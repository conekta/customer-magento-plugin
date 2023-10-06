<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TaxLinesBuilder implements BuilderInterface
{
    private ConektaLogger $_conektaLogger;

    private ConektaHelper $_conektaHelper;

    public function __construct(
        ConektaLogger $conektaLogger,
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request TaxLinesBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
    }

    public function build(array $buildSubject): array
    {
        $this->_conektaLogger->info('Request TaxLinesBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();

        $request = [];

        $request['tax_lines'] = $this->_conektaHelper->getTaxLines($order->getItems());

        $this->_conektaLogger->info('Request TaxLinesBuilder :: build : return request', $request);

        return $request;
    }
}
