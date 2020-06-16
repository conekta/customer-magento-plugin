<?php
namespace Conekta\Payments\Gateway\Response\Spei;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;

class TxnIdHandler implements HandlerInterface
{
    const TXN_ID = 'TXN_ID';
    const ORD_ID = 'ORD_ID';

    private $_conektaLogger;

    private $subjectReader;

    /**
     * TxnIdHandler constructor.
     * @param ConektaLogger $conektaLogger
     * @param SubjectReader $subjectReader
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     */
    public function __construct(
        ConektaLogger $conektaLogger,
        SubjectReader $subjectReader
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Response Spei TxnIdHandler :: __construct');

        $this->subjectReader = $subjectReader;
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
        $this->_conektaLogger->info('Response Spei TxnIdHandler :: handle');

        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        $payment->setTransactionId($response[self::TXN_ID]);
        $payment->setAdditionalInformation('offline_info', $response['offline_info']);

        $order->setExtOrderId($response[self::ORD_ID]);

        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
