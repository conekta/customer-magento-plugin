<?php
namespace Conekta\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ConektaSalesOrder extends AbstractDb
{
    protected $_isPkAutoIncrement = false;
    
	protected function _construct()
	{
		$this->_init('conekta_salesorder', 'conekta_order_id');
	}
}
