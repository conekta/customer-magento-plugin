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
                type: 'conekta_card',
                component: 'Conekta_Payments/js/view/payment/method-renderer/card-method'
            },
            {
                type: 'conekta_oxxo',
                component: 'Conekta_Payments/js/view/payment/method-renderer/oxxo-method'
            },
            {
                type: 'conekta_spei',
                component: 'Conekta_Payments/js/view/payment/method-renderer/spei-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);