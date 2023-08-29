<?php
namespace Conekta\Payments\Api;

use Conekta\Api\OrdersApi;
use Conekta\Model\OrderResponse;
use Conekta\Payments\Exception\ConektaException;

interface EmbedFormRepositoryInterface
{
    /**
     * Generate form repository interface
     *
     * @param int $quoteId
     * @param $orderParams
     * @param float $orderTotal
     * @return OrderResponse
     * @throws ConektaException
     */
    public function generate($quoteId, $orderParams, $orderTotal): OrderResponse;
}
