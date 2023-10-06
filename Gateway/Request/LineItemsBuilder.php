<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class LineItemsBuilder implements BuilderInterface
{

    private ConektaLogger $_conektaLogger;

    protected ConektaHelper $_conektaHelper;

    public function __construct(
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request LineItemsBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request LineItemsBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $items = $order->getItems();
        $request['line_items'] = $this->_conektaHelper->getLineItems($items, false);

        $this->_conektaLogger->info('Request LineItemsBuilder :: build : return request', $request);

        return $request;
    }
}
