<?php
namespace Conekta\Payments\Gateway\Config\EmbedForm;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class ActiveValueHandler implements ValueHandlerInterface
{
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;

    /**
     * @param ConektaHelper $conektaHelper
     */
    public function __construct(
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaHelper = $conektaHelper;
    }

    /**
     * Handle
     *
     * @param array $subject
     * @param mixed $storeId
     * @return bool
     */
    public function handle(array $subject, $storeId = null)
    {
        return $this->_conektaHelper->isCreditCardEnabled()
               || $this->_conektaHelper->isOxxoEnabled()
               || $this->_conektaHelper->isSpeiEnabled();
    }
}
