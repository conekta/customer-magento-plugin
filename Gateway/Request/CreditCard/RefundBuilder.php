<?php
namespace Conekta\Payments\Gateway\Request\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundBuilder implements BuilderInterface
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
        $this->_conektaLogger->info('Request RefundBuilder :: __construct');

        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request RefundBuilder :: build');
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $amount =  $this->subjectReader->readAmount($buildSubject);

        $request = [
            'payment_transaction_id' => $order->getExtOrderId(),
            'payment_transaction_amount' => $amount
        ];

        $this->_conektaLogger->info('Request RefundBuilder :: build request', $request);

        return $request;
    }
}
