<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

use Magento\Payment\Gateway\Helper\SubjectReader;

class MetadataBuilder implements BuilderInterface
{
    private $_conektaLogger;

    protected $_conektaHelper;

    protected $productRepository;

    private $subjectReader;

    public function __construct(
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        SubjectReader $subjectReader
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request MetadataBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request MetadataBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $this->subjectReader->readPayment($buildSubject);
        $order = $payment->getOrder();
        $items = $order->getItems();
        $request['metadata'] = $this->_conektaHelper->getMetadataAttributesConekta($items);

        $this->_conektaLogger->info('Request MetadataBuilder :: build : return request', $request);

        return $request;
    }
}
