<?php

namespace Conekta\Payments\Model\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;

class Pendingpayment extends Status
{
    /**
     * @var array
     */
    protected $_stateStatuses = [Order::STATE_PENDING_PAYMENT];

    public function toOptionArray(): array
    {
        // Agregar un mensaje de registro
        error_log("Conekta Custom Order Status: Logging something here.");

        return parent::toOptionArray();
    }
}
