<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

use Magento\Payment\Gateway\Helper\SubjectReader;

class MetadataBuilder implements BuilderInterface
{
    private ConektaLogger $_conektaLogger;

    protected ConektaHelper $_conektaHelper;

    private SubjectReader $subjectReader;

    public function __construct(
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        SubjectReader $subjectReader
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request MetadataBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
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
