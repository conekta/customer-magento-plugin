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
                    shippingAddress: ''
                },
            },
            
            getFormTemplate: function(){
                return 'Conekta_Payments/payment/embedform/form'
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'ChangeCard',
                        'SavedCardLater',
                        'isSaveCardEnable',
                        'paymentsShowNewCardSection',
                        'checkoutId',
                        'selectedPaymentId',
                        'isIframeLoaded',
                        'isVisiblePaymentButton',
                        'iframOrderData'
                ]);
                this.iframOrderData('');
                this.checkoutId('');
                if  (this.getCardList().length === 0){
                    this.paymentsShowNewCardSection(false);
                }
                
                quote.totals.subscribe(this.reRender, this);
                return this;
            },
            
            initialize: function() {
                var self = this;
                this._super();
            },
            
            reRender: function(total){
                var baseGrandTotal = quote.totals._latestValue.base_grand_total;
                var shippingMethodCode = quote.shippingMethod._latestValue.method_code;
                var shippingAddress = quote.shippingAddress._latestValue.getKey();
                
                
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

                if(hasToReRender && this.checkoutId()){
                    document.getElementById("conektaIframeContainer").innerHTML="";
                    this.getIframe();
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
                    showLoader: true,
                    async: true,
                    success: function (response) {
                        self.checkoutId(response.checkout_id);

                        if(!self.checkoutId){
                            self.messageContainer.clear();
                            self.messageContainer.addErrorMessage({
                                message: "El medio de pago seleccionado no puede utilizarse"
                            });
                        }
                        
                    },
                    error: function (res) {
                        console.error(res);                        
                    }
                });
            },

            getIframe: function() {
                const urlParams = new URLSearchParams(window.location.search);
                if ($('#conektaIframeContainer').length) {
                    this.loadCheckoutId();
                    var self = this;
                    var checkout_id = self.checkoutId();
                    if (checkout_id) {
                        window.ConektaCheckoutComponents.Integration({
                            targetIFrame: '#conektaIframeContainer',
                            checkoutRequestId: checkout_id,
                            publicKey: this.getPublicKey(),
                            paymentMethods: this.getPaymenMethods(),//['Card', 'Cash', 'BankTransfer'],
                            options: {
                                theme: 'default'
                            },
                            onCreateTokenSucceeded: function (token) {
                                console.log('onCreateTokenSucceeded');
                                console.log(token);
                            },
                            onCreateTokenError: function (error) {
                                console.log('onCreateTokenError');
                                console.log(error);
                            },
                            onFinalizePayment: function (event) {
                                self.iframOrderData(event);
                                self.beforePlaceOrder();
                                console.log("FinalizePayment payment");
                            }
                        });
                        $('#conektaIframeContainer').find('iframe').attr('data-cy', 'the-frame');
                    }
                }
                return true;
            },

            onSavedCardLaterChanged: function(newValue)
            {
                if(newValue){
                    this.isSaveCardEnable(true);
                }else{
                    this.isSaveCardEnable(false);
                }
            },
            /**
             * @param newValue
             */
            onSelectedCardChanged: function(newValue)
            {
                if (newValue === undefined){
                    this.paymentsShowNewCardSection('');
                    this.isVisiblePaymentButton(false);
                    this.selectedPaymentId('');
                    return;
                }

                if(newValue !== 'add_new_card') {
                    this.paymentsShowNewCardSection(true);
                    this.selectedPaymentId(newValue);
                    this.isVisiblePaymentButton(true);
                } else {
                    this.paymentsShowNewCardSection(false);
                    this.selectedPaymentId('');
                    this.isVisiblePaymentButton(false);
                }
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
                            'saved_card': this.selectedPaymentId(),
                            'saved_card_later': this.isSaveCardEnable(),
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
                        'saved_card': this.selectedPaymentId(),
                        'saved_card_later': this.isSaveCardEnable(),
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
            getSaveCardEnable: function() {
                return this.getMethodConfig().enable_saved_card;
            },

            getSavedCards: function() {
                return this.getMethodConfig().saved_card;
            },

            getCardList: function() {
                return _.map(this.getSavedCards(), function(value, key) {
                    return {
                        'value': key,
                        'type': value
                    }
                });
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

            getMonthlyInstallments: function() {
                var months = [];
                var i = 0;
                for (i in this.getMethodConfig().monthly_installments){
                    /*switch (this.getMethodConfig().monthly_installments[i]){
                        case "6": case "9": case "12":
                            if (this.getTotal() < 400) {
                                continue;
                            }
                            break;
                    }*/

                    months.push(this.getMethodConfig().monthly_installments[i]);
                }
                return months.sort(function(a,b){ return a - b; });
            },

            validateMonthlyInstallments: function() {
                if(this.activeMonthlyInstallments() && isNaN(installments) == false) {
                    var totalOrder = this.getTotal();
                    if (totalOrder >= this.getMinimumAmountMonthlyInstallments()) {
                        var installments = parseInt($('#' + this.getCode() + '_monthly_installments').val());
                        if (installments == 1) {
                            return true;
                        } else {

                            return (installments * 100 < totalOrder);
                        }
                    } else {

                        return false;
                    }
                }

                return true;
            },

            getTotal: function(){
                return parseFloat(this.getMethodConfig().total);
            },

            getCustomerName: function(){
                return $("#" + this.getCode() + "_card_holder_name").val();
            },

            assembleAddress: function() {
                var address = {};
                for (var i in window.customerData.addresses) {
                    if (window.customerData.addresses[i].default_billing) {
                        var addressData = window.customerData.addresses[i];

                        if(typeof addressData.street[0] !== "undefined"){
                            if (addressData.street[0] !== null && addressData.street[0] !== ""){
                                address.street1 = addressData.street[0];
                            }
                        }

                        if(typeof addressData.street[1] !== "undefined"){
                            if (addressData.street[1] !== null && addressData.street[1] !== ""){
                                address.street2 = addressData.street[0];
                            }
                        }

                        address.city = addressData.city;
                        address.state = addressData.region.region;
                        address.zip = addressData.postcode;
                        address.country = addressData.country_id;
                    }
                }

                return address;
            }

        });
    }
);
