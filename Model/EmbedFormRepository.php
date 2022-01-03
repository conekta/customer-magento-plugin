<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Conekta\Payments\Api\EmbedFormRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Conekta\Order as ConektaOrderApi;
use Conekta\ParameterValidationError;
use Conekta\Payments\Exception\ConektaException;

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

    /**
     * @param array $orderParams
     * @return void
     * @throws ConektaException
     */
    private function validateOrderParameters($orderParameters, $orderTotal)
    {
        //Currency
        if (strtoupper($orderParameters['currency']) !== 'MXN') {
            throw new ConektaException(
                __('Este medio de pago no acepta moneda extranjera')
            );
        }

        //Minimum amount per quote
        $total = 0;
        foreach ($orderParameters['line_items'] as $lineItem) {
            $total += $lineItem['unit_price']*$lineItem['quantity'];
        }
        
        if ($total < ConektaQuoteInterface::MINIMUM_AMOUNT_PER_QUOTE*100) {
            throw new ConektaException(
                __('Para utilizar este medio de pago
                debe ingresar una compra superior a $'.ConektaQuoteInterface::MINIMUM_AMOUNT_PER_QUOTE)
            );
        }

        //Shipping contact validations
        if (strlen($orderParameters["shipping_contact"]["phone"]) < 10 ||
            strlen($orderParameters["shipping_contact"]["address"]["phone"]) < 10
        ) {
            throw new ConektaException(__('Télefono no válido. 
                El télefono debe tener al menos 10 carácteres. 
                Los caracteres especiales se desestimaran, solo se puede ingresar como 
                primer carácter especial: +'));
        }

        if (strlen($orderParameters["shipping_contact"]["address"]["postal_code"]) !== 5) {
            throw new ConektaException(__("Código Postal invalido. Debe tener 5 dígitos"));
        }

        //Oxxo validations
        if (in_array('cash', $orderParameters["checkout"]["allowed_payment_methods"]) &&
            $orderTotal > 10000
        ) {
            throw new ConektaException(__('El monto máximo para pagos con Oxxo es de $10.000'));
        }
    }

    /**
     * @param int $quoteId
     * @param array $orderParams
     * @param float $orderTotal
     * @return \Conekta\Order
     * @throws ConektaException
     */
    public function generate($quoteId, $orderParams, $orderTotal)
    {

        //Validate params
        $this->validateOrderParameters($orderParams, $orderTotal);

        $conektaQuoteRepo = $this->conektaQuoteRepositoryFactory->create();

        $conektaQuote = null;
        $conektaOrder = null;
        $hasToCreateNewOrder = false;
        try {
            $conektaQuote = $conektaQuoteRepo->getByid($quoteId);
            $conektaOrder = $this->conektaOrderApi->find($conektaQuote->getConektaOrderId());

            if (!empty($conektaOrder)) {

                $chekoutParams = $orderParams['checkout'];
                $conektaChekout = $conektaOrder->checkout;
                $conektaCheckoutMonthlyInstallmentsOptions = (array)$conektaChekout->monthly_installments_options;
                if (!empty($conektaOrder->payment_status) ||
                    time() >= $conektaOrder->checkout->expires_at ||
                    
                    //detect changes in checkout params
                    $chekoutParams['allowed_payment_methods'] != (array)$conektaChekout->allowed_payment_methods ||
                    $chekoutParams['monthly_installments_enabled'] != $conektaChekout->monthly_installments_enabled ||
                    $chekoutParams['monthly_installments_options'] != $conektaCheckoutMonthlyInstallmentsOptions ||
                    $chekoutParams['on_demand_enabled'] != $conektaChekout->on_demand_enabled ||
                    $chekoutParams['force_3ds_flow'] != $conektaChekout->force_3ds_flow
                ) {
                    $hasToCreateNewOrder = true;
                }
                
            }
        } catch (NoSuchEntityException $e) {
            $conektaQuote = null;
            $conektaOrder = null;
            $hasToCreateNewOrder = true;
        }

        try {
            /**
             * Creates new conekta order-checkout if:
             *   1- Not exist row in map table conekta_quote
             *   2- Exist row in map table and:
             *      2.1- conekta order has payment_status OR
             *      2.2- conekta order checkout has expired
             *      2.3- checkout parameters has changed
             */
            if ($hasToCreateNewOrder) {
                $this->_conektaLogger->info('EmbedFormRepository::generate Creates conekta order', $orderParams);
                //Creates checkout order
                $conektaOrder = $this->conektaOrderApi->create($orderParams);
                
                //Save map conekta order and quote
                $conektaQuote = $this->conektaQuoteFactory->create();
                $conektaQuote->setQuoteId($quoteId);
                $conektaQuote->setConektaOrderId($conektaOrder['id']);
                $conektaQuoteRepo->save($conektaQuote);
            } else {
                $this->_conektaLogger->info('EmbedFormRepository::generate  Updates conekta order', $orderParams);
                //If map between conekta order and quote exist, then just updated conekta order
                $conektaOrder = $this->conektaOrderApi->find($conektaQuote->getConektaOrderId());
                
                unset($orderParams['customer_info']);
                $conektaOrder->update($orderParams);
            }

            return $conektaOrder;
        } catch (ParameterValidationError $e) {
            $this->_conektaLogger->error('EmbedFormRepository::generate Error: ' . $e->getMessage());
            throw new ConektaException(__($e->getMessage()));
        }
    }
}
