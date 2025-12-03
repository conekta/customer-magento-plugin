<?php
namespace Conekta\Payments\Gateway\Config\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class ActiveValueHandler implements ValueHandlerInterface
{
    /**
     * @var ConektaHelper
     */
    protected ConektaHelper $_conektaHelper;

    /**
     * @param ConektaHelper $conektaHelper
     */
    public function __construct(ConektaHelper $conektaHelper) {
        $this->_conektaHelper = $conektaHelper;
    }

    /**
     * Handle
     *
     * @param array $subject
     * @param mixed $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null): bool
    {
        return $this->_conektaHelper->isCreditCardEnabled()
               || $this->_conektaHelper->isCashEnabled()
               || $this->_conektaHelper->isBankTransferEnabled()
               || $this->_conektaHelper->isBnplEnabled()
               || $this->_conektaHelper->isPayByBankEnabled();
    }
}
