<?php
namespace Conekta\Payments\Gateway\Config\BankTransfer;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class PaymentActionValueHandler implements ValueHandlerInterface
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
     * @return string
     */
    public function handle(array $subject, $storeId = null)
    {
        return 'authorize';
    }
}
