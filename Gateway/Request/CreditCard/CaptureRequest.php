<?php
namespace Conekta\Payments\Gateway\Request\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CaptureRequest implements BuilderInterface
{
    private $config;

    private $subjectReader;

    protected $_conektaHelper;

    private $_conektaLogger;

    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request CaptureRequest :: __construct');

        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request CaptureRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $token = $payment->getAdditionalInformation('card_token');
        $installments = $payment->getAdditionalInformation('monthly_installments');

        $amount = (int)($order->getGrandTotalAmount() * 100);

        $request = [];
        try {
            $request['payment_method_details'] = $this->getChargeCard(
                $amount,
                $token
            );
            if ($this->_validateMonthlyInstallments($amount, $installments)) {
                $request['payment_method_details']['payment_method']['monthly_installments'] = $installments;
            }
        } catch (\Exception $e) {
            $this->_conektaLogger->info('Request CaptureRequest :: build Problem', $e->getMessage());
            throw new \Magento\Framework\Validator\Exception(__('Problem Creating Charge'));
        }

        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';
        $request['INVOICE'] = $order->getOrderIncrementId();
        $request['AMOUNT'] = number_format($order->getGrandTotalAmount(), 2);

        $this->_conektaLogger->info('Request CaptureRequest :: build : return request', $request);

        return $request;
    }

    public function getChargeCard($amount, $tokenId)
    {
        $charge = [
            'payment_method' => [
                'type'     => 'card',
                'token_id' => $tokenId
            ],
            'amount' => $amount
        ];

        return $charge;
    }

    private function _validateMonthlyInstallments($amount, $installments)
    {
        $active_monthly_installments = $this->_conektaHelper->getConfigData(
            'conekta/conekta_creditcard',
            'active_monthly_installments'
        );
        if ($active_monthly_installments) {
            $minimum_amount_monthly_installments = $this->_conektaHelper->getConfigData(
                'conekta/conekta_creditcard',
                'minimum_amount_monthly_installments'
            );
            if ($amount >= ($minimum_amount_monthly_installments * 100) && $installments > 1) {
                return true;
            }
        }

        return false;
    }
}
