require([
        'jquery',
        'mage/translate',
        'jquery/validate'],
    function($){
        $.validator.addMethod(
            'validate-expiry-days', function (v) {
                return $.mage.isEmptyNoTrim(v) || (v >= 1 && v <= 31);
            }, $.mage.__('Please use digits only (1-31) in this field length.'));
    }
);
