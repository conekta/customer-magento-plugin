require([
  'jquery',
  'mage/translate',
  'jquery/validate'],
function($){
  $.validator.addMethod(
      'validate-expiry-hours', function (v) {
          return $.mage.isEmptyNoTrim(v) || (v >= 1 && v <= 23);
      }, $.mage.__('Please use digits only (1-23) in this field length.'));
  }
);
