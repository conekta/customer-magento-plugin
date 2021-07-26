<?php
namespace Conekta\Payments\Gateway\Request\Oxxo;

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
        $this->_conektaLogger->info('Request Oxxo AuthorizeRequest :: __construct');

        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request Oxxo AuthorizeRequest :: build');
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        
        $timeFormat = $this->_conektaHelper->getConfigData('conekta_oxxo', 'days_or_hours');
        if (!$timeFormat) {
            $expiryHours = $this->_conektaHelper->getConfigData('conekta_oxxo', 'expiry_hours');
            $expiry_date = strtotime("+" . $expiryHours . " hours");
        } else {
            $expiryDays = $this->_conektaHelper->getConfigData('conekta_oxxo', 'expiry_days');
            $expiry_date = strtotime("+" . $expiryDays . " days");
        }
        $amount = (int)$order->getGrandTotalAmount();

        $request['metadata'] = [
            'plugin' => 'Magento',
            'plugin_version' => $this->_conektaHelper->getMageVersion(),
            'order_id'       => $order->getOrderIncrementId(),
            'soft_validations'  => 'true'
        ];

        $request['payment_method_details'] = $this->getChargeOxxo($amount, $expiry_date);
        $request['CURRENCY'] = $order->getCurrencyCode();
        $request['TXN_TYPE'] = 'A';

        return $request;
    }

    public function getChargeOxxo($amount, $expiry_date)
    {
        $amount = $amount * 100;
        $charge = [
            'payment_method' => [
                'type' => 'oxxo_cash',
                'expires_at' => $expiry_date
            ],
            'amount' => $amount
        ];
        return $charge;
    }
}
