require([
    'jquery',
    'jquery/ui',
    'jquery/validate'
], function($) {
    'use strict';
    return function () {
        $.validator.addMethod(
            "validate-limitnumber",
            function (v) {
                return jQuery.mage.isEmptyNoTrim(v) || /^[2-9]+$/.test(v);
            },
            'Please use digits only (2-9) in this field.'
        );
    }
});