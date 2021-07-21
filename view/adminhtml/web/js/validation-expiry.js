require([
        'jquery',
        'mage/translate',
        'jquery/validate'],
    function($){
        $.validator.addMethod(
            'validate-expiry-days', function (v) {
                return $.mage.isEmptyNoTrim(v) || (v >= 3 && v <= 365);
            }, $.mage.__('Please use digits only (3-365) in this field length.'));
    }
);
