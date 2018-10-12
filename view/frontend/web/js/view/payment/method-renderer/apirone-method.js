define(
    [   
        'jquery',
        'ko',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/quote',
        'mage/url',
        'checkoutData',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
    ],

    function ($,
              ko,
              Component,
              totals,
              quote,
              url,
              checkoutData,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              additionalValidators) {
        'use strict';
        var self;
        return Component.extend({
            defaults: {
                template: 'Apirone_Merchant/payment/apirone-form',
                redirectAfterPlaceOrder: false
            },
            totals: quote.getTotals(),
            priceInBitcoins: ko.observable('...'),
            initialize: function () {
                self = this;
                this._super();
                this.rate();
            },
            rate: function() {
                var grand_total = this.totals().base_grand_total;
                var currency_code = this.totals().base_currency_code;
                var request = $.ajax({
                    url:  url.build('apirone/payment/rate?currency_code='+currency_code+'&value='+grand_total),
                    type: 'GET',
                    dataType: 'json'
                });
                request.done(function(response) {
                    if (response.status) {
                        self.priceInBitcoins(response.rate);
                    }
                });
            },

            placeOrder: function (data, event) {
                 if (event) {
                     event.preventDefault();
                 }
                 var self = this,
                     placeOrder,
                     emailValidationResult = customer.isLoggedIn(),
                     loginFormSelector = 'form[data-role=email-with-possible-login]';
                 if (!customer.isLoggedIn()) {
                     $(loginFormSelector).validation();
                     emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                 }
                 if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                     this.isPlaceOrderActionAllowed(false);
                     placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);
    
                     $.when(placeOrder).fail(function () {
                         self.isPlaceOrderActionAllowed(true);
                     }).done(this.afterPlaceOrder.bind(this));
                     return true;
                 }
                 return false;
            },

            selectPaymentMethod: function() {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function (quoteId) {
                 var request = $.ajax({
                 url: url.build('apirone/pay/neworder'),
                 type: 'POST',
                 dataType: 'json',
                 data: {quote_id: quoteId}
               });
               request.done(function(response) {
                 console.log(response);
                 if (response.status) {
                   window.location.replace(response.payment_url);
                 } else {
                   window.location.replace('/checkout/onepage/failure');
                 }
                });
             }            
            });
    }
);