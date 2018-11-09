<?php
/**
 * Apirone Callback controller
 *
 * @category    Apirone
 * @package     Apirone_Merchant
 * @author      Apirone
 * @copyright   Apirone (https://apirone.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Apirone\Merchant\Controller\Payment;

use Apirone\Merchant\Model\Payment as ApironePayment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\Client\Curl;


use Magento\Checkout\Model\Session;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Sales\Model\OrderFactory;

class Check extends Action
{
    protected $ApironePayment;
    protected $orderFactory;

    public function __construct(
        Context $context,
        ApironePayment $ApironePayment,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->ApironePayment = $ApironePayment;
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * On calling this, get checkout session order id and save it to backend session
     *
     * @return void
     */
    public function execute() {

        define("ABF_COUNT_CONFIRMATIONS", $this->scopeConfig->getValue('payment/apirone_merchant/confirmations_count', ScopeInterface::SCOPE_STORE)); // number of confirmations

        $safe_order = intval( $this->getRequest()->getParam('order') );

        if ( $safe_order == 'undefined') {
             $safe_order = '';
        }

        if ( strlen( $safe_order ) > 25 ) {
            $safe_order = substr( $safe_order, 0, 25 );
        }

        $safe_key = $this->getRequest()->getParam('key');
        if ( strlen( $safe_key ) > 64 ) {
           $safe_key = substr( $safe_key, 0, 64 );
        }
        if ( !isset($safe_key) ) {
            $safe_key = '';
        }

        if (!empty($safe_order) && !empty($safe_key)) {
            $currentOrder = $this->orderFactory->create()->load($safe_order);
            $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
            $order['total'] = $currentOrder->getGrandTotal();
            $order['status'] = $currentOrder->getStatus();
            /*print_r( $order );*/
            if (!empty($safe_order)) {
                $sales = $this->ApironePayment->abf_getSales($safe_order);
                $transactions = $this->ApironePayment->abf_getTransactions($safe_order);
            }
            $empty = 0;
            $value = 0;
            $paid_value = 0;

            $payamount = 0;
            $innetwotk_pay = 0;
            $last_transaction = '';
            $confirmed = '';
            $status = 'waiting';
            //print_r($sales);
            //print_r($transactions);
            if($transactions != array()) {
                $alltransactions = array();
            foreach ($transactions as $transaction) {
                if($transaction['thash'] == 'empty') {
                            $status = 'innetwork';
                            $innetwotk_pay += $transaction['paid'];
                }
                if($transaction['thash'] != 'empty') 
                    $payamount += $transaction['paid'];      
               //print_r($transaction);

                if ($transaction['thash'] == "empty"){
                    $empty = 1; // has empty value in thash
                    $value = $transaction['paid'];
                } else{
                    $paid_value = $transaction['paid'];
                    $confirmed = $transaction['thash'];
                }
                $alltransactions[] = array('thash' => $transaction['thash'], 'input_thash' => $transaction['input_thash'], 'confirmations' => $transaction['confirmations']);             
            }
            } else {
                $alltransactions = "";
            }
            if ($order == '') {
                $this->getResponse()->setBody('');
                return;
            }

            $response_btc = $this->ApironePayment->abf_convert_to_btc($order['currency_code'], $order['total'], 1);
            if ($order['status'] == $this->scopeConfig->getValue('payment/apirone_merchant/order_status_complete', ScopeInterface::SCOPE_STORE) && $this->ApironePayment->abf_check_remains($safe_order)) {
                $status = 'complete';
            }
                $remains_to_pay = number_format($this->ApironePayment->abf_remains_to_pay($safe_order), 8, '.', '');
                $last_transaction = $confirmed;
                $payamount = number_format($payamount/1E8, 8, '.', '');
                $innetwotk_pay = number_format($innetwotk_pay/1E8, 8, '.', '');
                $response_btc = number_format($response_btc, 8, '.', '');

            if($sales[0]['address'] == $safe_key){
            $ouput = array('total_btc' => $response_btc, 'innetwork_amount' => $innetwotk_pay, 'arrived_amount' => $payamount, 'remains_to_pay' => $remains_to_pay, 'transactions' => $alltransactions, 'status' => $status, 'count_confirmations' => ABF_COUNT_CONFIRMATIONS);
            $this->getResponse()->setBody(json_encode($ouput));
            } else {
                $this->getResponse()->setBody('');
            }

            return;
        }
    }
}