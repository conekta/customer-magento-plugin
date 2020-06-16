<?php
namespace Conekta\Payments\Gateway\Response\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    private $subjectReader;

    protected $_conektaHelper;

    private $_conektaLogger;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Response RefundHandler :: __construct');
        $this->subjectReader = $subjectReader;
    }

    public function handle(array $handlingSubject, array $response)
    {
        $this->_conektaLogger->info('Request RefundHandler :: handle');

        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $transactionId = $response['refund_result']['transaction_id'];
        $payment->setTransactionId(
            $transactionId . '-'
                . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND
        );

        $payment->setParentTransactionId($transactionId);
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction(true);

        $payment->setIsTransactionPending(false);
    }
}
