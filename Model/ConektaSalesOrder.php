<?php 
namespace Conekta\Payments\Model;

use Magento\Framework\Model\AbstractModel;

class ConektaSalesOrder extends AbstractModel
{
    
    protected function _construct()
    {
        $this->_init('Conekta\Payments\Model\ResourceModel\ConektaSalesOrder');
    }
}