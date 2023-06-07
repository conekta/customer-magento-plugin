<?php
namespace Conekta\Payments\Api\Data;

interface ConektaSalesOrderInterface
{
    public const CONEKTA_ORDER_ID = 'conekta_order_id';
    public const INCREMENT_ORDER_ID = 'increment_order_id';

    /**
     * Get Conekta ID
     *
     * @return mixed
     */
    public function getId();

    /**
     * Get Conekta Order Id
     *
     * @return mixed
     */
    public function getConektaOrderId();

    /**
     * Set Conekta Order Id
     *
     * @param mixed $value
     * @return mixed
     */
    public function setConektaOrderId($value);

    /**
     * Gets the Sales Increment Order ID
     *
     * @return string|null Sales Increment Order ID.
     */
    public function getIncrementOrderId();

    /**
     * Set Increment Order Id
     *
     * @param mixed $value
     * @return mixed
     */
    public function setIncrementOrderId($value);
}
