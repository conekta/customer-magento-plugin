<?php

namespace Conekta\Payments\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Payment\Model\Method\Cc;
use Conekta\Payments\Model\Config as Config;
use Magento\Store\Api\Data\StoreInterface;

class Card extends Cc
{
    const MINAMOUNT = 300.00;
    const CODE = 'conekta_card';
    protected $_code = self::CODE;
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = ["USD", "MXN"];
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];
    protected $_scopeConfig;
    protected $_isSandbox = true;
    protected $_privateKey = null;
    protected $_publicKey = null;
    protected $_monthlyInstallments;
    protected $_activeMonthlyInstallments;
    protected $_minAmountMonthInstallments;
    protected $_typesCards;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        CountryFactory $countryFactory,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        if (!class_exists('\\Conekta\\Payments\\Model\\Config')){
            throw new \Magento\Framework\Validator\Exception(
                __("Class Conekta\\Payments\\Model\\Config not found.")
            );
        }

        $this->_scopeConfig = $scopeConfig;

        $this->_countryFactory = $countryFactory;

        $this->_isSandbox = (boolean) $this->_getConektaConfig('sandbox_mode');

        $this->_typesCards = $this->getConfigData('cctypes');

        //TODO: log this var to better validation
        $this->_activeMonthlyInstallments =
            ((integer) $this->getConfigData(
                'active_monthly_installments'
            ));

        if ($this->_activeMonthlyInstallments) {

            $this->_monthlyInstallments = $this->getConfigData(
                'monthly_installments'
            );

            $this->_minAmountMonthInstallments =
                (float) $this->getConfigData(
                    'minimum_amount_monthly_installments'
                );
            if (empty($this->_minAmountMonthInstallments)
                || $this->_minAmountMonthInstallments <= 0){

                $this->_minAmountMonthInstallments = MINAMOUNT;

            }
        }

        if ($this->_isSandbox) {
            $privateKey = (string) $this->_getConektaConfig(
                'test_private_api_key'
            );
            $publicKey = (string) $this->_getConektaConfig(
                'test_public_api_key'
            );
        } else {
            $privateKey = (string) $this->_getConektaConfig(
                'live_private_api_key'
            );
            $publicKey = (string) $this->_getConektaConfig(
                'live_public_api_key'
            );
        }

        if (!empty($privateKey)) {
            $this->_privateKey = $privateKey;
            unset($privateKey);
        } else {
            $this->_logger->error(
                __('Please set Conekta API keys in your admin.')
            );
        }

        if (!empty($publicKey)) {
            $this->_publicKey = $publicKey;
            unset($publicKey);
        } else {
            $this->_logger->error(
                __('Please set Conekta API keys in your admin.')
            );
        }

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Exception
     */
    public function assignData(\Magento\Framework\DataObject $data) {

        parent::assignData($data);

        $content = (array) $data->getData();

        $info = $this->getInfoInstance();

        if (key_exists('additional_data', $content)) {

            if (key_exists('card_token',$content['additional_data'])) {
                $additionalData = $content['additional_data'];

                $info->setAdditionalInformation(
                    'card_token', $additionalData['card_token']
                );
                $info->setCcType($additionalData['cc_type'])
                    ->setCcExpYear($additionalData['cc_exp_year'])
                    ->setCcExpMonth($additionalData['cc_exp_month']);

                // Additional data
                if (key_exists('monthly_installments', $additionalData))
                    $info->setAdditionalInformation(
                        'monthly_installments',
                        $additionalData['monthly_installments']
                    );

                // PCI assurance
                $info->setAdditionalInformation(
                    'cc_bin',
                    $additionalData['cc_bin']
                );
                $info->setAdditionalInformation(
                    'cc_last_4',
                    $additionalData['cc_last_4']
                );

            } else {
                $this->_logger->error(__('[Conekta]: Card token not found.'));
                throw new \Magento\Framework\Validator\Exception(
                    __("Payment capturing error.")
                );
            }

            if ($this->isActiveMonthlyInstallments()) {
                if (key_exists(
                    'monthly_installments',
                    $content['additional_data'])){
                    $info->setAdditionalInformation(
                        'monthly_installments',
                        $content['additional_data']['monthly_installments']
                    );
                }
            }
            return $this;
        }

        throw new \Magento\Framework\Validator\Exception(
            __("Payment capturing error.")
        );
    }

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        Config::initializeConektaLibrary($this->_privateKey);
        $info = $this->getInfoInstance();
        $order = $payment->getOrder();
        $monthlyInstallments = $info->getAdditionalInformation(
            'monthly_installments'
        );
        $orderParams['currency']         = $order->getStoreCurrencyCode();
        $orderParams['line_items']       = Config::getLineItems($order);
        $orderParams['tax_lines']        = Config::getTaxLines($order);
        $orderParams['customer_info']    = Config::getCustomerInfo($order);
        $orderParams['shipping_lines']   = Config::getShippingLines($order);
        $orderParams['discount_lines']   = Config::getDiscountLines($order);
        $orderParams['shipping_contact'] = Config::getShippingContact($order);

        $finalAmount = intval((float)$amount * 1000) / 10;

        try {
            $chargeParams = Config::getChargeCard(
                $finalAmount,
                Config::getCardToken($info)
            );
        }catch(\Exception $e){
            $this->_logger->log(100,$e->getMessage());
            throw new \Magento\Framework\Validator\Exception(
                __('Problem Creating Charge')
            );
        }

        if ($this->isActiveMonthlyInstallments()
            && intval($monthlyInstallments) > 1) {

            if ($this->_validateMonthlyInstallments(
                $finalAmount, $monthlyInstallments)) {

                $chargeParams
                ['payment_method']
                ['monthly_installments'] = $monthlyInstallments;
                $order->addStatusHistoryComment(
                    "Monthly installments select "
                    . $chargeParams['payment_method']['monthly_installments']
                    . ' months'
                );
                $order->save();
            } else {
                $this->_logger->error(
                    __('[Conekta]: installments: '
                        .  $monthlyInstallments
                        . ' Amount: ' . $finalAmount
                    )
                );
                throw new \Magento\Framework\Validator\Exception(
                    __('Problem with monthly installments.')
                );
            }
        }
        try {
            $orderParams = Config::checkBalance(
                $orderParams,
                $finalAmount
            );
            //create order
            $newOrder = \Conekta\Order::create($orderParams);
            //create charge
            $newCharge = $newOrder->createCharge($chargeParams);
            //set transaction completed
            $payment
                ->setTransactionId($newCharge->id)
                ->setIsTransactionClosed(0);
        } catch(\Exception $e) {
            $this->_logger->error(
                __('[Conekta]: Payment capturing error. '
                    . $e->getMessage())
            );
            throw new \Magento\Framework\Validator\Exception(__(
                    $e->getMessage()
                )
            );
        }
        return $this;
    }

    /**
     * Payment refund
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        Config::initializeConektaLibrary($this->_privateKey);
        $transactionId = $payment->getParentTransactionId();
        try {
            $charge = \Conekta\Charge::find($transactionId);
            $charge->refund();
        } catch (\Exception $e) {
            $logData = json_encode([
                'transaction_id' => $transactionId,
                'exception'      => $e->getMessage()
            ]);
            $this->_logger->log(100,$logData);
            $this->_logger->error(__('Payment refunding error.'));
            throw new \Magento\Framework\Validator\Exception(
                __('Payment refunding error.')
            );
        }
        $payment
            ->setTransactionId(
                $transactionId. '-'
                . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND
            )
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);

        return $this;
    }


    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if(self::amountBetweenBounds($quote,$this->_minAmount, $this->_maxAmount)){
            return false;
        }

        if (empty($this->_privateKey) || empty($this->_publicKey)) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    public static function amountBetweenBounds($quote, $min, $max)
    {
        if(!$quote){
            return false;
        }
        $grandTotal = $quote->getBaseGrandTotal();

        if($grandTotal < $min || $grandTotal > $max){
            return false;
        }
        return true;
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

    /**
     * Return the publishable key
     *
     * @return string
     */
    public function getPubishableKey()
    {
        return $this->_publicKey;
    }

    public function getActiveTypeCards()
    {
        $activeTypes = explode(",", $this->_typesCards);
        $supportType = [
            "AE" => "American Express",
            "VI" => "Visa",
            "MC" => "MasterCard"
        ];
        $out = [];
        foreach ($activeTypes AS $value) {
            $out[$value] = $supportType[$value];
        }

        return $out;
    }

    /**
     * isActiveMonthlyInstallments 
     * return if is active monthly installments
     * @return boolean
     */
    public function isActiveMonthlyInstallments()
    {
        return $this->_activeMonthlyInstallments;
    }

    /**
     * Return Monthly Installments
     * @return array
     */
    public function getMonthlyInstallments()
    {
        $months = explode(',', $this->_monthlyInstallments);
        if (!in_array("1", $months)) {
            array_push($months, "1");
        }
        asort($months);

        return $months;
    }

    /**
     * Get Minimum MI
     * @return integer
     *
     */
    public function getMinimumAmountMonthlyInstallments()
    {
        return $this->_minAmountMonthInstallments;
    }

    /**
     * Validate MI
     * @return boolean
     *
     */
    private function _validateMonthlyInstallments($totalAmount, $installments)
    {
        if ($totalAmount >= $this->getMinimumAmountMonthlyInstallment()) {
            if (intval($installments) > 1)

                return ($totalAmount > ($installments * 100));
        }

        return false;
    }

    /**
     * Conekta Config getter
     * @return Config
     */
    private function _getConektaConfig($field){
        $path = 'payment/' . \Conekta\Payments\Model\Config::CODE . '/' . $field;

        return $this
            ->_scopeConfig
            ->getValue(
                $path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                parent::getStore()
            );
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate()
    {
        $info = $this->getInfoInstance();
        $errorMsg = false;
        $availableTypes = explode(',', $this->getConfigData('cctypes'));

        // PCI assurance
        $binNumber = $info->getAdditionalInformation('cc_bin');
        $last4 =  $info->getAdditionalInformation('cc_last_4');
        $ccNumber = $binNumber.$last4;

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);

        $info->setCcNumber($ccNumber."******".$last4);

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)) {
            if ($this->validateCcNumOther($binNumber)) {
                $ccTypeRegExpList = [
                    // Visa
                    'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
                    // MasterCard
                    'MC' => '/^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$/',
                    // American Express
                    'AE' => '/^3[47][0-9]{13}$/',
                ];

                // Validate only main brands.
                $ccNumAndTypeMatches = isset(
                        $ccTypeRegExpList[$info->getCcType()]
                    ) && preg_match(
                        $ccTypeRegExpList[$info->getCcType()],
                        $ccNumber
                    ) || !isset(
                        $ccTypeRegExpList[$info->getCcType()]
                    );

                $ccType = $ccNumAndTypeMatches ? $info->getCcType() : 'OT';
            } else {
                $errorMsg = __('Custom Invalid Credit Card Number');
            }
        } else {
            $errorMsg = __(
                'Custom This credit card type is not allowed for this payment method.'
            );
        }
        if ($ccType != 'SS' && !$this
                ->_validateExpDate(
                    $info->getCcExpYear(),
                    $info->getCcExpMonth()
                )) {
            $errorMsg = __(
                'Custom Please enter a valid credit card expiration date.'
                .$info->getCcType()
            );
        }
        if ($errorMsg) {
            throw new \Magento\Framework\Exception\LocalizedException($errorMsg);
        }

        return $this;
    }

}