<?php
namespace Conekta\Payments\Block\EmbedForm;

use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class EmbedFormInfo extends Info
{
    /**
     * @var Config
     */
    protected $_paymentConfig;

    /**
     * @var string
     */
    protected $_template = 'Conekta_Payments::info/embedform.phtml';

    /**
     * @param Context $context
     * @param Config $paymentConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $paymentConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_paymentConfig = $paymentConfig;
    }

    /**
     * Get CC type name
     *
     * @return Phrase|mixed
     * @throws LocalizedException
     */
    public function getCcTypeName()
    {
        $types = $this->_paymentConfig->getCcTypes();
        $ccType = $this->getInfo()->getCcType();
        if (isset($types[$ccType])) {
            return $types[$ccType];
        }
        return empty($ccType) ? __('N/A') : $ccType;
    }

    /**
     * Get additional Data
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getAdditionalData()
    {
        return $this->getInfo()->getAdditionalInformation();
    }

    /**
     * Get off line info
     *
     * @return false|mixed
     * @throws LocalizedException
     */
    public function getOfflineInfo()
    {
        $additional_data = $this->getAdditionalData();
        if (isset($additional_data['offline_info']['data'])) {
            return $additional_data['offline_info']['data'];
        }

        return false;
    }

    /**
     * Get payment method type
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getPaymentMethodType()
    {
        return $this->getInfo()->getAdditionalInformation('payment_method');
    }

    /**
     * Get payment method title
     *
     * @return string
     * @throws LocalizedException
     */
    public function getPaymentMethodTitle()
    {
        $methodType = $this->getPaymentMethodType();
        $title = $this->getPaymentMethodType();

        switch ($methodType) {
            case ConfigProvider::PAYMENT_METHOD_CREDIT_CARD:
                $title = 'Tarjeta de Crédito';
                break;
            
            case ConfigProvider::PAYMENT_METHOD_CASH:
                $title = 'Pago en Efectivo';
                break;
            case ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER:
                $title = 'Transferencia Bancaria';
                break;
        }

        return $title;
    }

    /**
     * Is credit card payment method
     *
     * @return bool
     * @throws LocalizedException
     */
    public function isCreditCardPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_CREDIT_CARD;
    }

    /**
     * Is cash payment method
     *
     * @return bool
     * @throws LocalizedException
     */
    public function isCashPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_CASH;
    }

    /**
     * Is BankTransfer payment method
     *
     * @return bool
     * @throws LocalizedException
     */
    public function isBankTransferPaymentMethod()
    {
        return $this->getPaymentMethodType() === ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER;
    }

    /**
     * Show if card is debit o credit card
     *
     * @return string
     * @throws LocalizedException
     */
    public function getCardType()
    {
        return $this->getInfo()->getAdditionalInformation('C_TYPE');
    }
}
