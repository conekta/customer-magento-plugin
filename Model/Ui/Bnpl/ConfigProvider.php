<?php
namespace Conekta\Payments\Model\Ui\Bnpl;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'conekta_bnpl';
    /**
     * @var Session
     */
    protected $_checkoutSession;
    /**
     * @var Repository
     */
    protected $_assetRepository;
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;

    /**
     * @param Session $checkoutSession
     * @param Repository $assetRepository
     * @param ConektaHelper $conektaHelper
     */
    public function __construct(
        Session $checkoutSession,
        Repository $assetRepository,
        ConektaHelper $conektaHelper
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_assetRepository = $assetRepository;
        $this->_conektaHelper = $conektaHelper;
    }

    /**
     * Get config
     *
     * @return \array[][]
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'total' => $this->getQuote()->getGrandTotal(),
                    'logo' => $this->getLogo(),
                    'is_active' => $this->_conektaHelper->isBnplEnabled()
                ]
            ]
        ];
    }

    /**
     * Get quote
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    /**
     * Get logo URL
     *
     * @return string
     */
    public function getLogo()
    {
        return $this->_assetRepository->getUrl('Conekta_Payments::images/conekta.svg');
    }
}
