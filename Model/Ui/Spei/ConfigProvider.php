<?php
namespace Conekta\Payments\Model\Ui\Spei;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'conekta_spei';

    protected $_checkoutSession;

    protected $_assetRepository;

    protected $_conektaHelper;

    public function __construct(
        Session $checkoutSession,
        Repository $assetRepository,
        ConektaHelper $conektaHelper
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_assetRepository = $assetRepository;
        $this->_conektaHelper = $conektaHelper;
    }

    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'total' => $this->getQuote()->getGrandTotal()
                ]
            ]
        ];
    }

    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }
}
