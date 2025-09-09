<?php
namespace Conekta\Payments\Block\Bnpl;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class BnplInfo extends Info
{
    /**
     * @var Config
     */
    protected Config $_paymentConfig;

    /**
     * @var string
     */
    protected $_template = 'Conekta_Payments::info/bnpl.phtml';

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
     * Get BNPL data
     *
     * @return false|mixed
     * @throws LocalizedException
     */
    public function getDataBnpl()
    {
        $additional_data = $this->getAdditionalData();
        if (isset($additional_data['offline_info']['data'])) {
            return $additional_data['offline_info']['data'];
        }

        return false;
    }

    /**
     * Get additional data
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getAdditionalData()
    {
        return $this->getInfo()->getAdditionalInformation();
    }

    /**
     * Check if payment method is BNPL
     *
     * @return bool
     */
    public function isBnplPaymentMethod(): bool
    {
        return $this->getInfo()->getMethod() === 'conekta_bnpl';
    }

    /**
     * Get BNPL payment instructions
     *
     * @return string
     */
    public function getInstructions(): string
    {
        return $this->getMethod()->getConfigData('instructions') ?? '';
    }
}
