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
    private $conektaQuoteFactory;
    private $conektaQuoteRepositoryFactory;

    public function __construct(
        ConektaLogger $conektaLogger,
        ConektaQuoteInterface $conektaQuoteInterface,
        ConektaOrderApi $conektaOrderApi,
        ConektaQuoteFactory $conektaQuoteFactory,
        ConektaQuoteRepositoryFactory $conektaQuoteRepositoryFactory
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->conektaQuoteInterface = $conektaQuoteInterface;
        $this->conektaQuoteRepositoryFactory = $conektaQuoteRepositoryFactory;
        $this->conektaQuoteFactory = $conektaQuoteFactory;
        $this->conektaOrderApi = $conektaOrderApi;
    }

    public function generate($quoteId, $orderParams)
    {
        $conektaQuoteRepo = $this->conektaQuoteRepositoryFactory->create();

        $conektaQuote = null;
        $conektaOrder = null;
        try {
            $conektaQuote = $conektaQuoteRepo->getByid($quoteId);
            $conektaOrder = $this->conektaOrderApi->find($conektaQuote->getConektaOrderId());
        } catch (NoSuchEntityException $e) {
            $conektaQuote = null;
            $conektaOrder = null;
        }
        
        /**
         * Creates new conekta order-checkout if:
         *   1- Not exist row in map table conekta_quote
         *   2- Exist row in map table and conekta order has payment_status
         */
        if (empty($conektaQuote) ||
            (!empty($conektaOrder) && !empty($conektaOrder->payment_status))
        ) {
            $this->_conektaLogger->info('Creates conekta order');
            //Creates checkout order
            $conektaOrder = $this->conektaOrderApi->create($orderParams);
            
            //Save map conekta order and quote
            $conektaQuote = $this->conektaQuoteFactory->create();
            $conektaQuote->setQuoteId($quoteId);
            $conektaQuote->setConektaOrderId($conektaOrder['id']);
            $conektaQuoteRepo->save($conektaQuote);
        } else {
            $this->_conektaLogger->info('Updates conekta order');
            //If map between conekta order and quote exist, then just updated conekta order
            $conektaOrder = $this->conektaOrderApi->find($conektaQuote->getConektaOrderId());
            
            //TODO detect if checkout config has been modified
            unset($orderParams['customer_info']);
            $conektaOrder->update($orderParams);
        }

        return $conektaOrder;
    }
}
