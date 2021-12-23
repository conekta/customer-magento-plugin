<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\ConektaQuoteRepositoryInterface;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Conekta\Payments\Model\ResourceModel\ConektaQuote as ConektaQuoteResource;
use Magento\Framework\Exception\NoSuchEntityException;

class ConektaQuoteRepository implements ConektaQuoteRepositoryInterface
{

    private $conektaQuoteFactory;
    private $conektaQuoteResource;

    public function __construct(
        ConektaQuoteFactory $conektaQuoteFactory,
        ConektaQuoteResource $conektaQuoteResource
    ) {
        $this->conektaQuoteFactory = $conektaQuoteFactory;
        $this->conektaQuoteResource = $conektaQuoteResource;
    }
 
    public function getById($id)
    {
        $conektaQuote = $this->conektaQuoteFactory->create();
        $this->conektaQuoteResource->load($conektaQuote, $id);
        if (!$conektaQuote->getId()) {
            throw new NoSuchEntityException(__('Unable to find conekta quote with ID "%1"', $id));
        }
        return $conektaQuote;
    }
    
    public function save(ConektaQuoteInterface $conektaQuote)
    {
        $this->conektaQuoteResource->save($conektaQuote);
        return $conektaQuote;
    }
}
