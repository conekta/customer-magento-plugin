<?php
namespace Conekta\Payments\Model\Ui\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Payment method code
     */
    const CODE = 'conekta_ef';
    /**
     * Create Order Controller Path
     */
    const CREATEORDER_URL = 'conekta/index/createorder';
    /**
     * @var Repository
     */
    protected $_assetRepository;
    /**
     * @var CcConfig
     */
    protected $_ccCongig;
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;
    /**
     * @var Session
     */
    protected $_checkoutSession;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;
    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * ConfigProvider constructor.
     * @param Repository $assetRepository
     * @param CcConfig $ccCongig
     * @param ConektaHelper $conektaHelper
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param ConektaLogger $conektaLogger
     * @param UrlInterface $url
     */
    public function __construct(
        Repository $assetRepository,
        CcConfig $ccCongig,
        ConektaHelper $conektaHelper,
        Session $checkoutSession,
        CustomerSession $customerSession,
        Config $config,
        ConektaLogger $conektaLogger,
        UrlInterface $url
    ) {
        $this->_assetRepository = $assetRepository;
        $this->_ccCongig = $ccCongig;
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->conektaLogger = $conektaLogger;
        $this->url = $url;
    }

    /**
     * @return array|\array[][]
     */
    public function getConfig()
    {
        $savedCardEnable = $this->getEnableSaveCardConfig() ? true : false;
        return [
            'payment' => [
                self::CODE => [
                    'hasVerification' => true,
                    'monthly_installments' => $this->getMonthlyInstallments(),
                    'active_monthly_installments' => $this->getMonthlyInstallments(),
                    'minimum_amount_monthly_installments' => $this->getMinimumAmountMonthlyInstallments(),
                    'total' => $this->getQuote()->getGrandTotal(),
                    //'enable_saved_card' => $savedCardEnable,
                    //'saved_card' => $savedCardEnable ? $this->getSavedCard() : [],
                    'createOrderUrl' => $this->url->getUrl(self::CREATEORDER_URL),
                    'paymentMethods' => $this->getPaymentMethodsActive(),
                ]
            ]
        ];
    }

    /**
     * @return mixed
     */
    public function getEnableSaveCardConfig()
    {
        return $this->_conektaHelper->getConfigData('conekta/conekta_global', 'enable_saved_card');
    }

    /**
     * @return false|int[]|string[]
     */
    public function getMonthlyInstallments()
    {
        $total = $this->getQuote()->getGrandTotal();
        $months = [1];
        if ((int)$this->getMinimumAmountMonthlyInstallments() < (int)$total) {
            $months = explode(',', $this->_conektaHelper->getConfigData('conekta_cc', 'monthly_installments'));
            if (!in_array("1", $months)) {
                array_push($months, "1");
            }
            asort($months);
            foreach ($months as $k => $v) {
                if ((int)$total < ($v * 100)) {
                    unset($months[$k]);
                }
            }
        }
        return $months;
    }

    /**
     * @return mixed
     */
    public function getMinimumAmountMonthlyInstallments()
    {
        return $this->_conektaHelper->getConfigData('conekta_cc', 'minimum_amount_monthly_installments');
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    public function getPaymentMethodsActive()
    {
        $methods = [];

        if ($this->_conektaHelper->isCreditCardEnabled()) {
            $methods[] = 'Card';
        }
        if ($this->_conektaHelper->isOxxoEnabled()) {
            $methods[] = 'Cash';
        }
        if ($this->_conektaHelper->isSpeiEnabled()) {
            $methods[] = 'BankTransfer';
        }
        return $methods;
    }
}
