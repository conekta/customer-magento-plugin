<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Magento\Framework\Model\AbstractModel;
use Conekta\Payments\Model\ResourceModel\ConektaSalesOrder as ResourceConektaSalesOrder;

class ConektaSalesOrder extends AbstractModel implements ConektaSalesOrderInterface
{
    /**
     * Construct
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceConektaSalesOrder::class);
    }

    /**
     * SetConektaOrderId
     *
     * @param mixed $value
     * @return void
     */
    public function setConektaOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID, $value);
    }

    /**
     * GetConektaOrderId
     *
     * @return array|mixed|null
     */
    public function getConektaOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::CONEKTA_ORDER_ID);
    }

    /**
     * SetIncrementOrderId
     *
     * @param mixed $value
     * @return void
     */
    public function setIncrementOrderId($value)
    {
        $this->setData(ConektaSalesOrderInterface::INCREMENT_ORDER_ID, $value);
    }

    /**
     * GetIncrementOrderId
     *
     * @return array|mixed|string|null
     */
    public function getIncrementOrderId()
    {
        return $this->getData(ConektaSalesOrderInterface::INCREMENT_ORDER_ID);
    }

    /**
     * LoadByConektaOrderId
     *
     * @param mixed $conektaOrderId
     * @return $this
     */
    public function loadByConektaOrderId($conektaOrderId): ConektaSalesOrder
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
    public function loadByAttribute($attribute, $value): ConektaSalesOrder
    {
        $this->load($value, $attribute);
        return $this;
    }

    public function getId()
    {
        return $this->getData("id");
    }
}
