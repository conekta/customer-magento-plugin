// require([
//     'jquery',
//     'mage/translate',
//     'jquery/validate'],
// function($){
//     $.validator.addMethod(
//         'validate-product-attributes', function () {
//             $(".validate-product-attributes").change(function () {
//                 if ($("select option:selected").length > 3) {
//                     alert("menos");
//                 } else {
//                     alert("mas");
//                 }
//             });
//         }, $.mage.__('Please select a maximum of 12 attributes'));
//     }
// );


require([
    'jquery',
    'mage/translate',
    'jquery/validate'],
    function($){
        const MAX_ATTRIBUTES = 3;
        $.validator.addMethod(
            'validate-product-attributes', function (options) {
                return options.length <= MAX_ATTRIBUTES;
            }, $.mage.__('Please select a maximum of 12 attributes')
        );
    }
);
