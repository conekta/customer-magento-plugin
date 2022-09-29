<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Magento\Framework\Model\AbstractModel;
use Conekta\Payments\Model\ResourceModel\ConektaQuote as ResourceConektaQuote;

class ConektaQuote extends AbstractModel implements ConektaQuoteInterface
{
    /**
     * Construct
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceConektaQuote::class);
    }

    /**
     * SetQuoteId
     *
     * @param mixed $value
     * @return void
     */
    public function setQuoteId($value)
    {
        $this->setData(ConektaQuoteInterface::QUOTE_ID, $value);
    }

    /**
     * GetQuoteId
     *
     * @return array|int|mixed|null
     */
    public function getQuoteId()
    {
        return $this->getData(ConektaQuoteInterface::QUOTE_ID);
    }

    /**
     * SetConektaOrderId
     *
     * @param mixed $value
     * @return void
     */
    public function setConektaOrderId($value)
    {
        $this->setData(ConektaQuoteInterface::CONEKTA_ORDER_ID, $value);
    }

    /**
     * GetConektaOrderId
     *
     * @return array|mixed|string|null
     */
    public function getConektaOrderId()
    {
        return $this->getData(ConektaQuoteInterface::CONEKTA_ORDER_ID);
    }

    /**
     * LoadByConektaOrderId
     *
     * @param mixed $conektaOrderId
     * @return $this
     */
    public function loadByConektaOrderId($conektaOrderId)
    {
        return $this->loadByAttribute(ConektaQuoteInterface::CONEKTA_ORDER_ID, $conektaOrderId);
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
