<?php
namespace Conekta\Payments\Model\Api\Data;

interface ConektaSalesOrderInterface
{

    public const CONEKTA_ORDER_ID = 'conekta_order_id';
    public const ORDER_ID = 'order_id';

    public function getId();

    public function getConektaOrderId();
    public function setConektaOrderId($value);

    public function getOrderId();
    public function setOrderId($value);
}
