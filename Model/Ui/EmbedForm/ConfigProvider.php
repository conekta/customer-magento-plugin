<?php
namespace Conekta\Payments\Model\Ui\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Payment method code
     */
    public const CODE = 'conekta_ef';
    public const PAYMENT_METHOD_CREDIT_CARD = 'card';
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bankTransfer';
    /**
     * Create Order Controller Path
     */
    public const CREATEORDER_URL = 'conekta/index/createorder';
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;
    /**
     * @var Session
     */
    protected $_checkoutSession;
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
     *
     * @param ConektaHelper $conektaHelper
     * @param Session $checkoutSession
     * @param ConektaLogger $conektaLogger
     * @param UrlInterface $url
     */
    public function __construct(
        ConektaHelper $conektaHelper,
        Session $checkoutSession,
        ConektaLogger $conektaLogger,
        UrlInterface $url
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_checkoutSession = $checkoutSession;
        $this->conektaLogger = $conektaLogger;
        $this->url = $url;
    }

    /**
     * Get config
     *
     * @return array|\array[][]
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'hasVerification' => true,
                    'monthly_installments' => $this->getMonthlyInstallments(),
                    'active_monthly_installments' => $this->getMonthlyInstallments(),
                    'minimum_amount_monthly_installments' => $this->getMinimumAmountMonthlyInstallments(),
                    'total' => $this->getQuote()->getGrandTotal(),
                    'createOrderUrl' => $this->url->getUrl(self::CREATEORDER_URL),
                    'paymentMethods' => $this->getPaymentMethodsActive(),
                    'sessionExpirationTime' => $this->_checkoutSession->getCookieLifetime()
                ]
            ]
        ];
    }

    /**
     * Get Enable Save Card Config
     *
     * @return mixed
     */
    public function getEnableSaveCardConfig()
    {
        return $this->_conektaHelper->getConfigData('conekta/conekta_global', 'enable_saved_card');
    }

    /**
     * Get montly installments
     *
     * @return false|int[]|string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * Get Minimun amount montly installments
     *
     * @return mixed
     */
    public function getMinimumAmountMonthlyInstallments()
    {
        return $this->_conektaHelper->getConfigData('conekta_cc', 'minimum_amount_monthly_installments');
    }

    /**
     * Get Quote
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
     * Get active payment methods
     *
     * @return array
     */
    public function getPaymentMethodsActive()
    {
        $methods = [];

        if ($this->_conektaHelper->isCreditCardEnabled()) {
            $methods[] = 'Card';
        }
        if ($this->_conektaHelper->isCashEnabled()) {
            $methods[] = 'Cash';
        }
        if ($this->_conektaHelper->isBankTransferEnabled()) {
            $methods[] = 'BankTransfer';
        }
        return $methods;
    }
}
