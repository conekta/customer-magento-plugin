<?php
namespace Conekta\Payments\Gateway\Request\Bnpl;

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
        $this->_conektaLogger->info('Request BNPL AuthorizeRequest :: __construct');

        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request BNPL AuthorizeRequest :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        
        $expiry_date = $this->_conektaHelper->getExpiredAt();
        $amount = $this->_conektaHelper->convertToApiPrice($order->getGrandTotalAmount());

        $request['metadata'] = [
            'plugin' => 'Magento',
            'plugin_version' => $this->_conektaHelper->getMageVersion(),
            'plugin_conekta_version' => $this->_conektaHelper->pluginVersion(),
            'order_id' => $order->getOrderIncrementId(),
            'soft_validations' => 'true'
        ];

        $request['order'] = [
            'line_items' => $this->_conektaHelper->getLineItems($order),
            'shipping_lines' => $this->_conektaHelper->getShippingLines($order),
            'discount_lines' => $this->_conektaHelper->getDiscountLines($order),
            'tax_lines' => $this->_conektaHelper->getTaxLines($order),
            'customer_info' => $this->_conektaHelper->getCustomerInfo($order),
            'shipping_contact' => $this->_conektaHelper->getShippingContact($order),
            'metadata' => $request['metadata'],
            'currency' => $order->getCurrencyCode()
        ];

        $monthly_installments = $payment->getAdditionalInformation('monthly_installments');
        
        $request['charge_request'] = [
            'payment_method' => [
                'type' => 'bnpl',
                'monthly_installments' => $monthly_installments ?: 3
            ],
            'amount' => $amount,
            'currency' => $order->getCurrencyCode()
        ];

        $this->_conektaLogger->info('Request BNPL AuthorizeRequest :: build : return request', $request);

        return $request;
    }
}
