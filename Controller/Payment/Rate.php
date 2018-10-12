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

class Rate extends Action
{
    protected $ApironePayment;

    public function __construct(
        Context $context,
        ApironePayment $ApironePayment
    ) {
        parent::__construct($context);
        $this->ApironePayment = $ApironePayment;
    }

    /**
     * On calling this, get checkout session order id and save it to backend session
     *
     * @return void
     */
    public function execute() {
        $currency_code = $this->getRequest()->getParam('currency_code');
        $value = $this->getRequest()->getParam('value');
        if (!$this->ApironePayment->abf_is_valid_for_use($currency_code)) {
                $this->getResponse()->setBody(json_encode([
                'status' => false,
                'reason' => 'Invalid currency',
            ]));
            return;
        }
        if (!is_numeric($value) || $value < 0) {
            $this->getResponse()->setBody(json_encode([
            'status' => false,
            'reason' => 'Invalid value',
        ]));
        return;
        }
        $rate = $this->ApironePayment->abf_convert_to_btc($currency_code, $value, 1);
        $this->getResponse()->setBody(json_encode([
            'status' => true,
            'rate' => number_format($rate, 8, '.', ''),
        ]));
    }
}