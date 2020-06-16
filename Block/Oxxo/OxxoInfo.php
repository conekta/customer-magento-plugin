<?php
namespace Conekta\Payments\Block\Oxxo;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Payment\Model\Config;

class OxxoInfo extends Info
{
    protected $_paymentConfig;

    protected $_template = 'Conekta_Payments::info/oxxo.phtml';

    public function __construct(
        Context $context,
        Config $paymentConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_paymentConfig = $paymentConfig;
    }

    public function getDataOxxo()
    {
        $additional_data = $this->getAdditionalData();
        if (isset($additional_data['offline_info']['data'])) {
            return $additional_data['offline_info']['data'];
        }

        return false;
    }

    public function getAdditionalData()
    {
        return $this->getInfo()->getAdditionalInformation();
    }
}
