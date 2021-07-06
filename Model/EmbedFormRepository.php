<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Conekta\Payments\Api\EmbedFormRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Conekta\Order as ConektaOrderApi;

class EmbedFormRepository implements EmbedFormRepositoryInterface
{
    private $_conektaLogger;
    private $conektaQuoteInterface;
    protected $conektaOrderApi;
    private $conektaQuoteRepositoryFactory;

    public function __construct(
        ConektaLogger $conektaLogger,
        ConektaQuoteInterface $conektaQuoteInterface,
        ConektaOrderApi $conektaOrderApi,
        ConektaQuoteRepositoryFactory $conektaQuoteRepositoryFactory
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->conektaQuoteInterface = $conektaQuoteInterface;
        $this->conektaQuoteRepositoryFactory = $conektaQuoteRepositoryFactory;
        $this->conektaOrderApi = $conektaOrderApi;
    }

    public function generate($quoteId, $orderParams)
    {
        $conektaQuoteRepo = $this->conektaQuoteRepositoryFactory->create();

        try {
            $conektaQuote = $conektaQuoteRepo->getByid($quoteId);
        } catch (NoSuchEntityException $e) {}
        
        if (empty($conektaQuote)) {

            //Creates checkout order
            $order = $this->conektaOrderApi->create($orderParams);

            //Save map conekta order and quote
            $conektaQuote = new ConektaQuoteInterface;
            $conektaQuote->setQuoteId($quoteId);
            $conektaQuote->setConektaOrderId($order->getId());
            $conektaQuoteRepo->save($conektaQuote);
        } else {

            //If map between conekta order and quote exist, then just updated conekta order
            $order = $this->conektaOrderApi->find($conektaQuote->getConektaOrderId());

            $order->update($orderParams);
        }

        return $order;
    }
}
