define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Conekta_Payments/payment/base-form',
                transactionResult: ''
            },

            getFormTemplate: function(){
                return 'Conekta_Payments/payment/oxxo/form'
            },

            initialize: function() {
                var self = this;
                this._super();
            },

            getCode: function () {
                return 'conekta_oxxo';
            },

            isActive: function () {
                return true;
            },

            getGlobalConfig: function() {
                return window.checkoutConfig.payment.conekta_global
            },

            getConektaLogo: function() {
                return this.getGlobalConfig().conekta_logo;
            },

            /** Returns send check to info */
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },

            beforePlaceOrder: function () {
            	this.placeOrder();
            }
        });
    }
);
