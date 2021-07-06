<?php
namespace Conekta\Payments\Api\Data;

interface ConektaQuoteInterface
{
    public const QUOTE_ID = 'quote_id';
    public const CONEKTA_ORDER_ID = 'conekta_order_id';

    /**
     * @return int
     */
    public function getQuoteId();

    /**
     * @param int $value
     * @return void
     */
    public function setQuoteId($value);

    /**
     * @return string
     */
    public function getConektaOrderId();

    /**
     * @param string $value
     * @return void
     */
    public function setConektaOrderId($value);

    
}
