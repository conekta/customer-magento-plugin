define(
    [
        'ko',
        'conekta',
        'conektaCheckout',
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'mage/storage',
        'uiRegistry',
        'domReady!',
        'Magento_Checkout/js/model/shipping-save-processor',
        'Magento_Checkout/js/action/set-billing-address',
    ],
    function (ko, CONEKTA, conektaCheckout, Component, $, quote, customer, validator, storage, uiRegistry, domRe, shSP, sBA) {
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
                    guestEmail: '',
                    isLoggedIn: '',
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
                        'conektaError',
                        'isFormLoading'
                ]);
                this.iframOrderData('');
                this.checkoutId('');
                this.conektaError(null);
                this.isFormLoading(false);
                
                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                
                var shippingAddress = '';
                if(quote.shippingAddress())
                    shippingAddress = JSON.stringify(quote.shippingAddress());

                var billingAddress = '';
                if(quote.billingAddress())
                    billingAddress = JSON.stringify(quote.billingAddress());

                var shippingMethodCode = '';
                if(quote.shippingMethod._latestValue){
                    shippingMethodCode = quote.shippingMethod._latestValue.method_code;
                }
                
                this.renderProperties.quoteBaseGrandTotal = baseGrandTotal;
                this.renderProperties.shippingMethod = shippingMethodCode;
                this.renderProperties.shippingAddress = shippingAddress;
                this.renderProperties.billingAddress = billingAddress;
                this.renderProperties.guestEmail = quote.guestEmail;
                this.renderProperties.isLoggedIn = customer.isLoggedIn();
                
                //Suscriptions to re-render
                quote.totals.subscribe(this.reRender, this);
                quote.billingAddress.subscribe(this.billingAddressChanges, this);
                customer.isLoggedIn.subscribe(this.reRender, this);
                uiRegistry
                    .get('checkout.steps.billing-step.payment.customer-email')
                    .email
                    .subscribe(this.reRender, this);
                
                return this;
            },
            
            initialize: function() {
                var self = this;
                this._super();
                if (customer.isLoggedIn() && 
                    quote.isVirtual() && 
                    quote.billingAddress()
                ){
                    $.when(sBA()).then(this.initializeForm());    
                } else {
                    this.initializeForm();
                } 
                
            },

            initializeForm: function(){

                //if doesn't rendered yet, then tries to render
                if(!this.reRender()){
                    
                    this.isFormLoading(true);
                    this.loadCheckoutId();
                }
            },

            billingAddressChanges: function() {
                var self = this;
                
                //if no billing info, then form is editing
                if(!quote.billingAddress()){
                    self.reRender();
                    
                } else if (!quote.isVirtual()) {
                    self.isFormLoading(false);
                    shSP.saveShippingInformation()
                        .done(function(){
                            self.reRender();
                        });
                }
                
            },

            reRender: function(total){
                
                if(this.isFormLoading())
                    return;

                this.isFormLoading(true);
                
                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                
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
                var shippingAddress = '';
                if(quote.shippingAddress()) shippingAddress = JSON.stringify(quote.shippingAddress());
                if (shippingAddress !== this.renderProperties.shippingAddress) {
                    hasToReRender = true;
                }
                this.renderProperties.shippingAddress = shippingAddress;

                //check for billing changes
                var quoteBilling = quote.billingAddress();
                var strBillingAddr = quoteBilling? JSON.stringify(quote.billingAddress()) : '';
                if(strBillingAddr !== this.renderProperties.billingAddress ){
                    hasToReRender = true;
                }
                this.renderProperties.billingAddress = strBillingAddr;
                                
                var actuaGuestEmail = quote.guestEmail;
                if (!customer.isLoggedIn() && 
                    quote.isVirtual()
                ){
                
                    //If is virtual, guest mail guets from uiregistry
                    actuaGuestEmail = uiRegistry.get('checkout.steps.billing-step.payment.customer-email').email();
                    
                    //check for guest email changes on virtual cart
                    if(actuaGuestEmail !== this.renderProperties.guestEmail) {
                        hasToReRender = true;
                    }
                }
                this.renderProperties.guestEmail = actuaGuestEmail;

                //Check if customer is logged in changes
                if(customer.isLoggedIn() !== this.renderProperties.isLoggedIn){
                    hasToReRender = true;
                }
                this.renderProperties.isLoggedIn = customer.isLoggedIn();
                
                if (hasToReRender) {
                    this.loadCheckoutId()

                } else {
                    this.isFormLoading(false);
                }

                return hasToReRender;
            },

            validateRenderEmbedForm: function(){
                var isValid = true;

                if (!this.renderProperties.billingAddress) {
                    this.conektaError('Información de Facturación: Complete todos los campos requeridos de para continuar');
                    return false;
                }

                if (!customer.isLoggedIn() && 
                    quote.isVirtual() && 
                    (!quote.guestEmail || (
                        this.renderProperties.guestEmail &&
                        this.renderProperties.guestEmail !== quote.guestEmail
                        )
                    )
                ) {
                    this.conektaError('Ingrese un email válido para continuar');
                    return false;
                }

                if (!customer.isLoggedIn() && 
                    !quote.isVirtual() && 
                    !quote.guestEmail
                ) {
                    this.conektaError('Ingrese un email válido para continuar');
                    return false;
                }
                
                return true;
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
                    $.ajax({
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
                            }else{
                                self.isFormLoading(false);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error(status);
                            self.conektaError(xhr.responseJSON.error_message);
                            self.isFormLoading(false);
                        }
                    })
                }else{
                    this.isFormLoading(false);
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
                self.isFormLoading(false);
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
