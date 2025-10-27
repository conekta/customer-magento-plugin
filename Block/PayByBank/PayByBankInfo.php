<?php
namespace Conekta\Payments\Block\PayByBank;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class PayByBankInfo extends Info
{
    /**
     * @var Config
     */
    protected Config $_paymentConfig;

    /**
     * @var string
     */
    protected $_template = 'Conekta_Payments::info/paybybank.phtml';

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
     * Get Pay By Bank data
     *
     * @return false|mixed
     * @throws LocalizedException
     */
    public function getDataPayByBank()
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
     * Check if payment method is Pay By Bank
     *
     * @return bool
     */
    public function isPayByBankPaymentMethod(): bool
    {
        return $this->getInfo()->getMethod() === 'conekta_pay_by_bank';
    }

    /**
     * Get Pay By Bank payment instructions
     *
     * @return string
     */
    public function getInstructions(): string
    {
        return $this->getMethod()->getConfigData('instructions') ?? '';
    }

    /**
     * Check if user is on mobile device
     *
     * @return bool
     */
    public function isMobileDevice(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
    }
}

