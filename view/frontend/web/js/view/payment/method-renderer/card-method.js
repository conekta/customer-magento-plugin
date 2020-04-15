/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $, quote, customer, validator) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Conekta_Payments/payment/card-form'
            },

            getCode: function() {
                return 'conekta_card';
            },

            isActive: function() {
                return true;
            },
            sendPay: function () {
                var $form = $('#' + this.getCode() + '-form');
                var self = this;
                
                if($form.validation() && $form.validation('isValid')) {
                    self.messageContainer.clear();

                    if (!this.validateMonthlyInstallments()) {
                        self.messageContainer.addErrorMessage({
                            message: "The amount required for monthly installments is not valid."
                        });
                    }

                    Conekta.setPublishableKey(window.checkoutConfig.payment.conekta.public_key);

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
                        $("#card_token").val(token.id);
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
                        'card_token': $("#card_token").val()
                    }
                };

                if (this.activeMonthlyInstallments()) {
                    data['additional_data']['monthly_installments'] = $('#conekta_monthly_installments').val();
                }

                return data;
            },
            getCustomerName: function(){
                return $("#card_holder_name").val();
            },
            getMonthlyInstallments: function() {
                var months = [];
                var i = 0;
                
                for (i in window.checkoutConfig.payment.conekta.monthly_installments){
                    switch (window.checkoutConfig.payment.conekta.monthly_installments[i]){
                        case "6": case "9": case "12":
                            if (this.getTotal() < 400) {
                                continue;
                            } 
                            break;
                    }
                    
                    months.push(window.checkoutConfig.payment.conekta.monthly_installments[i]);
                }

                return months.sort(function(a,b){ return a - b; });
            },
            
            activeMonthlyInstallments: function() {
                return window.checkoutConfig.payment.conekta.active_monthly_installments;
            },

            getMinimumAmountMonthlyInstallments: function() {
                return window.checkoutConfig.payment.conekta.minimum_amount_monthly_installments;
            },
            
            getTotal: function(){
                return parseFloat(window.checkoutConfig.payment.total);
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            validateMonthlyInstallments: function() {
                if(this.activeMonthlyInstallments() && isNaN(installments) == false) {
                    var totalOrder = this.getTotal();
                    if (totalOrder >= this.getMinimumAmountMonthlyInstallments()) {
                        var installments = parseInt($('#conekta_monthly_installments').val());
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