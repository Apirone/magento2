<?php
namespace Apirone\Merchant\Model;

use Apirone\Merchant\Model\TransactionsFactory;
use Apirone\Merchant\Model\SalesFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderFactory;


class Payment extends AbstractMethod {
    const CODE = 'apirone_merchant';
        /**
    * @var \Magento\Framework\HTTP\Client\Curl
    */
   
   /**
    * @param Context                             $context
    * @param \Magento\Framework\HTTP\Client\Curl $curl
    */
    protected $_code = 'apirone_merchant';
    protected $_curl;
    protected $_modelTransactionsFactory;
    protected $_modelSalesFactory;
    protected $_logger;
    protected $_registry;
    protected $urlBuilder;
    protected $orderFactory;

   public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        TransactionsFactory $modelTransactionsFactory,
        SalesFactory $modelSalesFactory,
        DateTime $date,
        Curl $curl,
        LoggerInterface $log,
        UrlInterface $urlBuilder,
        OrderFactory $orderFactory
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->_modelTransactionsFactory = $modelTransactionsFactory;
        $this->_modelSalesFactory = $modelSalesFactory;
        $this->_date = $date;
        $this->_curl = $curl;
        $this->_logger = $log;
        $this->orderFactory = $orderFactory;
        $this->_registry = $registry;
    }

    public function abf_create($neworder){

        $order_id = $neworder->getIncrementId();
        $order_id = intval( $order_id );
        $order['currency_code'] = $neworder->getOrderCurrencyCode();
        $order['total'] = $neworder->getGrandTotal();
      
        $test = $this->scopeConfig->getValue('payment/apirone_merchant/debug_mode', ScopeInterface::SCOPE_STORE);

        $apirone_adr = 'https://apirone.com/api/v1/receive';
        
        $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], 1);

        if ($this->abf_is_valid_for_use($order['currency_code']) && $response_btc > 0) {
                /**
                 * Args for Forward query
                 */           
                
            $sales = $this->abf_getSales($order_id);
            $result['error_message'] = false;
                
            if ($sales == null) {

                $new_order_status = $this->scopeConfig->getValue('payment/apirone_merchant/order_status_new', ScopeInterface::SCOPE_STORE);
                $neworder->setState($new_order_status)
                         ->setStatus($new_order_status);
                $neworder->addStatusHistoryComment("Waiting for payment");
                $neworder->save();  

                $secret = $this->abf_getKey($order_id);

                $args = array(
                    'address' => $this->scopeConfig->getValue('payment/apirone_merchant/merchant_wallet', ScopeInterface::SCOPE_STORE),
                    'callback' => urlencode(
                        $this->urlBuilder->getUrl('apirone/payment/callback') . '?secret='. $secret .'&order_id='.$order_id)
                );
                $apirone_create = $apirone_adr . '?method=create&address=' . $args['address'] . '&callback=' . $args['callback'];

                    $response_create = $this->_curl->get($apirone_create);
                    $response_create = $this->_curl->getBody();
                    $response_create = json_decode($response_create, true);
                    if ($response_create['input_address'] != null){
                        $this->abf_addSale($order_id, $response_create['input_address']);
                    } else{
                        $result['error_message'] =  'No Input Address from Apirone :( Go to previous step';
                    }
                } else {
                    $response_create['input_address'] = $sales[0]['address'];
                }             
                    if(isset($apirone_create) && $test)
                        $this->abf_logger("Request: " . $apirone_create . " , Response: " . $response_btc);
            } else {
                $result['error_message'] = "Apirone couldn't exchange " . $order['currency_code'] . "to BTC :(";
            }

        $result['payment_url'] = $this->urlBuilder->getUrl('apirone/pay/paybitcoin') . '?order='.$order_id.'&address='. $response_create['input_address'];
        return $result;
    }

    public function abf_getReceiveAddress(Order $order) {
        $apiOrder = $this->abf_create($order);

        if (!$apiOrder['error_message']) {
            return array(
                'status' => true,
                'payment_url' => $apiOrder['payment_url']
            );
        } else {
            return array(
                'status' => false
            );
        }
    }

    public function abf_getTransactions($order_id){
        $resultPage = $this->_modelTransactionsFactory->create(); // get Transacdtions model
        $collection = $resultPage->getCollection() //Get Collection of module data
            ->addFieldToFilter('order_id', $order_id);
        return $collection->getData();     
    }

    public function abf_getSales($order_id, $address = NULL) {
        $resultPage = $this->_modelSalesFactory->create(); // get Transacdtions model
        if (is_null($address)) {
          $collection = $resultPage->getCollection()
            ->addFieldToFilter('order_id', $order_id);          
        } else {
            $collection = $resultPage->getCollection() //Get Collection of module data
                ->addFieldToFilter('order_id', $order_id)
                ->addFieldToFilter('address', $address);
        }
        return $collection->getData(); 
    }

    public function abf_addSale($order_id, $address) {
        $resultPage = $this->_modelSalesFactory->create();
        $data = array('time'=>$this->_date->gmtDate(),'address'=>$address, 'order_id'=>$order_id); 
        $model = $resultPage->setData($data); 
        $model->save();
        return true;
    }


    public function abf_addTransaction($order_id, $thash, $input_thash, $paid, $confirmations) {
        $delTx = $this->_modelTransactionsFactory->create();
        $dataToDelete = $delTx->getCollection();
        $dataToDelete->addFieldToFilter('input_thash', $input_thash)
                     ->load();
        if($dataToDelete->getSize() > 0) {
            $this->_registry->register('isSecureArea', true);
            foreach ($dataToDelete as $deleteItem) {
                $deleteItem->delete();
            }
        }
        $data = array('time'=>$this->_date->gmtDate(), 'order_id'=>$order_id, 'thash'=>$thash,'input_thash'=>$input_thash, 'paid'=>$paid, 'confirmations' => $confirmations); 
        $query = $this->_modelTransactionsFactory->create();
        $addTx = $query->setData($data);
        $addTx->save();
        return true;
    }

    public function abf_updateTransaction($where_input_thash, $where_paid, $confirmations, $thash = NULL, $where_order_id = NULL, $where_thash = 'empty') {
        $query = $this->_modelTransactionsFactory->create();

        $collections = $this->_modelTransactionsFactory->create();
        if (is_null($thash) || is_null($where_order_id)) {
            $collections->getCollection()
                ->addFieldToFilter('paid', array('eq' => $where_paid))
                ->addFieldToFilter('thash', array('eq' => $thash))
                ->addFieldToFilter('input_thash', array('eq' => $where_input_thash));
            foreach($collections as $item) {
                $data = array('time'=>$this->_date->gmtDate(), 'confirmations' => $confirmations);
            }
        } else {
            $collections->getCollection()
                ->addFieldToFilter('order_id', array('eq' => $where_order_id))
                ->addFieldToFilter('paid', array('eq' => $where_paid))
                ->addFieldToFilter('thash', array('eq' => $where_thash))
                ->addFieldToFilter('input_thash', array('eq' => $where_input_thash));
            foreach($collections as $item) {
                    $data = array('time'=>$this->_date->gmtDate(), 'confirmations' => $confirmations, 'thash'=>$thash);
            }
        }
        $collections->setData($data);
        $collections->save();
        return true;
    }

        public function abf_convert_to_btc($currency, $value, $currency_value){
           if ($currency == 'BTC') {
                return $value * $currency_value;
            } else { 
                if ($currency == 'USD' || $currency == 'EUR' || $currency == 'GBP') {
                    $apirone_tobtc = 'https://apirone.com/api/v1/tobtc?currency='.$currency.'&value='.$value;
                    $response_btc = $this->_curl->get($apirone_tobtc);
                    $response_btc = $this->_curl->getBody();
                    $response_btc = json_decode($response_btc, true);
                    return round($response_btc * $currency_value, 8);
                } else {
                if($this->abf_is_valid_for_use($currency)){
                    $response_coinbase = $this->_curl->get('https://api.coinbase.com/v2/prices/BTC-'. $currency .'/buy');
                    $response_coinbase = $this->_curl->getBody();
                    $response_coinbase = json_decode($response_coinbase, true);
                    $response_coinbase = $response_coinbase['data']['amount'];
                    if (is_numeric($response_coinbase)) {
                       return round(($value  * $currency_value) / $response_coinbase, 8);
                    } else {
                        return 0;
                    }                   
                } else {
                    return 0;
                }
                }     
            }           
    }


        public function abf_is_valid_for_use($currency)
        {
            $check_currency = $currency;
            
            if (!in_array($check_currency, array(
                'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BCH', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BYR', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF', 'CLP', 'CNH', 'CNY', 'COP', 'CRC', 'CUC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EEK', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTC', 'LTL', 'LVL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MTL', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'SVC', 'SZL', 'THB', 'TJS', 'TMT', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU', 'UZS', 'VEF', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT', 'YER', 'ZAR', 'ZMK', 'ZMW', 'ZWL'
            ))) {
                return false;
            }
            return true;
        }

        //checks that order has sale
        private function abf_sale_exists($order_id, $input_address)
        {
            $sales = $this->abf_getSales($order_id, $input_address);
            if ($sales[0]['address'] == $input_address) {return true;} else {return false;};
        }

        // function that checks what user complete full payment for order
        public function abf_check_remains($order_id)
        {
            $currentOrder = $this->orderFactory->create()->load($order_id);
            $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
            $order['total'] = $currentOrder->getGrandTotal();
            $order['status'] = $currentOrder->getStatus();

            $total = $this->abf_convert_to_btc($order['currency_code'], $order['total'], 1);

            $sales = $this->abf_getSales($order_id);
            $transactions = $this->abf_getTransactions($order_id);
            $remains = 0;
            $total_paid = 0;
            $total_empty = 0;
            if($transactions != '')
                foreach ($transactions as $transaction) {
                    if ($transaction['thash'] == "empty") $total_empty+=$transaction['paid'];
                    $total_paid+=$transaction['paid'];
                }
            $total_paid/=1E8;
            $total_empty/=1E8;
            $remains = $total - $total_paid;
            $remains_wo_empty = $remains + $total_empty;
            if ($remains_wo_empty > 0) {
                return false;
            } else {
                return true;
            };
        }

        public function abf_logger($message) {
            if ($this->scopeConfig->getValue('payment/apirone_merchant/debug_mode', ScopeInterface::SCOPE_STORE)) {
                $this->_logger->info($message);
            }
        }

        private function abf_getKey($order_id) {
            $key = $this->scopeConfig->getValue('payment/apirone_merchant/callback_secret', ScopeInterface::SCOPE_STORE);
            return md5($key . $order_id);
        }

        public function abf_remains_to_pay($order_id) {   
            $currentOrder = $this->orderFactory->create()->load($order_id);
            $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
            $order['total'] = $currentOrder->getGrandTotal();

            $sales = $this->abf_getSales($order_id);
            $transactions = $this->abf_getTransactions($order_id);

            $total_paid = 0;
            if($transactions != '')
            foreach ($transactions as $transaction) {
                $total_paid+=$transaction['paid'];
            }
            $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], 1);
            $remains = $response_btc - $total_paid/1E8;
            if($remains < 0) $remains = 0;  
            return $remains;
        }
        public function abf_check_data($apirone_order) {
            $abf_check_code = 100; //No value
            if (!empty($apirone_order['value'])) {
                $abf_check_code = 101; //No input address
                if (!empty($apirone_order['input_address'])) {
                    $abf_check_code = 102; //No order_id
                    if (!empty($apirone_order['orderId'])) {
                        $abf_check_code = 103; //No secret
                        if (!empty($apirone_order['secret'])) {
                            $abf_check_code = 104; //No confirmations
                            if ($apirone_order['confirmations']>=0) {
                                    $abf_check_code = 106; //No input transaction hash
                                    if (!empty($apirone_order['input_transaction_hash'])) {
                                        $abf_check_code = 200; //No transaction hash
                                        if (!empty($apirone_order['transaction_hash'])){
                                            $abf_check_code = 201; //All data is ready
                                        }                           
                                    }                    
                            }                   
                        }
                    }
                }
            }
            return $abf_check_code;
        }     


        private function abf_transaction_exists($thash, $order_id) {
            $transactions = $this->abf_getTransactions($order_id);
            $flag = false;
            if($transactions != '')
                foreach ($transactions as $transaction) {
                if($thash == $transaction['thash']){
                    $flag = true; // same transaction was in DB
                    break;
                }
            }
            return $flag;
        }  
    private function abf_input_transaction_exists($input_thash, $order_id) {
        $transactions = $this->abf_getTransactions($order_id);
        $flag = false;
        if($transactions != '')
            foreach ($transactions as $transaction) {
            if($input_thash == $transaction['input_thash']){
                $flag = true; // same transaction was in DB
                break;
            }
        }
        return $flag;
    } 

    private function secret_is_valid($secret, $order_id) {
        $flag = false;
        if($secret == $this->abf_getKey($order_id)){
            $flag = true;
        }
        return $flag;
    }
    private function confirmations_is_ok($confirmations){ 
        define("ABF_COUNT_CONFIRMATIONS", $this->scopeConfig->getValue('payment/apirone_merchant/confirmations_count', ScopeInterface::SCOPE_STORE));
        $flag = false;
        if($confirmations >= ABF_COUNT_CONFIRMATIONS) {
            $flag = true;
        }
    return $flag;
    }


    public function abf_validate_data($apirone_order) {
        $abf_check_code = 300; //No sale exists
        if ($this->abf_sale_exists($apirone_order['orderId'], $apirone_order['input_address'])) {
            $abf_check_code = 302; //secret is invalid
            if ($this->secret_is_valid($apirone_order['secret'], $apirone_order['orderId'])) {
                $abf_check_code = 400; //validate complete
            }
        }
        return $abf_check_code;
    }
    public function abf_empty_transaction_hash($apirone_order){
        if ($this->abf_input_transaction_exists($apirone_order['input_transaction_hash'],$apirone_order['orderId'])) {
            $this->abf_updateTransaction(
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations']
            );
            $abf_check_code = 500; //update existing transaction
        } else {
            $this->abf_addTransaction(
                $apirone_order['orderId'],
                'empty',
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations']
            );
            $abf_check_code = 501; //insert new transaction in DB without transaction hash
        }
        return $abf_check_code;
    }
    private function abf_calculate_payamount($apirone_order) {
        $transactions = $this->abf_getTransactions($apirone_order['orderId']);
        $payamount = 0;
        if($transactions != '')
            foreach ($transactions as $transaction) {
                if($transaction['thash'] != 'empty')
                    $payamount += $transaction['paid'];
            }
        return $payamount;
    }

    public function abf_skip_transaction($apirone_order) {
        define("ABF_COUNT_CONFIRMATIONS", $this->scopeConfig->getValue('payment/apirone_merchant/confirmations_count', ScopeInterface::SCOPE_STORE)); // number of confirmations
        define("ABF_MAX_CONFIRMATIONS", 150); // max confirmations count
        $abf_check_code = NULL;
        if(($apirone_order['confirmations'] >= ABF_MAX_CONFIRMATIONS) && (ABF_MAX_CONFIRMATIONS != 0)) {// if callback's confirmations count more than ABF_MAX_CONFIRMATIONS we answer *ok*
            $abf_check_code="*ok*";
            $this->abf_logger('[Info] Skipped transaction: ' .  $apirone_order['transaction_hash'] . ' with confirmations: ' . $apirone_order['confirmations']);
        };
        return $abf_check_code;
    }

    private function abf_take_notes($apirone_order) {
        $currentOrder = $this->orderFactory->create()->load($apirone_order['orderId']);
        $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
        $order['total'] = $currentOrder->getGrandTotal();
        $response_btc = $this->abf_convert_to_btc($order['currency_code'], $order['total'], 1);
        $payamount = $this->abf_calculate_payamount($apirone_order);
        $notes  = 'Input Address: ' . $apirone_order['input_address'] . '; Transaction Hash: ' . $apirone_order['transaction_hash'] . '; Payment: ' . number_format($apirone_order['value']/1E8, 8, '.', '') . ' BTC; ';
        $notes .= 'Total paid: '.number_format(($payamount)/1E8, 8, '.', '').' BTC; ';
        if (($payamount)/1E8 < $response_btc)
            $notes .= 'User trasfrer not enough money in your shop currency. Waiting for next payment; ';
        if (($payamount)/1E8 > $response_btc)
            $notes .= 'User trasfrer more money than You need in your shop currency; ';
        $notes .= 'Order total: '.$response_btc . ' BTC; ';
        if ($this->abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment it's not completed 
            $notes .= 'Successfully paid.';
        }
        return $notes;
    }
    public function abf_filled_transaction_hash($apirone_order){
        $currentOrder = $this->orderFactory->create()->load($apirone_order['orderId']);
        $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
        $order['total'] = $currentOrder->getGrandTotal();
        if($this->abf_transaction_exists($apirone_order['transaction_hash'],$apirone_order['orderId'])){
            $abf_check_code = 600;//update transaction
            $this->abf_updateTransaction(
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations'],
                $apirone_order['transaction_hash'],
                $apirone_order['orderId']
            ); 
        } else {
            $abf_check_code = 601; //small confirmations count for update tx
            if ($this->confirmations_is_ok($apirone_order['confirmations'])) {
            $this->abf_addTransaction(
                $apirone_order['orderId'],
                $apirone_order['transaction_hash'],
                $apirone_order['input_transaction_hash'],
                $apirone_order['value'],
                $apirone_order['confirmations']
            );
            $notes = $this->abf_take_notes($apirone_order);
            $abf_check_code = '*ok*';//insert new TX with transaction_hash
            if ($this->abf_check_remains($apirone_order['orderId'])){ //checking that payment is complete, if not enough money on payment is not completed
                $complete_order_status = $this->scopeConfig->getValue('payment/apirone_merchant/order_status_complete', ScopeInterface::SCOPE_STORE);
                $currentOrder->setState($complete_order_status)
                             ->setStatus($complete_order_status);
            } else{
                $partiallypaid_order_status = $this->scopeConfig->getValue('payment/apirone_merchant/order_status_pending', ScopeInterface::SCOPE_STORE);
                $currentOrder->setState($partiallypaid_order_status)
                             ->setStatus($partiallypaid_order_status);
            }
            $currentOrder->addStatusHistoryComment($notes);
            $currentOrder->save();
        }
    }
    return $abf_check_code;
}
}
