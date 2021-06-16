<?php
namespace Conekta\Payments\Model\ResourceModel\ConektaSalesOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            'Conekta\Payments\Model\ConektaSalesOrder',
            'Conekta\Payments\Model\ResourceModel\ConektaSalesOrder'
        );
    }

}
