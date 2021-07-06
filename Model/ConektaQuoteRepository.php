<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\ConektaQuoteRepositoryInterface;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ConektaQuoteRepository implements ConektaQuoteRepositoryInterface
{

    private $conektaQuoteFactory;

    public function __construct(
        ConektaQuoteFactory $conektaQuoteFactory
    ) {
        $this->conektaQuoteFactory = $conektaQuoteFactory;
    }
 
    public function getById($id)
    {
        $conektaQuote = $this->conektaQuoteFactory->create();
        $conektaQuote->getResource()->load($conektaQuote, $id);
        if (!$conektaQuote->getId()) {
            throw new NoSuchEntityException(__('Unable to find conekta quote with ID "%1"', $id));
        }
        return $conektaQuote;
    }
    
    public function save(ConektaQuoteInterface $conektaQuote)
    {
        $conektaQuote->getResource()->save($conektaQuote);
        return $conektaQuote;
    }
}
