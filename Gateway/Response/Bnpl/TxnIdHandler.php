<?php
namespace Conekta\Payments\Gateway\Response\Bnpl;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class TxnIdHandler implements HandlerInterface
{
    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;

    /**
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
    }

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->_conektaLogger->info('BNPL TxnIdHandler :: handle');

        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment->setTransactionId($response['TXN_ID']);
        $payment->setIsTransactionClosed(false);

        if (isset($response['offline_info'])) {
            $payment->setAdditionalInformation('offline_info', $response['offline_info']);
        }

        $payment->setAdditionalInformation('conekta_order_id', $response['ORD_ID']);
        $payment->setAdditionalInformation('payment_method', 'bnpl');
    }
} 