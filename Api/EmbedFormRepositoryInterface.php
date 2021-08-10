<?php
namespace Conekta\Payments\Api;

interface EmbedFormRepositoryInterface
{
    
    /**
     * @param int $quoteId
     * @param [] $orderParams
     * @param float $orderTotal
     * @return \Conekta\Order
     * @throws ConektaException
     */
    public function generate($quoteId, $orderParams, $orderTotal);
}
