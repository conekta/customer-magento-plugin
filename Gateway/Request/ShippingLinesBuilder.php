<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class ShippingLinesBuilder implements BuilderInterface
{
    private $subjectReader;

    private $_conektaLogger;

    protected $_cartRepository;

    public function __construct(
        SubjectReader $subjectReader,
        CartRepositoryInterface $cartRepository,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request ShippingLinesBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_cartRepository = $cartRepository;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingLinesBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $quote_id = $payment->getAdditionalInformation('quote_id');
        $quote = $this->_cartRepository->get($quote_id);
        $amount = $quote->getShippingAddress()->getShippingAmount();

        if (!empty($amount)) {
            $shipping_lines['amount'] = (int)($amount * 100);
            $shipping_lines['method'] = $quote->getShippingAddress()->getShippingMethod();
            $shipping_lines['carrier'] = $quote->getShippingAddress()->getShippingDescription();
            $request['shipping_lines'][] = $shipping_lines;
        } else {
            $request['shipping_lines'] = [];
        }

        $this->_conektaLogger->info('Request ShippingLinesBuilder :: build : return request', $request);

        return $request;
    }
}
