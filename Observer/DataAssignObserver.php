<?php
namespace Conekta\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Checkout\Model\Session;

class DataAssignObserver extends AbstractDataAssignObserver
{
    public const PAYMENT_METHOD = 'payment_method';
    public const CC_TYPE = 'cc_type';
    public const CC_EXP_YEAR = 'cc_exp_year';
    public const CC_EXP_MONTH = 'cc_exp_month';
    public const CC_BIN = 'cc_bin';
    public const CC_LAST_4 = 'cc_last_4';
    public const CARD_TOKEN = 'card_token';
    public const MONTLY_INSTALLAMENTS = 'monthly_installments';
    public const SAVED_CARD = 'saved_card';
    public const SAVED_CARD_LATER = 'saved_card_later';
    public const IFRAME_PAYMENT = 'iframe_payment';
    public const ORDER_ID = 'order_id';
    public const TXN_ID = 'txn_id';
    public const C_TYPE = 'c_type';
    public const REFERENCE = 'reference';
    /**
     * @var string[]
     */
    protected $additionalInformationList = [
        self::PAYMENT_METHOD,
        self::CC_TYPE,
        self::C_TYPE,
        self::CC_EXP_YEAR,
        self::CC_EXP_MONTH,
        self::CC_BIN,
        self::CC_LAST_4,
        self::CARD_TOKEN,
        self::MONTLY_INSTALLAMENTS,
        self::SAVED_CARD,
        self::SAVED_CARD_LATER,
        self::IFRAME_PAYMENT,
        self::ORDER_ID,
        self::TXN_ID,
        self::TIPO_TARJETA,
        self::REFERENCE,
    ];
    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @param Session $checkoutSession
     */
    public function __construct(
        Session $checkoutSession
    ) {
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);
        $quote = $this->_checkoutSession->getQuote();

        $paymentInfo->setAdditionalInformation(
            'quote_id',
            $quote->getId()
        );

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
