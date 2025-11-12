<?php
namespace Conekta\Payments\Gateway\Request\PayByBank;

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
        $this->_conektaLogger->info('Request PayByBank AuthorizeRequest :: __construct');

        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request PayByBank AuthorizeRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        
        $expiry_date = $this->_conektaHelper->getExpiredAt();
        $amount = $this->_conektaHelper->convertToApiPrice($order->getGrandTotalAmount());

        $request['metadata'] = [
            'plugin' => 'Magento',
            'plugin_version' => $this->_conektaHelper->getMageVersion(),
            'plugin_conekta_version' => $this->_conektaHelper->pluginVersion(),
            'order_id'       => $order->getOrderIncrementId(),
            'soft_validations'  => 'true'
        ];

        $request['payment_method_details'] = $this->getChargePayByBank($amount, $expiry_date);
        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';

        return $request;
    }

    public function getChargePayByBank($amount, $expiry_date)
    {
        $charge = [
            'payment_method' => [
                'type' => 'pay_by_bank',
                'expires_at' => $expiry_date
            ],
            'amount' => $amount
        ];
        return $charge;
    }
}

