<?php
namespace Apirone\Merchant\Block;

use Apirone\Merchant\Model\Payment as ApironePayment;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;


class PayBitcoin extends \Magento\Framework\View\Element\Template
{
	protected $ApironePayment;
	protected $orderFactory;
    protected $checkoutSession;
    protected $backendSession;
    protected $urlBuilder;
    protected $storeManager;

	public function __construct(
        Context $context,
        Session $checkoutSession,
        BackendSession $backendSession,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig,
        ApironePayment $ApironePayment,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->backendSession = $backendSession;
        $this->scopeConfig = $scopeConfig;
        $this->ApironePayment = $ApironePayment;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
    }

	public function getAllData()
    {	
        $input_address = $this->getRequest()->getParam('address');
        $order_id = $this->getRequest()->getParam('order');

        $sales = $this->ApironePayment->abf_getSales($order_id);
        if(!(isset($sales) && $input_address == $sales[0]['address'] && $order_id == $sales[0]['order_id']))
            $this->_redirect('checkout/cart');

        if($this->scopeConfig->getValue('payment/apirone_merchant/merchant_name', ScopeInterface::SCOPE_STORE) != ''){
            $data['merchantname'] = $this->scopeConfig->getValue('payment/apirone_merchant/merchant_name', ScopeInterface::SCOPE_STORE);
        } else {
            $data['merchantname'] = $this->storeManager->getStore()->getName();
        }

        $currentOrder = $this->orderFactory->create()->load($order_id);
        $order['currency_code'] = $currentOrder->getOrderCurrencyCode();
        $order['total'] = $currentOrder->getGrandTotal();
        $response_btc = $this->ApironePayment->abf_convert_to_btc($order['currency_code'], $order['total'], 1);

        $data['key'] = $input_address;
        $data['order'] = intval($order_id);
        $data['message'] = urlencode("bitcoin:" . $input_address . "?amount=" . $response_btc . "&label=Apirone");
        $data['response_btc'] = number_format($response_btc, 8, '.', '');
        $data['input_address'] = $input_address;
        $data['current_date'] = date('Y-m-d');
        $data['back_to_cart'] = $this->urlBuilder->getUrl('checkout/cart');

        return $data;
    }

}