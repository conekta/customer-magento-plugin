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
                type: 'conekta_cc',
                component: 'Conekta_Payments/js/view/payment/method-renderer/creditcard'
            },
            {
                type: 'conekta_oxxo',
                component: 'Conekta_Payments/js/view/payment/method-renderer/oxxo'
            },
            {
                type: 'conekta_spei',
                component: 'Conekta_Payments/js/view/payment/method-renderer/spei'
            }
        );
        return Component.extend({});
    }
);
