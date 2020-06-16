<?php
namespace Conekta\Payments\Model\Ui;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'conekta_global';

    protected $_conektaHelper;

    private $assetRepository;

    public function __construct(
        ConektaHelper $conektaHelper,
        AssetRepository $assetRepository
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_assetRepository = $assetRepository;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'publicKey' => $this->_conektaHelper->getPublicKey(),
                    'conekta_logo' => $this->_assetRepository->getUrl('Conekta_Payments::images/conekta.svg')
                ]
            ]
        ];
    }
}
