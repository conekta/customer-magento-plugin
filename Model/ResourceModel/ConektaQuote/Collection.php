<?php
namespace Conekta\Payments\Model\ResourceModel\ConektaQuote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Conekta\Payments\Model\ConektaQuote;
use Conekta\Payments\Model\ResourceModel\ConektaQuote as ResourceConektaQuote;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            ConektaQuote::class,
            ResourceConektaQuote::class
        );
    }
}
