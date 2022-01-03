<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Magento\Framework\Model\AbstractModel;
use Conekta\Payments\Model\ResourceModel\ConektaQuote as ResourceConektaQuote;

class ConektaQuote extends AbstractModel implements ConektaQuoteInterface
{

    protected function _construct()
    {
        $this->_init(ResourceConektaQuote::class);
    }

    public function setQuoteId($value)
    {
        $this->setData(ConektaQuoteInterface::QUOTE_ID, $value);
    }
    public function getQuoteId()
    {
        return $this->getData(ConektaQuoteInterface::QUOTE_ID);
    }

    public function setConektaOrderId($value)
    {
        $this->setData(ConektaQuoteInterface::CONEKTA_ORDER_ID, $value);
    }
    public function getConektaOrderId()
    {
        return $this->getData(ConektaQuoteInterface::CONEKTA_ORDER_ID);
    }

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
