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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Callback extends Action
{   
    /**
    * @var \Magento\Framework\HTTP\Client\Curl
    */
   
   /**
    * @param Context                             $context
    * @param \Magento\Framework\HTTP\Client\Curl $curl
    */
    protected $ApironePayment;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ApironePayment $ApironePayment,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->ApironePayment = $ApironePayment;
        $this->scopeConfig = $scopeConfig;
    }
    
    public function execute()
    {   
    $this->ApironePayment->abf_logger('[Info] Callback ' . $_SERVER['REQUEST_URI']);
    if ($this->getRequest()->getParam('secret') !== null) {
        $safe_secret = $this->getRequest()->getParam('secret');
    } else {
        $safe_secret = '';
    }
    if ($this->getRequest()->getParam('order_id') !== null) {
        $safe_order_id = intval( $this->getRequest()->getParam('order_id') );
    } else {
        $safe_order_id = '';
    }
    if ($this->getRequest()->getParam('confirmations') !== null) {
        $safe_confirmations = intval( $this->getRequest()->getParam('confirmations') );
    } else {
        $safe_confirmations = '';
    }
    if ($this->getRequest()->getParam('value') !== null) {
        $safe_value = intval( $this->getRequest()->getParam('value') );
    } else {
        $safe_value = '';
    }
    if ($this->getRequest()->getParam('input_address') !== null) {
        $safe_input_address = $this->getRequest()->getParam('input_address');
    } else {
        $safe_input_address = '';
    }
    if ($this->getRequest()->getParam('transaction_hash') !== null) {
        $safe_transaction_hash = $this->getRequest()->getParam('transaction_hash');
    } else {
        $safe_transaction_hash = '';
    }
    if ($this->getRequest()->getParam('input_transaction_hash') !== null) {
        $safe_input_transaction_hash = $this->getRequest()->getParam('input_transaction_hash');
    } else {
        $safe_input_transaction_hash = '';
    }

    if ( strlen( $safe_secret ) > 32 ) {
        $safe_secret = substr( $safe_secret, 0, 32 );
    }
    if ( $safe_order_id == 'undefined' ) {
         $safe_order_id = '';
    }
    if ( strlen( $safe_order_id ) > 25 ) {
        $safe_order_id = substr( $safe_order_id, 0, 25 );
    }
    if ( strlen( $safe_confirmations ) > 5 ) {
        $safe_confirmations = substr( $safe_confirmations, 0, 5 );
    }
    if ( ! $safe_confirmations ) {
        $safe_confirmations = 0;
    }
    if ( strlen( $safe_value ) > 16 ) {
        $safe_value = substr( $safe_value, 0, 16 );
    }
    if ( ! $safe_value ) {
        $safe_value = '';
    }
    if ( strlen( $safe_input_address ) > 64 ) {
        $safe_input_address = substr( $safe_input_address, 0, 64 );
    }
    if ( ! $safe_input_address ) {
        $safe_input_address = '';
    }
    if ( strlen( $safe_transaction_hash ) > 65 ) {
        $safe_transaction_hash = substr( $safe_transaction_hash, 0, 65 );
    }
    if ( ! $safe_transaction_hash ) {
        $safe_transaction_hash = '';
    }
    if ( strlen( $safe_input_transaction_hash ) > 65 ) {
        $safe_input_transaction_hash = substr( $safe_input_transaction_hash, 0, 65 );
    }
    if ( ! $safe_input_transaction_hash ) {
        $safe_input_transaction_hash = '';
    }
    $apirone_order = array(
        'value' => $safe_value,
        'input_address' => $safe_input_address,
        'orderId' => $safe_order_id, // order id
        'secret' => $safe_secret,
        'confirmations' => $safe_confirmations,
        'input_transaction_hash' => $safe_input_transaction_hash,
        'transaction_hash' => $safe_transaction_hash
    );
    $check_data_score = $this->ApironePayment->abf_check_data($apirone_order);
    $abf_api_output = $check_data_score;
    if( $check_data_score >= 200 ){
        $validate_score = $this->ApironePayment->abf_validate_data($apirone_order);
        $abf_api_output = $validate_score;
        if ($validate_score == 400) {
            if($check_data_score == 200){
                $data_action_code = $this->ApironePayment->abf_empty_transaction_hash($apirone_order);
            }
            if($check_data_score == 201){
                $data_action_code = $this->ApironePayment->abf_filled_transaction_hash($apirone_order);
            }
            $abf_api_output = $data_action_code;
        }
    }
    if($this->scopeConfig->getValue('payment/apirone_merchant/debug_mode', ScopeInterface::SCOPE_STORE)) {
        $this->getResponse()->setBody($abf_api_output);//global output
    } else {
        if($abf_api_output === '*ok*') {
            $this->getResponse()->setBody('*ok*');
        } else{
            $this->getResponse()->setBody($this->ApironePayment->abf_skip_transaction($apirone_order));
        }
    }
    return;
    }
}
