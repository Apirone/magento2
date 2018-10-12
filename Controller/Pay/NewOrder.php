<?php
namespace Apirone\Merchant\Controller\Pay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Apirone\Merchant\Model\Payment as ApironePayment;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session;
use Magento\Backend\Model\Session as BackendSession;


class NewOrder extends Action
{
	protected $ApironePayment;
	protected $orderFactory;
    protected $checkoutSession;
    protected $backendSession;

	public function __construct(
        Context $context,
        Session $checkoutSession,
        BackendSession $backendSession,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig,
        ApironePayment $ApironePayment
    ) {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->backendSession = $backendSession;
        $this->scopeConfig = $scopeConfig;
        $this->ApironePayment = $ApironePayment;
    }

	public function execute() {
        $id = $this->checkoutSession->getLastOrderId();
        $order = $this->orderFactory->create()->load($id);
        if (!$order->getIncrementId()) {
            $this->getResponse()->setBody(json_encode(array(
                'status' => false,
                'reason' => 'Order Not Found',
            )));
            return;
        }
        $this->getResponse()->setBody(json_encode($this->ApironePayment->abf_getReceiveAddress($order)));
        return;
    }

}