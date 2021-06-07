<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Json\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;

use Magento\Framework\App\Request\InvalidRequestException;

class Index extends Action implements CsrfAwareActionInterface
{

    private const EVENT_ORDER_PAID = 'order.paid';
    private const EVENT_ORDER_EXPIRED = 'order.expired';

    private const HTTP_BAD_REQUEST_CODE = 400;
    private const HTTP_OK_REQUEST_CODE = 200;

    protected $orderInterface;

    protected $resultJsonFactory;

    protected $resultRawFactory;

    protected $helper;

    protected $invoiceService;

    protected $invoiceSender;

    protected $transaction;

    private $logger;

    private $_conektaLogger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        OrderInterface $orderInterface,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        Transaction $transaction,
        Logger $logger,
        ConektaLogger $conektaLogger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->orderInterface = $orderInterface;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;
        $this->logger = $logger;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Controller Index :: __construct');
    }

    /** * @inheritDoc */
    public function createCsrfValidationException(RequestInterface $request): ?       InvalidRequestException
    {
        return null;
    }
    /** * @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $this->_conektaLogger->info('Controller Index :: execute');

        $body = null;

        $resultRaw = $this->resultRawFactory->create();
        $response = self::HTTP_BAD_REQUEST_CODE;

        try {
            $body = $this->helper->jsonDecode($this->getRequest()->getContent());
        } catch (\Exception $e) {
            return $resultRaw->setHttpResponseCode($response);
        }

        if (!$body || $this->getRequest()->getMethod() !== 'POST') {
            return $resultRaw->setHttpResponseCode($response);
        }

        $this->_conektaLogger->info('Controller Index :: execute body json', [$body]);

        $event = $body['type'];
        
        switch($event){
            case self::EVENT_ORDER_PAID:
                $response = $this->orderPaidProcess($body);
                break;
            case self::EVENT_ORDER_EXPIRED:
                $response = $this->orderExpiredProcess($body);
                break;
        }


        return $resultRaw->setHttpResponseCode($response);
        
    }

    private function orderExpiredProcess($body){
        $this->_conektaLogger->info('Controller Index :: orderExpiredProcess');

        $responseCode = self::HTTP_BAD_REQUEST_CODE;
        try {
            if (
                !isset($body['data']['object']) &&
                !isset($body['data']['object']['metadata']) &&
                !isset($body['data']['object']['metadata']['order_id'])
            ){
                throw new Exception(_('Missing order information'));
            }
            
            $orderId = $body['data']['object']['metadata']['order_id'];
            $order = $this->orderInterface->loadByIncrementId($orderId);

            if(!$order->getId()){
                throw new Exception(_('We could not locate the order in the store'));
            }

            //Only update order status if is Pending
            if(
                $order->getState() === Order::STATE_PENDING_PAYMENT ||
                $order->getState() === Order::STATE_PAYMENT_REVIEW
            ){
                $order->setSate(Order::STATE_CANCELED);
                $order->setStatus(Order::STATE_CANCELED);

                $order->addStatusHistoryComment("Order Expired")
                        ->setIsCustomerNotified(true);

                $order->save();
            }
            
            $this->_conektaLogger->info('Controller Index :: orderExpiredProcess: Order Canceled');

            //everything is ok
            $responseCode = self::HTTP_OK_REQUEST_CODE;
        }catch(Exception $e){
            $this->_conektaLogger->error('Controller Index :: orderExpiredProcess: ' . $e->getMessage());

            $responseCode = self::HTTP_BAD_REQUEST_CODE;
        }
        

        return $responseCode;
    }

    private function orderPaidProcess($body){

        if (!isset($body['data']['object'])){
            return self::HTTP_BAD_REQUEST_CODE;
        }
        
        $charge = $body['data']['object'];
        if (!isset($charge['payment_status']) || $charge['payment_status'] !== "paid"){
            return self::HTTP_BAD_REQUEST_CODE;
        }

        try {
            $order = $this->orderInterface->loadByIncrementId($charge['metadata']['order_id']);
            if (!$order->getId()) {
                $this->_conektaLogger->error(
                    'Controller Index :: execute - The order does not allow an invoice to be created'
                );
                return;
            }
            $order->setSate(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);

            $order->addStatusHistoryComment("Payment received successfully")
                ->setIsCustomerNotified(true);

            $order->save();
            $this->_conektaLogger->info('Controller Index :: execute - Order status updated');

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $this->_conektaLogger->info('Controller Index :: execute - The invoice to be created');

            try {
                $this->invoiceSender->send($invoice);
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice creation #%1.', $invoice->getId())
                )
                    ->setIsCustomerNotified(true)
                    ->save();
                $this->_conektaLogger->info(
                    'Controller Index :: execute - Notified customer about invoice creation'
                );
            } catch (\Exception $e) {
                $this->_conektaLogger->error(
                    'Controller Index :: execute - We can\'t send the invoice email right now.'
                );
                $this->logger->error(__('[Conekta]: We can\'t send the invoice email right now.'));
            }

            return self::HTTP_OK_REQUEST_CODE;
        } catch (\Exception $e) {
            $this->_conektaLogger->critical(
                'Controller Index :: execute - Error processing webhook notification',
                ['exception' => $e]
            );
            $this->logger->error(__('[Conekta]: Error processing webhook notification'));
            $this->logger->debug(['exception' => $e]);
            return self::HTTP_BAD_REQUEST_CODE;
        }

    }
}
