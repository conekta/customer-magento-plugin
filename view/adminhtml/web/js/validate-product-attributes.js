require([
    'jquery',
    'mage/translate',
    'jquery/validate'],
    function($){
        const MAX_ATTRIBUTES = 12;
        $.validator.addMethod(
            'validate-product-attributes', function (options) {
                return options.length <= MAX_ATTRIBUTES;
            }, $.mage.__('Please select a maximum of 12 attributes')
        );
    }
);
