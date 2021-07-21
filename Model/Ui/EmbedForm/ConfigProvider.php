<?php
namespace Conekta\Payments\Model\Ui\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Payment method code
     */
    const CODE = 'conekta_ef';
    const PAYMENT_METHOD_CREDIT_CARD = 'credit';
    const PAYMENT_METHOD_OXXO = 'oxxo';
    const PAYMENT_METHOD_SPEI = 'spei';
    /**
     * Create Order Controller Path
     */
    const CREATEORDER_URL = 'conekta/index/createorder';
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
