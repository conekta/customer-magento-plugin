define(
    [
        'ko',
        'conekta',
        'conektaCheckout',
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'mage/storage',
        'uiRegistry'
    ],
    function (ko, CONEKTA, conektaCheckout, Component, $, quote, customer, validator, storage, uiRegistry) {
        'use strict';
        
        return Component.extend({
            defaults: {
                template: 'Conekta_Payments/payment/base-form',
                transactionResult: '',
                renderProperties: {
                    shippingMethodCode: '',
                    quoteBaseGrandTotal: '',
                    shippingAddress: '',
                    billingAddress: '',
                    guestEmail: ''
                }
            },
            
            getFormTemplate: function(){
                return 'Conekta_Payments/payment/embedform/form'
            },
            
            initObservable: function () {

                this._super()
                    .observe([
                        'checkoutId',
                        'isIframeLoaded',
                        'isVisiblePaymentButton',
                        'iframOrderData',
                        'conektaError'
                ]);
                this.iframOrderData('');
                this.checkoutId('');
                this.conektaError(null);
                
                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                var shippingAddress = quote.shippingAddress._latestValue?.getCacheKey();
                var billingAddress = JSON.stringify(quote.billingAddress());
                var shippingMethodCode = '';
                if(quote.shippingMethod._latestValue){
                    shippingMethodCode = quote.shippingMethod._latestValue.method_code;
                }
                
                this.renderProperties.quoteBaseGrandTotal = baseGrandTotal;
                this.renderProperties.shippingMethod = shippingMethodCode;
                this.renderProperties.shippingAddress = shippingAddress;
                this.renderProperties.billingAddress = billingAddress;
                this.renderProperties.guestEmail = quote.guestEmail;
                
                //Suscriptions to re-render
                quote.totals.subscribe(this.reRender, this);
                uiRegistry
                    .get('checkout.steps.billing-step.payment.customer-email')
                    .email
                    .subscribe(this.reRender, this);

                return this;
            },
            
            initialize: function() {
                var self = this;
                this._super();
            },

            reRender: function(total){

                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                var shippingAddress = quote.shippingAddress._latestValue?.getCacheKey();
                var shippingMethodCode = '';
                if(quote.shippingMethod._latestValue){
                    shippingMethodCode = quote.shippingMethod._latestValue.method_code;
                }
                
                var hasToReRender = false;

                //check for total changes
                if (baseGrandTotal !== this.renderProperties.quoteBaseGrandTotal) {
                    this.renderProperties.quoteBaseGrandTotal = baseGrandTotal;
                    hasToReRender = true;
                }

                //check for shipping methods changes
                if (shippingMethodCode !== this.renderProperties.shippingMethod) {
                    this.renderProperties.shippingMethod = shippingMethodCode;
                    hasToReRender = true;
                }

                //check for shipping changes
                if (shippingAddress !== this.renderProperties.shippingAddress) {
                    this.renderProperties.shippingAddress = shippingAddress;
                    hasToReRender = true;
                }

                //check for billing changes
                var quoteBilling = quote.billingAddress();
                var strBillingAddr = quoteBilling? JSON.stringify(quote.billingAddress()) : '';
                if(strBillingAddr !== this.renderProperties.billingAddress ){
                    hasToReRender = true;
                }
                this.renderProperties.billingAddress = strBillingAddr;
                
                //check for guest email changes on virtual cart
                if (!customer.isLoggedIn() && 
                    quote.isVirtual && 
                    quote.guestEmail !== this.renderProperties.guestEmail
                ) {
                    hasToReRender = true;
                }
                this.renderProperties.guestEmail = quote.guestEmail;

                if(hasToReRender){
                    this.loadCheckoutId();
                }    
            },

            validateRenderEmbedForm: function(){
                
                var isValid = true;

                /**
                 * Allow render if:
                 *  - Billing addres has been set
                 *  - Customer is logged in. In case is not, check if
                 *    cart is virtual and guest email has been set
                 */
                if (this.renderProperties.billingAddress && 
                    (customer.isLoggedIn() ||
                    (
                        !customer.isLoggedIn() && 
                        quote.isVirtual && 
                        quote.guestEmail
                    )
                   )
                ) {
                    isValid = true;
                    this.conektaError(null);
                } else {
                    isValid = false;
                    this.conektaError('Complete todos los campos requeridos para continuar');
                }
                
                return isValid;
            },

            loadCheckoutId: function() {
                var self = this;
                var guest_email = '';
                if (this.isLoggedIn() === false) {
                    guest_email = quote.guestEmail;
                }
                var params = {
                    'guestEmail': guest_email
                };
                
                if(this.validateRenderEmbedForm()){
                    return  $.ajax({
                        type: 'POST',
                        url: self.getcreateOrderUrl(),
                        data: params,
                        async: true,
                        showLoader: true,
                        success: function (response) {
                            self.conektaError(null);
                            self.checkoutId(response.checkout_id);
                            
                            if(self.checkoutId()){
                                self.renderizeEmbedForm();
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error(status);
                            self.conektaError(xhr.responseJSON.error_message);
                        }
                    });
                }
                
            },

            renderizeEmbedForm: function(){
                var self = this;
                document.getElementById("conektaIframeContainer").innerHTML="";
                window.ConektaCheckoutComponents.Integration({
                    targetIFrame: '#conektaIframeContainer',
                    checkoutRequestId: this.checkoutId(),
                    publicKey: this.getPublicKey(),
                    paymentMethods: this.getPaymenMethods(),
                    options: {
                        theme: 'default'
                    },
                    onCreateTokenSucceeded: function (token) {
                        
                    },
                    onCreateTokenError: function (error) {
                        console.error(error);
                    },
                    onFinalizePayment: function (event) {
                        self.iframOrderData(event);
                        self.beforePlaceOrder();
                    }
                });
                
                $('#conektaIframeContainer').find('iframe').attr('data-cy', 'the-frame');
            },

            getData: function () {
                var number = this.creditCardNumber().replace(/\D/g,'');
                if(this.iframOrderData() !== '') {
                    var params = this.iframOrderData();
                    var data = {
                        'method': this.getCode(),
                        'additional_data': {
                            'payment_method': params.charge.payment_method.type,
                            'cc_type': params.charge.payment_method.brand,
                            'cc_last_4': params.charge.payment_method.last4,
                            'reference': params.reference,
                            'order_id': params.charge.order_id,
                            'txn_id': params.charge.id,
                            'card_token': $("#" + this.getCode() + "_card_token").val(),
                            'iframe_payment': true
                        }
                    };
                    return data;
                }
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method': '',
                        'cc_type': this.creditCardType(),
                        'cc_last_4': number.substring(number.length-4, number.length),
                        'card_token': $("#" + this.getCode() + "_card_token").val(),
                        'reference': '',                        
                        'iframe_payment': false,
                        'order_id': '',
                        'txn_id': ''
                    }
                };

                if (this.activeMonthlyInstallments()) {
                    data['additional_data']['monthly_installments'] = $("#" + this.getCode() + "_monthly_installments").children("option:selected").val();
                }

                return data;
            },

            beforePlaceOrder: function () {
                var self = this;
                if(this.iframOrderData() !== ''){
                    self.placeOrder();
                    return;
                }
            },

            validate: function() {
                if(this.iframOrderData() !== '') {
                    return true;
                }

                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getCode: function() {
                return 'conekta_ef';
            },

            isActive: function() {
                return true;
            },

            getGlobalConfig: function() {
                return window.checkoutConfig.payment.conekta_global
            },

            getMethodConfig: function() {
                return window.checkoutConfig.payment.conekta_ef
            },

            getPublicKey: function() {
                return this.getGlobalConfig().publicKey;
            },

            getPaymenMethods: function() {
                return this.getMethodConfig().paymentMethods;
            },

            getConektaLogo: function() {
                return this.getGlobalConfig().conekta_logo;
            },

            getcreateOrderUrl: function() {
                return this.getMethodConfig().createOrderUrl;
            },
            
            isLoggedIn: function () {
                return customer.isLoggedIn();
            },

            activeMonthlyInstallments: function() {
                return this.getMethodConfig().active_monthly_installments;
            },

            getMinimumAmountMonthlyInstallments: function() {
                return this.getMethodConfig().minimum_amount_monthly_installments;
            },
        });
    }
);
