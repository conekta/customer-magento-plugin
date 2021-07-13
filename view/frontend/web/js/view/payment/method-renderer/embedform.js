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
        'mage/storage'
    ],
    function (ko, CONEKTA, conektaCheckout, Component, $, quote, customer, validator, storage) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Conekta_Payments/payment/base-form',
                transactionResult: '',
                renderProperties: {
                    shippingMethodCode: '',
                    quoteBaseGrandTotal: '',
                    shippingAddress: '',
                    billingAddress: ''
                },
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
                        'iframOrderData'
                ]);
                this.iframOrderData('');
                this.checkoutId('');
                
                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                var shippingMethodCode = '';
                if(quote.shippingMethod._latestValue){
                    shippingMethodCode = quote.shippingMethod._latestValue.method_code;
                }
                var shippingAddress = quote.shippingAddress._latestValue?.getCacheKey();
                var billingAddress = JSON.stringify(quote.billingAddress());
                
                this.renderProperties.quoteBaseGrandTotal = baseGrandTotal;
                this.renderProperties.shippingMethod = shippingMethodCode;
                this.renderProperties.shippingAddress = shippingAddress;
                this.renderProperties.billingAddress = billingAddress;

                quote.totals.subscribe(this.reRender, this);
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
                if (baseGrandTotal !== this.renderProperties.quoteBaseGrandTotal) {
                    this.renderProperties.quoteBaseGrandTotal = baseGrandTotal;
                    hasToReRender = true;
                }

                if (shippingMethodCode !== this.renderProperties.shippingMethod) {
                    this.renderProperties.shippingMethod = shippingMethodCode;
                    hasToReRender = true;
                }

                if (shippingAddress !== this.renderProperties.shippingAddress) {
                    this.renderProperties.shippingAddress = shippingAddress;
                    hasToReRender = true;
                }

                var strBillingAddr = JSON.stringify(quote.billingAddress());
                if(strBillingAddr !== this.renderProperties.billingAddress ){
                    hasToReRender = true;
                }
                this.renderProperties.billingAddress = strBillingAddr;

                if(hasToReRender){
                    console.log('Re-render triggered');
                    this.loadCheckoutId();
                }
                
                    
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

               return  $.ajax({
                    type: 'POST',
                    url: self.getcreateOrderUrl(),
                    data: params,
                    async: true,
                    showLoader: true,
                    success: function (response) {
                        
                        self.checkoutId(response.checkout_id);
                        
                        if(self.checkoutId()){
                            self.renderizeEmbedForm();
                        }
                    },
                    error: function (res) {
                        console.error(res);                        
                    }
                });
            },

            renderizeEmbedForm: function(){
                console.log('integration', {
                    targetIFrame: '#conektaIframeContainer',
                    checkoutRequestId: this.checkoutId(),
                    publicKey: this.getPublicKey(),
                    paymentMethods: this.getPaymenMethods(),
                    options: {
                        theme: 'default'
                    },
                    onCreateTokenSucceeded: function (token) {
                        console.log('onCreateTokenSucceeded');
                    },
                    onCreateTokenError: function (error) {
                        console.log('onCreateTokenError');
                        console.error(error);
                    },
                    onFinalizePayment: function (event) {
                        self.iframOrderData(event);
                        self.beforePlaceOrder();
                        console.log("FinalizePayment payment");
                    }
                });

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
                        console.log('onCreateTokenSucceeded');
                    },
                    onCreateTokenError: function (error) {
                        console.log('onCreateTokenError');
                        console.error(error);
                    },
                    onFinalizePayment: function (event) {
                        self.iframOrderData(event);
                        self.beforePlaceOrder();
                        console.log("FinalizePayment payment");
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
                            'order_id': params.charge.order_id,
                            'txn_id': params.charge.id,
                            'cc_exp_year': this.creditCardExpYear(),
                            'cc_exp_month': this.creditCardExpMonth(),
                            'cc_bin': number.substring(0, 6),
                            'card_token': $("#" + this.getCode() + "_card_token").val(),
                            //'saved_card': this.selectedPaymentId(),
                            //'saved_card_later': this.isSaveCardEnable(),
                            'iframe_payment': true,
                        }
                    };
                    return data;
                }
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method': '',
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_bin': number.substring(0, 6),
                        'cc_last_4': number.substring(number.length-4, number.length),
                        'card_token': $("#" + this.getCode() + "_card_token").val(),
                        //'saved_card': this.selectedPaymentId(),
                        //'saved_card_later': this.isSaveCardEnable(),
                        'iframe_payment': false,
                        'order_id': '',
                        'txn_id': '',
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
                if (this.paymentsShowNewCardSection() === true) {
                    self.placeOrder();
                }
                var $form = $('#' + this.getCode() + '-form');
                if($form.validation() && $form.validation('isValid')) {
                    self.messageContainer.clear();

                    if (!this.validateMonthlyInstallments()) {
                        self.messageContainer.addErrorMessage({
                            message: "The amount required for monthly installments is not valid."
                        });
                    }

                    Conekta.setPublishableKey(this.getPublicKey());

                    var tokenParams = {
                        "card": {
                            "number": this.creditCardNumber(),
                            "name": this.getCustomerName(),
                            "exp_year": this.creditCardExpYear().replace(/ /g, ''),
                            "exp_month": this.creditCardExpMonth(),
                            "cvc": this.creditCardVerificationNumber(),
                            "address": this.assembleAddress()
                        }
                    };

                    Conekta.token.create(tokenParams, function(token){
                        $("#" + self.getCode() + "_card_token").val(token.id);
                        self.placeOrder();
                    }, function(error){
                        self.messageContainer.addErrorMessage({
                            message: error.message
                        });
                    });
                } else {
                    return $form.validation() && $form.validation('isValid');
                }
            },

            validate: function() {
                if(this.iframOrderData() !== '') {
                    return true;
                }

                if (this.paymentsShowNewCardSection() === true) {
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

            getCcYears: function () {
                return this.getMethodConfig().years;
            },

            getCcMonths: function () {
                return this.getMethodConfig().months;
            },

            hasVerification: function () {
                return this.getMethodConfig().hasVerification;
            },

            getCvvImageUrl: function () {
                return this.getMethodConfig().cvvImageUrl;
            },

            getCcAvailableTypes: function() {
                return this.getMethodConfig().availableTypes;
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
