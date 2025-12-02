<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Exception\EntityNotFoundException;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;

class WebhookRepository
{
    /**
     * @var OrderInterface
     */
    protected OrderInterface $orderInterface;
    /**
     * @var InvoiceService
     */
    protected InvoiceService $invoiceService;
    /**
     * @var InvoiceSender
     */
    protected InvoiceSender $invoiceSender;
    /**
     * @var Transaction
     */
    protected Transaction $transaction;
    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;
    /**
     * @var ConektaSalesOrderInterface
     */
    private ConektaSalesOrderInterface $conektaOrderSalesInterface;

    /**
     * @param OrderInterface $orderInterface
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     * @param ConektaLogger $conektaLogger
     * @param ConektaSalesOrderInterface $conektaOrderSalesInterface
     */
    public function __construct(
        OrderInterface $orderInterface,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        ConektaLogger $conektaLogger,
        ConektaSalesOrderInterface $conektaOrderSalesInterface
    ) {
        $this->orderInterface = $orderInterface;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaOrderSalesInterface = $conektaOrderSalesInterface;
    }

    /**
     * Find store order in body. If keys['data']['object']['metadata']['order_id'] does not exist, throws an Exception
     *
     * @param array $body
     * @return Order
     * @throws LocalizedException
     */
    public function findByMetadataOrderId(array $body): Order
    {
        if (!isset($body['data']['object']) ||
            !isset($body['data']['object']['id'])
        ) {
            $this->_conektaLogger->error('WebhookRepository :: Missing order information in body', [
                'body_keys' => array_keys($body)
            ]);
            throw new LocalizedException(__('Missing order information'));
        }
        $conektaOrderId = $body['data']['object']['id'];
        
        $this->_conektaLogger->info('WebhookRepository :: findByMetadataOrderId started', [
            'conekta_order_id' => $conektaOrderId
        ]);

        try {
            $conektaSalesOrder = $this->conektaOrderSalesInterface->loadByConektaOrderId($conektaOrderId);
            
            $this->_conektaLogger->info('WebhookRepository :: Conekta sales order loaded', [
                'increment_order_id' => $conektaSalesOrder->getIncrementOrderId()
            ]);
            
            $order = $this->orderInterface->loadByIncrementId($conektaSalesOrder->getIncrementOrderId());
            
            if ($order->getId()) {
                $this->_conektaLogger->info('WebhookRepository :: Order found successfully', [
                    'order_id' => $order->getId(),
                    'increment_id' => $order->getIncrementId()
                ]);
            } else {
                $this->_conektaLogger->warning('WebhookRepository :: Order not found by increment_id', [
                    'increment_id' => $conektaSalesOrder->getIncrementOrderId()
                ]);
            }
            
            return $order;
        } catch (Exception $e) {
            $this->_conektaLogger->error('WebhookRepository :: Error loading order', [
                'error' => $e->getMessage(),
                'conekta_order_id' => $conektaOrderId
            ]);
            throw $e;
        }
    }

    /**
     * Finds order by metadata id in $body If state == Pending, set as CANCELED If order not exists, throws exception
     *
     * @param array $body
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function expireOrder(array $body)
    {
        $this->_conektaLogger->info('WebhookRepository :: expireOrder started');

        $order = $this->findByMetadataOrderId($body);

        if (!$order->getId()) {
            throw new LocalizedException(__('We could not locate the order in the store'));
        }

        //Only update order status if order is Pending
        if ($order->getState() === Order::STATE_PENDING_PAYMENT ||
            $order->getState() === Order::STATE_PAYMENT_REVIEW
        ) {
            if ($order->canCancel()) {
                $order->cancel();
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);
                $order->addCommentToStatusHistory("Order Expired")
                    ->setIsCustomerNotified(true);

                $order->save();

                $this->_conektaLogger->info('La orden con ID $order_id ha sido cancelada exitosamente', ["id"=>$order->getId()]);

            } else {
                $this->_conektaLogger->info("No se puede cancelar la orden con ID  en su estado actual.",["id"=>$order->getId()]);
            }
        }
    }

    /**
     * Pay Order
     *
     * @param mixed $body
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function payOrder($body)
    {
        $this->_conektaLogger->info('WebhookRepository :: payOrder started');

        try {
            $order = $this->findByMetadataOrderId($body);
            
            $charge = $body['data']['object'];
            if (!isset($charge['payment_status']) || $charge['payment_status'] !== "paid") {
                $this->_conektaLogger->error('WebhookRepository :: Invalid payment status', [
                    'payment_status' => $charge['payment_status'] ?? 'not set'
                ]);
                throw new LocalizedException(__('Missing order information'));
            }
            
            if (!$order->getId()) {
                $message = 'The order does not exists';
                $this->_conektaLogger->error(
                    'WebhookRepository :: execute - ' . $message
                );
                throw new EntityNotFoundException(__($message));
            }

            $this->_conektaLogger->info('WebhookRepository :: Updating order state to processing', [
                'order_id' => $order->getId()
            ]);

            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);

            $order->addCommentToStatusHistory("Payment received successfully")
                ->setIsCustomerNotified(true);

            $order->save();
            $this->_conektaLogger->info('WebhookRepository :: execute - Order status updated');

            $this->_conektaLogger->info('WebhookRepository :: Preparing invoice');
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();

            $this->_conektaLogger->info('WebhookRepository :: execute - Invoice created successfully');

            try {
                $this->invoiceSender->send($invoice);
                $order->addCommentToStatusHistory(
                    __('Notified customer about invoice creation')
                )
                    ->setIsCustomerNotified(true)
                    ->save();
                $this->_conektaLogger->info(
                    'WebhookRepository :: execute - Notified customer about invoice creation'
                );
            } catch (Exception $e) {
                $this->_conektaLogger->error('WebhookRepository :: Error sending invoice email: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->_conektaLogger->error('WebhookRepository :: Error in payOrder: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
