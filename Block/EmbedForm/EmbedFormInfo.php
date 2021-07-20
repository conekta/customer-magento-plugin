<?php
namespace Conekta\Payments\Block\EmbedForm;

use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class EmbedFormInfo extends Info
{
    protected $_paymentConfig;

    protected $_template = 'Conekta_Payments::info/embedform.phtml';

    public function __construct(
        Context $context,
        Config $paymentConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_paymentConfig = $paymentConfig;
    }

    public function getCcTypeName()
    {
        $types = $this->_paymentConfig->getCcTypes();
        $ccType = $this->getInfo()->getCcType();
        if (isset($types[$ccType])) {
            return $types[$ccType];
        }
        return empty($ccType) ? __('N/A') : $ccType;
    }

    public function getAdditionalData()
    {
        return $this->getInfo()->getAdditionalInformation();
    }

    public function getOfflineInfo()
    {
        $additional_data = $this->getAdditionalData();
        if (isset($additional_data['offline_info']['data'])) {
            return $additional_data['offline_info']['data'];
        }

        return false;
    }

    public function getPaymentMethodType()
    {
        return $this->getInfo()->getAdditionalInformation('payment_method');
    }

    public function getPaymentMethodTitle()
    {
        $methodType = $this->getPaymentMethodType();
        $title = '';

        switch ($methodType) {
            case ConfigProvider::PAYMENT_METHOD_CREDIT_CARD:
                $title = 'Tarjeta de Crédito';
                break;
            
            case ConfigProvider::PAYMENT_METHOD_OXXO:
                $title = 'Pago en Efectivo con Oxxo';
                break;
            case ConfigProvider::PAYMENT_METHOD_SPEI:
                $title = 'Transferencia SPEI';
                break;
        }

        return $title;
    }

    public function isCreditCardPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_CREDIT_CARD;
    }

    public function isOxxoPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_OXXO;
    }

    public function isSpeiPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_SPEI;
    }
}
