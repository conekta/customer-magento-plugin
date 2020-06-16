<?php
namespace Conekta\Payments\Gateway\Config\Oxxo;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class PaymentActionValueHandler implements ValueHandlerInterface
{

    protected $_conektaHelper;

    public function __construct(
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaHelper = $conektaHelper;
    }

    public function handle(array $subject, $storeId = null)
    {
        return 'authorize';
    }
}
