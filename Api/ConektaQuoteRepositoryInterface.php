<?php
namespace Conekta\Payments\Api;

use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface ConektaQuoteRepositoryInterface
{
     /**
      * Get Conekta quote by ID
      *
      * @param int $id
      * @return ConektaQuoteInterface
      * @throws NoSuchEntityException
      */
    public function getById($id);

    /**
     * Save Conekta quote
     *
     * @param ConektaQuoteInterface $conektaQuote
     * @return ConektaQuoteInterface
     */
    public function save(ConektaQuoteInterface $conektaQuote);
}
