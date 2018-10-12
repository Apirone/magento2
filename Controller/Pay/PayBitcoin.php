<?php
/**
 * Apirone PayBitcoin controller
 *
 * @category    Apirone
 * @package     Apirone_Merchant
 * @author      Apirone
 * @copyright   Apirone (https://apirone.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Apirone\Merchant\Controller\Pay;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Framework\View\Result\Page;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Exception\LocalizedException;
use Apirone\Merchant\Model\Payment as ApironePayment;

class PayBitcoin extends Action
{

    protected $_resultPageFactory;
    protected $ApironePayment;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ApironePayment $ApironePayment
    ) {
        parent::__construct(
            $context
        );
        $this->_resultPageFactory = $resultPageFactory;
        $this->ApironePayment = $ApironePayment;
    }
    /**
     * @return Page
     */
    public function execute()
    {	

        $safe_order = intval( $this->getRequest()->getParam('order') );
        $safe_address = $this->getRequest()->getParam('address');
        if(isset( $safe_address )) {
            if ( strlen( $safe_address ) > 64 ) {
               $safe_address = substr( $safe_address, 0, 64 );
            }
        }
        if ( !isset($safe_address) ) {
            $safe_address = '';
        }
        if ( !isset($safe_order) ) {
            $safe_order = '';
        }

        $order_id = $safe_order;
        $input_address = $safe_address;

        $sales = $this->ApironePayment->abf_getSales($order_id);
        if (isset($sales[0]['address'])) {
            if($input_address == $sales[0]['address'] && $order_id == $sales[0]['order_id']){            
                $resultPage = $this->_resultPageFactory->create();
                return $resultPage;
            } else {
                $this->_redirect('checkout/cart');
            }
        }
        else {
            $this->_redirect('checkout/cart');
        }
    }
}
