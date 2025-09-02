<?php
namespace Conekta\Payments\Model;

use Conekta\Payments\Api\ConektaQuoteRepositoryInterface;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Conekta\Payments\Model\ResourceModel\ConektaQuote as ConektaQuoteResource;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;

class ConektaQuoteRepository implements ConektaQuoteRepositoryInterface
{
    /**
     * @var ConektaQuoteFactory
     */
    private ConektaQuoteFactory $conektaQuoteFactory;
    /**
     * @var ConektaQuoteResource
     */
    private $conektaQuoteResource;

    /**
     * @param ConektaQuoteFactory $conektaQuoteFactory
     * @param ConektaQuoteResource $conektaQuoteResource
     */
    public function __construct(
        ConektaQuoteFactory $conektaQuoteFactory,
        ConektaQuoteResource $conektaQuoteResource
    ) {
        $this->conektaQuoteFactory = $conektaQuoteFactory;
        $this->conektaQuoteResource = $conektaQuoteResource;
    }

    /**
     * Get by ID
     *
     * @param mixed $id
     * @return ConektaQuoteInterface
     * @throws NoSuchEntityException
     */
    public function getById($id)
    {
        $conektaQuote = $this->conektaQuoteFactory->create();
        $this->conektaQuoteResource->load($conektaQuote, $id);
        if (!$conektaQuote->getId()) {
            throw new NoSuchEntityException(__('Unable to find conekta quote with ID "%1"', $id));
        }
        return $conektaQuote;
    }

    /**
     * Get by Conekta Order ID
     *
     * @param string $conektaOrderId
     * @return ConektaQuoteInterface
     * @throws NoSuchEntityException
     */
    public function getByConektaOrderId(string $conektaOrderId)
    {
        $conektaQuote = $this->conektaQuoteFactory->create();
        $this->conektaQuoteResource->load($conektaQuote, $conektaOrderId, 'conekta_order_id');
        if (!$conektaQuote->getId()) {
            throw new NoSuchEntityException(__('Unable to find conekta quote with Order ID "%1"', $conektaOrderId));
        }
        return $conektaQuote;
    }

    /**
     * Save quote
     *
     * @param ConektaQuoteInterface $conektaQuote
     * @return ConektaQuoteInterface
     * @throws AlreadyExistsException
     */
    public function save(ConektaQuoteInterface $conektaQuote)
    {
        $this->conektaQuoteResource->save($conektaQuote);
        return $conektaQuote;
    }
}
