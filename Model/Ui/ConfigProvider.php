<?php

namespace Conekta\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Conekta\Payments\Model\Card as ConektaPayment;
use Magento\Checkout\Model\Cart;

class ConfigProvider implements ConfigProviderInterface 
{
    /**
     * @var array[]
     */
    protected $methodCodes = [
        'conekta_card'     
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    
    /**
     * @var \Conekta\Card\Model\Payment
     */
    protected $_payment;

    /**
    * @var Magento\Checkout\Model\Cart
    */
    protected $_cart;

    /**
    * @var
    */
    private $_config = [];


    /**     
     * @param PaymentHelper $paymentHelper
     * @param \Conekta\Card\Model\Payment $payment
     */
    public function __construct(PaymentHelper $paymentHelper, ConektaPayment $payment, Cart $cart) {       
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }

        $this->_cart = $cart;
        $this->_payment = $payment;
    }

    /**
    * Magic method
    * @return mixed
    */
    public function __get($name)
    {
        if (true === property_exists($this, $name)) {
            return $this->$name;
        }

        return false;
    }

    /**
    * Magic method
    * @return object \Conekta\Card\Model\ConfigProvider
    */
    public function __set($name, $value)
    {
        if (true == property_exists($this, $name)) {
            $this->$name = $value;
            return $this;
        }

        return false;
    }

    /**
     * Set config template form need
     * @return object \Conekta\Model\ConfigProvider
     */
    public function setConfig()
    {                
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['conekta']['public_key'] = $this->_payment->getPubishableKey();

                $config['payment']['ccform']["availableTypes"][$code] = $this->_payment->getActiveTypeCards();

                $config['payment']['conekta']['active_monthly_installments'] = $this->_payment->isActiveMonthlyInstallments();
                if ($config['payment']['conekta']['active_monthly_installments']){
                    $config['payment']['conekta']['monthly_installments'] = $this->_payment->getMonthlyInstallments();
                    $config['payment']['conekta']['minimum_amount_monthly_installments'] = $this->_payment->getMinimumAmountMonthlyInstallments();
                }
                
                $config['payment']['total'] = $this->_cart->getQuote()->getGrandTotal();
 
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->_getMonths();
                $config['payment']['ccform']["years"][$code] = $this->_getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = "https://www.ekwb.com/shop/skin/frontend/base/default/images/cvv.gif";
                $config['payment']['ccform']["ssStartYears"][$code] = $this->_getStartYears();
            }
        }
                
        return $this->__set('_config', $config);
    }

    /**
    * Get al config template form need
    * @return array
    */
    public function getConfig()
    {
        return $this->setConfig()->__get("_config");
    }

    /**
    * Return list of months for html template
    * @return array
    */
    private function _getMonths(){
        return [
            "1" => "01 - Enero",
            "2" => "02 - Febrero",
            "3" => "03 - Marzo",
            "4" => "04 - Abril",
            "5" => "05 - Mayo",
            "6" => "06 - Junio",
            "7" => "07 - Julio",
            "8" => "08 - Augosto",
            "9" => "09 - Septiembre",
            "10" => "10 - Octubre",
            "11" => "11 - Noviembre",
            "12" => "12 - Diciembre"
        ];
    }
    
    /**
    * List year avaibles for html template
    * @return array
    */
    private function _getYears()
    {
        $years = [];
        $cYear = (integer) date("Y");
        $cYear = $cYear - 1;
        for($i=1; $i <= 8; $i++) {
            $year = (string) ($cYear + $i);
            $years[$year] = $year;
        }

        return $years;
    }
    
    /**
    * @return array
    */
    private function _getStartYears()
    {
        $years = [];
        $cYear = (integer) date("Y");

        for($i=5; $i>=0; $i--) {
            $year = (string)($cYear - $i);
            $years[$year] = $year;
        }

        return $years;
    }
}