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
                type: 'conekta_ef',
                component: 'Conekta_Payments/js/view/payment/method-renderer/embedform'
            }
        );
        return Component.extend({});
    }
);
