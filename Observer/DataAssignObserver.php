<?php
namespace Conekta\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Checkout\Model\Session;

class DataAssignObserver extends AbstractDataAssignObserver
{
    const PAYMENT_METHOD = 'payment_method';
    const CC_TYPE = 'cc_type';
    const CC_EXP_YEAR = 'cc_exp_year';
    const CC_EXP_MONTH = 'cc_exp_month';
    const CC_BIN = 'cc_bin';
    const CC_LAST_4 = 'cc_last_4';
    const CARD_TOKEN = 'card_token';
    const MONTLY_INSTALLAMENTS = 'monthly_installments';
    const SAVED_CARD = 'saved_card';
    const SAVED_CARD_LATER = 'saved_card_later';
    const IFRAME_PAYMENT = 'iframe_payment';
    const ORDER_ID = 'order_id';
    const TXN_ID = 'txn_id';
    const REFERENCE = 'reference';

    protected $additionalInformationList = [
        self::PAYMENT_METHOD,
        self::CC_TYPE,
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
        self::REFERENCE,
    ];

    protected $_checkoutSession;

    public function __construct(
        Session $checkoutSession
    ) {
        $this->_checkoutSession = $checkoutSession;
    }

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
