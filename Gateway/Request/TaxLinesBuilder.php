<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Catalog\Model\Product;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Tax\Model\ClassModel;

class TaxLinesBuilder implements BuilderInterface
{
    private $_product;
    private $_taxClass;

    private $_conektaLogger;

    public function __construct(
        Product $product,
        ClassModel $taxClass,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request TaxLinesBuilder :: __construct');
        $this->_product = $product;
        $this->_taxClass = $taxClass;
    }

    public function build(array $buildSubject)
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
        $ctr_amount = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getProductType() != 'bundle' && $item->getTaxAmount() > 0) {
                $ctr_amount = $ctr_amount + (int)($item->getTaxAmount() * 100);
            }
        }

        $request['tax_lines'][] = [
            'description' => 'Tax',
            'amount' => $ctr_amount
        ];

        $this->_conektaLogger->info('Request TaxLinesBuilder :: build : return request', $request);

        return $request;
    }
}
