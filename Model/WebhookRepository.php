<?php

namespace Conekta\Payments\Model;

use Conekta\ApiException;
use Conekta\Payments\Api\ConektaApiClient;
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
    private const CONEKTA_STATUS_PAID = 'paid';
    private const CONEKTA_CLOSED_STATUSES = ['expired', 'canceled'];
    private const PAYABLE_ORDER_STATES = [
        Order::STATE_NEW,
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_PAYMENT_REVIEW,
        Order::STATE_PROCESSING,
    ];
    private const AMOUNT_TOLERANCE_IN_CENTS = 100;

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
    private ConektaApiClient $conektaApiClient;

    /**
     * @param OrderInterface $orderInterface
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param Transaction $transaction
     * @param ConektaLogger $conektaLogger
     * @param ConektaSalesOrderInterface $conektaOrderSalesInterface
     * @param ConektaApiClient $conektaApiClient
     */
    public function __construct(
        OrderInterface $orderInterface,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        ConektaLogger $conektaLogger,
        ConektaSalesOrderInterface $conektaOrderSalesInterface,
        ConektaApiClient $conektaApiClient
    ) {
        $this->orderInterface = $orderInterface;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaOrderSalesInterface = $conektaOrderSalesInterface;
        $this->conektaApiClient = $conektaApiClient;
    }

    public function fetchConektaOrder(array $body): array
    {
        if (!isset($body['data']['object']['id'])) {
            throw new LocalizedException(__('Missing order information'));
        }

        $conektaOrder = $this->conektaApiClient->getOrderByID((string)$body['data']['object']['id']);

        return json_decode((string)json_encode($conektaOrder), true) ?? [];
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
            throw new LocalizedException(__('Missing order information'));
        }
        $conektaOrderId = $body['data']['object']['id'];
        
        $this->_conektaLogger->info('WebhookRepository :: findByMetadataOrderId started', [
            'order_id' => $conektaOrderId
        ]);

        $conektaSalesOrder = $this->conektaOrderSalesInterface->loadByConektaOrderId($conektaOrderId);

        return $this->orderInterface->loadByIncrementId($conektaSalesOrder->getIncrementOrderId());
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
            $conektaOrder = $this->fetchConektaOrder($body);
            $conektaStatus = $conektaOrder['payment_status'] ?? null;
            if (!in_array($conektaStatus, self::CONEKTA_CLOSED_STATUSES, true)) {
                $this->_conektaLogger->error('WebhookRepository :: expireOrder - Conekta order is not expired or canceled, order not updated', [
                    'order_id' => $order->getIncrementId(),
                    'conekta_order_id' => $conektaOrder['id'] ?? null,
                    'conekta_payment_status' => $conektaStatus,
                ]);
                return;
            }

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
     * @throws ApiException
     * @throws Exception
     */
    public function payOrder($body)
    {

        $order = $this->findByMetadataOrderId($body);

        if (!$order->getId()) {
            $message = 'The order does not exists';
            $this->_conektaLogger->error(
                'WebhookRepository :: execute - ' . $message
            );
            throw new EntityNotFoundException(__($message));
        }

        $conektaOrder = $this->fetchConektaOrder($body);
        if (!$this->canMarkAsPaid($order, $conektaOrder)) {
            return;
        }

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);

        $order->addCommentToStatusHistory("Payment received successfully")
            ->setIsCustomerNotified(true);

        $order->save();
        $this->_conektaLogger->info('WebhookRepository :: execute - Order status updated');

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->save();
        $transactionSave = $this->transaction->addObject(
            $invoice
        )->addObject(
            $invoice->getOrder()
        );
        $transactionSave->save();

        $this->_conektaLogger->info('WebhookRepository :: execute - The invoice to be created');

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
            $this->_conektaLogger->error($e->getMessage());
        }
    }

    private function canMarkAsPaid(Order $order, array $conektaOrder): bool
    {
        $context = [
            'order_id' => $order->getIncrementId(),
            'order_state' => $order->getState(),
            'conekta_order_id' => $conektaOrder['id'] ?? null,
            'conekta_payment_status' => $conektaOrder['payment_status'] ?? null,
        ];

        if (($conektaOrder['payment_status'] ?? null) !== self::CONEKTA_STATUS_PAID) {
            $this->_conektaLogger->error('WebhookRepository :: payOrder - Conekta order is not paid, order not updated', $context);
            return false;
        }

        $paidAmount = (int)($conektaOrder['amount'] ?? 0);
        $expectedAmount = (int)round((float)$order->getGrandTotal() * 100);
        if ($paidAmount < $expectedAmount - self::AMOUNT_TOLERANCE_IN_CENTS) {
            $this->_conektaLogger->error(
                'WebhookRepository :: payOrder - Conekta paid amount is lower than order total, order not updated',
                array_merge($context, ['paid_amount' => $paidAmount, 'expected_amount' => $expectedAmount])
            );
            return false;
        }

        if (!in_array($order->getState(), self::PAYABLE_ORDER_STATES, true) || $order->hasInvoices()) {
            $this->_conektaLogger->error('WebhookRepository :: payOrder - Order is not payable or already invoiced, order not updated', $context);
            return false;
        }

        return true;
    }
}
