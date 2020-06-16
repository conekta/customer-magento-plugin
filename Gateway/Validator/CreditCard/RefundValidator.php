<?php
namespace Conekta\Payments\Gateway\Validator\CreditCard;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;

class RefundValidator extends AbstractValidator
{
    private $subjectReader;

    protected $_conektaHelper;

    private $_conektaLogger;

    /**
     * RefundValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->subjectReader = $subjectReader;

        $this->_conektaLogger->info('Credit Card RefundValidator :: __construct');

        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = $this->subjectReader->readResponse($validationSubject);

        $this->_conektaLogger->info('RefundValidator :: handle');

        $this->_conektaLogger->info('RefundValidator: response', [$response['refund_result']]);

        $errorMessages = [];
        $isValid = true;

        try {
            $transactionResult = $response['refund_result'];
            if ($transactionResult['status'] != 'SUCCESS') {
                $isValid = false;
                $errorMessages[] = $response['refund_result']['status_message'];
            }
        } catch (\Exception $e) {
            $isValid = false;
            $errorMessages[] = $e->getMessage();
        }

        return $this->createResult($isValid, $errorMessages);
    }
}
