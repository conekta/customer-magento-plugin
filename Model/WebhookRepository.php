<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
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
    private OrderManagementInterface $orderManagement;
    private OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory;

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
        ConektaSalesOrderInterface $conektaOrderSalesInterface,
        OrderManagementInterface $orderManagement,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
    ) {
        $this->orderInterface = $orderInterface;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaOrderSalesInterface = $conektaOrderSalesInterface;
        $this->orderManagement = $orderManagement;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
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
            //$order->setState(Order::STATE_CANCELED);
            //$order->setStatus(Order::STATE_CANCELED);
            $this->canCancel($order);
            $this->_conektaLogger->info('WebhookRepository :: after can cancel',
                [
                    "can" => $order->canCancel(),
                    "isCanceled" => $order->isCanceled(),
                    "canUnhold" => $order->canUnhold() ,
                    "canUnhold_inside" => $order->getActionFlag("unhold") ,
                    "canUnhold_inside_2" => $order->getState() ,
                    "isPaymentReview" => $order->isPaymentReview(),
                    "canReviewPayment" => $order->canReviewPayment(),
                    "canFetchPaymentReviewUpdate" => $order->canFetchPaymentReviewUpdate(),
                    "getActionFlag" => $order->getActionFlag("cancel"),
                //{
                    //"can":false,
                //"isCanceled":false,
                //"canUnhold":false,
                //"isPaymentReview":true,
                //"canReviewPayment":false,
                //"canFetchPaymentReviewUpdate":false,
                //"getActionFlag":null} []
                    /*
                     protected function _canVoidOrder()
                    {
                        return !($this->isCanceled() || $this->canUnhold() || $this->isPaymentReview());
                    }*/
                                ]
            );
            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);
            $order->cancel()->save();
/*
            $canceled = $this->orderManagement->cancel($order->getId());
            if ($canceled) {
                $history = $this->orderStatusHistoryFactory->create();
                $history->setParentId($order->getId()); // Establece el ID de la orden
                $history->setComment("Order Expired");
                $history->setIsCustomerNotified(true);
                $this->orderManagement->addComment($order->getId(), $history );
            }*/
            $this->_conektaLogger->info('WebhookRepository :: end can cancel', ["canceled" =>$order->isCanceled()]);

        }
        
        $this->_conektaLogger->info('WebhookRepository :: orderExpiredProcess: Order has been Canceled');
    }
    public function canvoid(Order $order) :bool{
        return !($order->isCanceled() || $order->canUnhold() || $order->isPaymentReview());
    }
    public function canCancel(Order $order): bool
    {
        if (!$this->canvoid($order)) {
            $this->_conektaLogger->info('canvoid');

            return false;
        }
        if ($order->canUnhold()) {
            $this->_conektaLogger->info('canUnhold');

            return false;
        }
        if (!$order->canReviewPayment() && $order->canFetchPaymentReviewUpdate()) {
            $this->_conektaLogger->info('$order->canReviewPayment() && $order->canFetchPaymentReviewUpdate()');
            return false;
        }

        $allInvoiced = true;
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice()) {
                $allInvoiced = false;
                break;
            }
        }

        if ($allInvoiced) {
            $this->_conektaLogger->info('$allInvoiced');
            return false;
        }

        $state = $order->getState();
        if ($order->isCanceled() || $state === 'complete' || $state === 'closed') {
            $this->_conektaLogger->info('if $state');
            return false;
        }

        if ($order->getActionFlag('cancel') === false) {
            $this->_conektaLogger->info('getActionFlag');
            return false;
        }

        return true;
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

        $order = $this->findByMetadataOrderId($body);
        
        $charge = $body['data']['object'];
        if (!isset($charge['payment_status']) || $charge['payment_status'] !== "paid") {
            throw new LocalizedException(__('Missing order information'));
        }
        
        if (!$order->getId()) {
            $message = 'The order does not exists';
            $this->_conektaLogger->error(
                'WebhookRepository :: execute - ' . $message
            );
            throw new LocalizedException(__($message));
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
                __('Notified customer about invoice creation #%1.', $invoice->getId())
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
}
