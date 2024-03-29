<?php
namespace Conekta\Payments\Gateway\Request\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Validator\Exception;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CaptureRequest implements BuilderInterface
{

    private SubjectReader $subjectReader;

    protected ConektaHelper $_conektaHelper;

    private ConektaLogger $_conektaLogger;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request CaptureRequest :: __construct');
        $this->subjectReader = $subjectReader;
    }

    /**
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $this->_conektaLogger->info('Request CaptureRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $token = $payment->getAdditionalInformation('card_token');
        $installments = $payment->getAdditionalInformation('monthly_installments');

        $amount = $this->_conektaHelper->convertToApiPrice($order->getGrandTotalAmount());

        $request = [];
        try {
            $request['payment_method_details'] = $this->getChargeCard(
                $amount,
                $token
            );
            $request['metadata'] = [
                'plugin' => 'Magento',
                'plugin_version' => $this->_conektaHelper->getMageVersion(),
                'plugin_conekta_version' => $this->_conektaHelper->pluginVersion(),
                'order_id'       => $order->getOrderIncrementId(),
                'soft_validations'  => 'true'
            ];
            if ($this->_validateMonthlyInstallments($amount, $installments)) {
                $request['payment_method_details']['payment_method']['monthly_installments'] = $installments;
            }
        } catch (\Exception $e) {
            $this->_conektaLogger->info('Request CaptureRequest :: build Problem '.$e->getMessage());
            throw new Exception(__('Problem Creating Charge'));
        }

        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';
        $request['INVOICE'] = $order->getOrderIncrementId();
        $request['AMOUNT'] = number_format($order->getGrandTotalAmount(), 2);

        $this->_conektaLogger->info('Request CaptureRequest :: build : return request', $request);

        return $request;
    }

    public function getChargeCard($amount, $tokenId): array
    {
        return [
            'payment_method' => [
                'type'     => 'card',
                'token_id' => $tokenId
            ],
            'amount' => $amount
        ];
    }

    private function _validateMonthlyInstallments($amount, $installments): bool
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
