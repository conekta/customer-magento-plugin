<?php
namespace Conekta\Payments\Api;

use Conekta\Order;
use Conekta\Payments\Exception\ConektaException;

interface EmbedFormRepositoryInterface
{
    
    /**
     * Generate form repository interface
     *
     * @param int $quoteId
     * @param [] $orderParams
     * @param float $orderTotal
     * @return Order
     * @throws ConektaException
     */
    public function generate($quoteId, $orderParams, $orderTotal);
}
