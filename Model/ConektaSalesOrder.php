<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Magento\Framework\Model\AbstractModel;
use Conekta\Payments\Model\ResourceModel\ConektaSalesOrder as ResourceConektaSalesOrder;

class ConektaSalesOrder extends AbstractModel implements ConektaSalesOrderInterface
{

    protected function _construct()
    {
        $this->_init(ResourceConektaSalesOrder::class);
    }

    public function setConektaOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID, $value);
    }
    public function getConektaOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID);
    }

    public function setIncrementOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::INCREMENT_ORDER_ID, $value);
    }
    public function getIncrementOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::INCREMENT_ORDER_ID);
    }

    public function loadByConektaOrderId($conektaOrderId)
    {
        return $this->loadByAttribute(ConektaSalesOrderInterface::CONEKTA_ORDER_ID, $conektaOrderId);
    }

    /**
     * Load order by custom attribute value. Attribute value should be unique
     *
     * @param string $attribute
     * @param string $value
     * @return $this
     */
    public function loadByAttribute($attribute, $value)
    {
        $this->load($value, $attribute);
        return $this;
    }
}
