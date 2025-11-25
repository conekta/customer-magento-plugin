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
        'Magento_Checkout/js/model/cart/totals-processor/default',
        'Magento_Checkout/js/model/cart/cache'
    ],
    function (ko, CONEKTA, conektaCheckout, Component, $, quote, customer, validator, storage, uiRegistry, domRe, shSP, sBA, totalsProcessor, cartCache) {
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
            shouldDelaySuccessRedirect: false,
            payByBankRedirectDelay: 60000,

            getFormTemplate: function () {
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
                if (quote.shippingAddress())
                    shippingAddress = JSON.stringify(quote.shippingAddress());

                var billingAddress = '';
                if (quote.billingAddress())
                    billingAddress = JSON.stringify(quote.billingAddress());

                var shippingMethodCode = '';
                if (quote.shippingMethod._latestValue) {
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

            initialize: function () {
                var self = this;
                this._super();
                if (customer.isLoggedIn() &&
                    quote.isVirtual() &&
                    quote.billingAddress()
                ) {
                    $.when(sBA()).then(this.initializeForm());
                } else {
                    this.initializeForm();
                }

            },

            initializeForm: function () {

                //if doesn't rendered yet, then tries to render
                if (!this.reRender()) {

                    this.isFormLoading(true);
                    this.loadCheckoutId();
                }
            },

            billingAddressChanges: function () {
                var self = this;

                //if no billing info, then form is editing
                if (!quote.billingAddress()) {
                    self.reRender();

                } else if (!quote.isVirtual()) {
                    self.isFormLoading(false);
                    try {
                        shSP.saveShippingInformation()
                            .done(function () {
                                self.reRender();
                            });
                    } catch (error) {
                        self.reRender();
                    }
                }

            },

            reRender: function (total) {

                if (this.isFormLoading())
                    return;

                this.isFormLoading(true);

                var hasToReRender = false;

                if (quote.shippingMethod._latestValue && !this.isEmpty(quote.shippingMethod._latestValue)
                    && quote.shippingMethod._latestValue.method_code !== undefined
                    && quote.shippingMethod._latestValue.method_code !== this.renderProperties.shippingMethod) {
                    //check for shipping methods changes
                    this.renderProperties.shippingMethod = quote.shippingMethod._latestValue.method_code;
                    hasToReRender = true;
                }

                //check for total changes
                if (quote.totals._latestValue.base_grand_total !== this.renderProperties.quoteBaseGrandTotal) {
                    this.renderProperties.quoteBaseGrandTotal = quote.totals._latestValue.base_grand_total;
                    hasToReRender = true;
                }

                //check for shipping changes
                if (quote.shippingAddress()) {
                    const shippingAddress = JSON.stringify(quote.shippingAddress());
                    if (shippingAddress !== this.renderProperties.shippingAddress) {
                        this.renderProperties.shippingAddress = shippingAddress;
                        hasToReRender = true;
                    }
                }


                //check for billing changes
                if(quote.billingAddress()) {
                    const quoteBilling = JSON.stringify(quote.billingAddress());
                    if (quoteBilling !== this.renderProperties.billingAddress) {
                        this.renderProperties.billingAddress = quoteBilling;
                        hasToReRender = true;
                    }
                }


                if (!customer.isLoggedIn() && quote.isVirtual()) {
                    let currentGuestEmail = quote.guestEmail;

                    //If is virtual, guest mail gets from uiregistry
                    currentGuestEmail = uiRegistry.get('checkout.steps.billing-step.payment.customer-email').email();

                    //check for guest email changes on virtual cart
                    if (currentGuestEmail !== this.renderProperties.guestEmail) {
                        this.renderProperties.guestEmail = currentGuestEmail;
                        hasToReRender = true;
                    }
                }

                //Check if customer is logged in changes
                if (customer.isLoggedIn() !== this.renderProperties.isLoggedIn) {
                    this.renderProperties.isLoggedIn = customer.isLoggedIn();
                    hasToReRender = true;
                }

                if (hasToReRender) {
                    this.loadCheckoutId()

                } else {
                    this.isFormLoading(false);
                }

                return hasToReRender;
            },

            validateRenderEmbedForm: function () {
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

            loadCheckoutId: function () {
                var self = this;
                var guest_email = '';
                if (this.isLoggedIn() === false) {
                    guest_email = quote.guestEmail;
                }
                var params = {
                    'guestEmail': guest_email
                };

                if (this.validateRenderEmbedForm()) {
                    this.validateCheckoutSession()
                    $.ajax({
                        type: 'POST',
                        url: self.getcreateOrderUrl(),
                        data: params,
                        async: true,
                        showLoader: true,
                        success: function (response) {
                            self.conektaError(null);
                            self.checkoutId(response.checkout_id);

                            if (self.checkoutId()) {
                                self.renderizeEmbedFormTimes = 0
                                self.renderizeEmbedForm();
                            } else {
                                self.isFormLoading(false);
                            }
                        },
                        error: function (xhr, status, error) {
                            self.conektaError(xhr.responseJSON.error_message);
                            self.isFormLoading(false);
                        }
                    })
                } else {
                    this.isFormLoading(false);
                }

            },

            renderizeEmbedForm: function () {
                var self = this;
                try {
                    document.getElementById("conektaIframeContainer").innerHTML = "";
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
                        },
                        onFinalizePayment: function (event) {
                            self.iframOrderData(event);
                            self.beforePlaceOrder();
                        },
                        onErrorPayment: function(a) {
                            self.conektaError("Ocurrió un error al procesar el pago. Por favor, inténtalo de nuevo.");
                        },
                        onPayByBankWaitingPay: function(data) {
                            var provider = data.provider || 'bbva';
                            var redirectUrl = data.redirectUrl || '';
                            var deepLink = data.deepLink || '';
                            var reference = data.reference || '';
                            self.shouldDelaySuccessRedirect = true;
                            
                            try {
                                localStorage.setItem('conekta_pbb_data', JSON.stringify({
                                    type: 'pay_by_bank',
                                    redirect_url: redirectUrl,
                                    deep_link: deepLink,
                                    reference: reference,
                                    provider: provider,
                                    timestamp: Date.now()
                                }));
                            } catch (e) {}
                            
                            var payByBankEvent = {
                                charge: {
                                    id: 'pending',
                                    order_id: 'pending',
                                    payment_method: {
                                        type: 'pay_by_bank',
                                        brand: provider,
                                        last4: '0000',
                                        card_type: 'debit',
                                        redirect_url: redirectUrl,
                                        deep_link: deepLink,
                                        reference: reference
                                    }
                                }
                            };
                            
                            var userAgent = window.navigator.userAgent || '';
                            var isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent);
                            var targetUrl = (isMobileDevice ? deepLink : redirectUrl) || 'about:blank';
                            var popupWindow = null;
                            var popupName = 'conektaPayByBank_' + Date.now();
                            var popupFeatures = 'width=900,height=900,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes';
                            
                            if (isMobileDevice) {
                                window.location.href = targetUrl;
                            } else {
                                try {
                                    popupWindow = window.open('', popupName, popupFeatures);
                                    if (popupWindow) {
                                        popupWindow.location = targetUrl;
                                    } else {
                                        window.location.href = targetUrl;
                                    }
                                } catch (e) {
                                    window.location.href = targetUrl;
                                }
                            }
                            
                            self.iframOrderData(payByBankEvent);
                            self.beforePlaceOrder();
                        }
                    });

                    $('#conektaIframeContainer').find('iframe').attr('data-cy', 'the-frame');
                    self.isFormLoading(false);
                } catch {
                    if(self.renderizeEmbedFormTimes > 4) 
                        return self.isFormLoading(false);

                    self.renderizeEmbedFormTimes++;
                    setTimeout(function() { 
                        self.renderizeEmbedForm();
                    }, 500);
                }
            },

            getData: function () {
                var number = this.creditCardNumber().replace(/\D/g, '');
                if (this.iframOrderData() !== '') {
                    var params = this.iframOrderData();
                    var data = {
                        'method': this.getCode(),
                        'additional_data': {
                            'payment_method': params.charge.payment_method.type,
                            'cc_type': params.charge.payment_method.brand,
                            'cc_last_4': params.charge.payment_method.last4,
                            'order_id': params.charge.order_id || params.id,
                            'txn_id': params.charge.id,
                            'card_type': params.charge.payment_method.card_type,
                            'card_token': $("#" + this.getCode() + "_card_token").val(),
                            'iframe_payment': true,
                            'redirect_url': params.charge.payment_method.redirect_url || '',
                            'deep_link': params.charge.payment_method.deep_link || '',
                            'reference': params.charge.payment_method.reference || ''
                        }
                    };
                    return data;
                }
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'payment_method': '',
                        'cc_type': this.creditCardType(),
                        'cc_last_4': number.substring(number.length - 4, number.length),
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
                if (this.iframOrderData() !== '') {
                    return self.placeOrder();
                }
            },

            afterPlaceOrder: function () {
                var self = this;
                if (this.shouldDelaySuccessRedirect) {
                    setTimeout(function () {
                        self.redirectToSuccessPage();
                    }, this.payByBankRedirectDelay);
                    this.shouldDelaySuccessRedirect = false;
                } else {
                    return this._super();
                }
            },

            validate: function () {
                if (this.iframOrderData() !== '') {
                    return true;
                }

                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getCode: function () {
                return 'conekta_ef';
            },

            isActive: function () {
                return true;
            },

            getGlobalConfig: function () {
                return window.checkoutConfig.payment.conekta_global
            },

            getMethodConfig: function () {
                return window.checkoutConfig.payment.conekta_ef
            },

            getPublicKey: function () {
                return this.getGlobalConfig().publicKey;
            },

            getPaymenMethods: function () {
                return this.getMethodConfig().paymentMethods;
            },

            getConektaLogo: function () {
                return this.getGlobalConfig().conekta_logo;
            },

            getcreateOrderUrl: function () {
                return this.getMethodConfig().createOrderUrl;
            },

            isLoggedIn: function () {
                return customer.isLoggedIn();
            },

            activeMonthlyInstallments: function () {
                return this.getMethodConfig().active_monthly_installments;
            },

            getMinimumAmountMonthlyInstallments: function () {
                return this.getMethodConfig().minimum_amount_monthly_installments;
            },
            validateCheckoutSession: function () {
                const lifeTime = parseInt(this.getMethodConfig().sessionExpirationTime)
                const timeToExpire = (lifeTime - 5) * 1000
                setTimeout(()=> {
                    document.getElementById("conektaIframeContainer").innerHTML = `<div style="width: 100%; text-align: center;"><p>La sesión a finalizado por 
                    favor actualice la pagina</p> <button onclick="window.location.reload()" class="button action continue primary">Actualizar</button></body></div>`;
                }, timeToExpire)
            },

            isEmpty: function (obj) {
                return obj === undefined || obj === null || obj === ''
            }
        });
    }
);
