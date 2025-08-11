define([
    'Magento_Checkout/js/view/payment/default',
    'mage/url',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/translate'
], function (Component, url, placeOrderAction, selectPaymentMethodAction, customer, checkoutData, additionalValidators, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Conekta_Payments/payment/bnpl/form'
        },

        getCode: function() {
            return 'conekta_bnpl';
        },

        getData: function() {
            return {
                'method': this.item.method,
                'additional_data': {}
            };
        },

        getTitle: function() {
            return window.checkoutConfig.payment.conekta_bnpl.title || $t('Buy Now Pay Later');
        },

        getLogo: function() {
            return window.checkoutConfig.payment.conekta_bnpl.logo;
        },

        isActive: function() {
            return true;
        },

        validate: function() {
            return true;
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

        afterPlaceOrder: function () {
            window.location.replace(url.build('checkout/onepage/success/'));
        },

        getInstructions: function() {
            return $t('You will be redirected to complete the payment with Buy Now Pay Later installments.');
        }
    });
});
