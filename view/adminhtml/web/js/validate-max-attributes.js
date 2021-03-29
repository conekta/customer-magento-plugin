require([
    'jquery',
    'mage/translate',
    'jquery/validate'],
    function($){
        const MAX_ATTRIBUTES = 12;
        var productAmount = 0;
        var orderAmount = 0;
        $.validator.addMethod(
            'validate-product-attributes', function (options) {
                if (!options|| options.length == 0) {
                    return true;
                }
                if (options.length + orderAmount > MAX_ATTRIBUTES) {
                    return false;
                }
                productAmount = options.length;
                return true;
            }, $.mage.__('Please select a maximum of 12 attributes adding products and order')
        );        
        $.validator.addMethod(
            'validate-order-attributes', function (options) {
                if (!options || options.length == 0) {
                    return true;
                }
                if (options.length + productAmount > MAX_ATTRIBUTES) {
                    return false;
                }
                orderAmount = options.length;
                return true;
            }, $.mage.__('Please select a maximum of 12 attributes adding products and order')
        ); 
    }
);
