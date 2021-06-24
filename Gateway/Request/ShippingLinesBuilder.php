<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShippingLinesBuilder implements BuilderInterface
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
        $this->_conektaLogger->info('Request ShippingLinesBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_conektaHelper = $conektaHelper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingLinesBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $quote_id = $payment->getAdditionalInformation('quote_id');
        
        $shippingLines = $this->_conektaHelper->getShippingLines($quote_id);

        if (empty($shippingLines)) {
            throw new LocalizedException(__('Shippment information should be provided'));
        }

        $request['shipping_lines'] = $shippingLines;

        $this->_conektaLogger->info('Request ShippingLinesBuilder :: build : return request', $request);
        
        return $request;
    }
}
