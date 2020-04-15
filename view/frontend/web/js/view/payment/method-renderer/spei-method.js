/*browser:true*/
/*global define*/
define(
        [
            'Magento_Checkout/js/view/payment/default'
        ],
        function (Component) {
            'use strict';
            
            return Component.extend({
                defaults: {
                    template: 'Conekta_Payments/payment/conekta-spei'
                },
                getCode: function () {
                    return 'conekta_spei';
                },
                isActive: function () {
                    return true;
                },
                /** Returns send check to info */
                getMailingAddress: function () {
                    return window.checkoutConfig.payment.checkmo.mailingAddress;
                }
            });
        }
);