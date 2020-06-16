<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Catalog\Model\Product;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CustomerInfoBuilder implements BuilderInterface
{
    private $_product;

    private $_conektaLogger;

    public function __construct(
        Product $product,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request LineItemsBuilder :: __construct');
        $this->_product = $product;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request CustomerInfoBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();

        $billing = $order->getBillingAddress();

        $request['customer_info'] = [
            'name' => $this->getCustomerName($order),
            'email' => $billing->getEmail(),
            'phone' => $billing->getTelephone(),
            'metadata' => [
                'soft_validations' => true
            ]
        ];

        $this->_conektaLogger->info('Request CustomerInfoBuilder :: build : return request', $request);

        return $request;
    }

    public function getCustomerName($order)
    {
        $billing = $order->getBillingAddress();
        $customerName = sprintf(
            '%s %s %s',
            $billing->getFirstName(),
            $billing->getMiddleName(),
            $billing->getLastName()
        );

        return $customerName;
    }
}
