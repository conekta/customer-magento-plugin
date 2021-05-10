<?php
namespace Conekta\Payments\Gateway\Request\Spei;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AuthorizeRequest implements BuilderInterface
{
    private $config;

    private $subjectReader;

    protected $_conektaHelper;

    private $_conektaLogger;

    public function __construct(
        ConfigInterface $config,
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request Spei AuthorizeRequest :: __construct');

        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request Spei AuthorizeRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $expiry_date = strtotime("+" . $this->_conektaHelper->getConfigData('conekta_spei', 'expiry_days') . " days");
        $amount = (int)$order->getGrandTotalAmount();

        $request['metadata'] = [
            'checkout_id'       => $order->getOrderIncrementId(),
            'soft_validations'  => true

        ];
        $request['payment_method_details'] = $this->getChargeSpei($amount, $expiry_date);
        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';

        return $request;
    }

    public function getChargeSpei($amount, $expiry_date)
    {
        $charge = [
            'payment_method' => [
                'type' => 'spei',
                'expires_at' => $expiry_date
            ],
            'amount' => $amount
        ];
        return $charge;
    }
}
