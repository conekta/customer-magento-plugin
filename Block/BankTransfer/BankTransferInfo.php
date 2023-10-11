<?php
namespace Conekta\Payments\Block\BankTransfer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class BankTransferInfo extends Info
{
    /**
     * @var Config
     */
    protected Config $_paymentConfig;

    /**
     * @var string
     */
    protected $_template = 'Conekta_Payments::info/bankTransfer.phtml';

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
     * Get data BankTransfer
     *
     * @return false|mixed
     * @throws LocalizedException
     */
    public function getDataBankTransfer()
    {
        $additional_data = $this->getAdditionalData();
        if (isset($additional_data['offline_info']['data'])) {
            return $additional_data['offline_info']['data'];
        }

        return false;
    }

    /**
     * Get additional
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function getAdditionalData()
    {
        return $this->getInfo()->getAdditionalInformation();
    }
}
