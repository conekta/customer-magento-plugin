require([
        'jquery',
        'mage/translate',
        'jquery/validate'],
    function($){
        $.validator.addMethod(
            'validate-custom-length', function (v) {
                return jQuery.mage.isEmptyNoTrim(v) || /^[2-9]+$/.test(v) && v.length == 1;
            }, $.mage.__('Please use digits only (2-9) in this field length.'));
    }
);
