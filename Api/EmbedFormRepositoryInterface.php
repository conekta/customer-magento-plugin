<?php
namespace Conekta\Payments\Api;

interface EmbedFormRepositoryInterface
{
    
    /**
     * @param int $quoteId
     * @param [] $orderParams
     * @return \Conekta\Order
     */
    public function generate($quoteId, $orderParams);
}
