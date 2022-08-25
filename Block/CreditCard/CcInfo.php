<?php
namespace Conekta\Payments\Block\CreditCard;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;

/**
 * @property Config $_paymentConfig
 */
class CcInfo extends Info
{
    /**
     * @var Config
     */
    protected $_paymentConfig;

    /**
     * @var string
     */
    protected $_template = 'Conekta_Payments::info/creditcard.phtml';

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
     * Get Additional Data
     *
     * @return false|mixed
     * @throws LocalizedException
     */
    public function getAdditionalData()
    {
        $information = $this->getInfo()->getAdditionalInformation();
        if (isset($information['additional_data'])) {
            return $information['additional_data'];
        }

        return false;
    }
}
