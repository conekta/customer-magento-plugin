<?php 
namespace Conekta\Payments\Model;

use Conekta\Payments\Model\Api\Data\ConektaSalesOrderInterface;
use Magento\Framework\Model\AbstractModel;

class ConektaSalesOrder extends AbstractModel implements ConektaSalesOrderInterface
{

    protected function _construct()
    {
        $this->_init('Conekta\Payments\Model\ResourceModel\ConektaSalesOrder');
    }

    public function setConektaOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID, $value);
    }

    public function getConektaOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID);
    }

    public function setOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::ORDER_ID, $value);
    }

    public function getOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::ORDER_ID);
    }
}