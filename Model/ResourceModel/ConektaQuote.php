<?php
namespace Conekta\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ConektaQuote extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('conekta_quote', 'quote_id');
        $this->_isPkAutoIncrement = false;
    }
}
