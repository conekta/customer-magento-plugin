<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;

class ShippingContactBuilder implements BuilderInterface
{
    private $subjectReader;

    private $_conektaLogger;

    private $_conektaHelper;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaLogger $conektaLogger,
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request ShippingContactBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_conektaHelper = $conektaHelper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingContactBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $quoteId = $payment->getAdditionalInformation('quote_id');

        $request['shipping_contact'] = $this->_conektaHelper->getShippingContact($quoteId);

        if (empty($request['shipping_contact'])) {
            throw new LocalizedException(__('Missing shipping contacta information'));
        }

        $this->_conektaLogger->info('Request ShippingContactBuilder :: build : return request', $request);

        return $request;
    }
}
