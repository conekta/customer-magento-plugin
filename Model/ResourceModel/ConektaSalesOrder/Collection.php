<?php
namespace Conekta\Payments\Model\ResourceModel\ConektaSalesOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Conekta\Payments\Model\ConektaSalesOrder;
use Conekta\Payments\Model\ResourceModel\ConektaSalesOrder as ResourceConektaSalesOrder;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            ConektaSalesOrder::class,
            ResourceConektaSalesOrder::class
        );
    }
}
