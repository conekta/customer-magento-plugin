<?php
namespace Conekta\Payments\Gateway\Request\Bnpl;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AuthorizeRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;
    
    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;

    /**
     * @param ConfigInterface $config
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        ConfigInterface $config,
        ConektaLogger $conektaLogger
    ) {
        $this->config = $config;
        $this->_conektaLogger = $conektaLogger;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $this->_conektaLogger->info('BNPL AuthorizeRequest :: build');
        
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $address = $order->getBillingAddress();

        return [
            'payment_method_details' => [
                'type' => 'bnpl'
            ]
        ];
    }
} 