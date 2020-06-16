define(
    [
        'conekta',
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (CONEKTA, Component, $, quote, customer, validator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Conekta_Payments/payment/base-form',
                transactionResult: ''
            },

            getFormTemplate: function(){
                return 'Conekta_Payments/payment/creditcard/form'
            },

            initialize: function() {
                var self = this;
                this._super();
            },

            getData: function () {
                var number = this.creditCardNumber().replace(/\D/g,'');
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_bin': number.substring(0, 6),
                        'cc_last_4': number.substring(number.length-4, number.length),
                        'card_token': $("#" + this.getCode() + "_card_token").val()
                    }
                };

                if (this.activeMonthlyInstallments()) {
                    data['additional_data']['monthly_installments'] = $("#" + this.getCode() + "_monthly_installments").children("option:selected").val();
                }

                return data;
            },

            beforePlaceOrder: function () {
                var $form = $('#' + this.getCode() + '-form');
                var self = this;

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
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getCode: function() {
                return 'conekta_cc';
            },

            isActive: function() {
                return true;
            },

            getGlobalConfig: function() {
                return window.checkoutConfig.payment.conekta_global
            },

            getMethodConfig: function() {
                return window.checkoutConfig.payment.conekta_cc
            },

            getPublicKey: function() {
                return this.getGlobalConfig().publicKey;
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
