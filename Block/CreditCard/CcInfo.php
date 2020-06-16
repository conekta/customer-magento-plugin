<?php
namespace Conekta\Payments\Block\CreditCard;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class CcInfo extends Info
{
    protected $_paymentConfig;

    protected $_template = 'Conekta_Payments::info/creditcard.phtml';

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
        $information = $this->getInfo()->getAdditionalInformation();
        if (isset($information['additional_data'])) {
            return $information['additional_data'];
        }

        return false;
    }
}
