/**
 * Apirone payment method model
 *
 * @category    Apirone
 * @package     Apirone_Merchant
 * @author      Apirone
 * @copyright   Apirone (https://apirone.com)
 * @license     https://github.com/apirone/magento2-plugin/blob/master/LICENSE The MIT License (MIT)
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'apirone_merchant',
                component: 'Apirone_Merchant/js/view/payment/method-renderer/apirone-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);