<?php
namespace Conekta\Payments\Api;

use Conekta\Payments\Api\Data\ConektaQuoteInterface;

interface ConektaQuoteRepositoryInterface
{

     /**
      * @param int $id
      * @return ConektaQuoteInterface
      * @throws \Magento\Framework\Exception\NoSuchEntityException
      */
    public function getById($id);
 
    /**
     * @param  $conektaQuote
     * @return ConektaQuoteInterface
     */
    public function save(ConektaQuoteInterface $conektaQuote);
}
