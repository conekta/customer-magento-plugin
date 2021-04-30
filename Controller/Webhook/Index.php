<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Logger\Logger as ConektaLogger;
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
        $httpBadRequestCode = 400;
        $httpOkRequestCode = 200;

        $resultRaw = $this->resultRawFactory->create();

        try {
            $body = $this->helper->jsonDecode($this->getRequest()->getContent());
        } catch (\Exception $e) {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }

        if (!$body || $this->getRequest()->getMethod() !== 'POST') {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }

        $this->_conektaLogger->info('Controller Index :: execute body json', [$body]);
        if (isset($body['data']['object'])) {
            $charge = $body['data']['object'];
            if (isset($charge['payment_status']) && $charge['payment_status'] === "paid") {
                try {
                    $order = $this->orderInterface->loadByIncrementId($charge['metadata']['checkout_id']);
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

                    return $resultRaw->setHttpResponseCode($httpOkRequestCode);
                } catch (\Exception $e) {
                    $this->_conektaLogger->critical(
                        'Controller Index :: execute - Error processing webhook notification',
                        ['exception' => $e]
                    );
                    $this->logger->error(__('[Conekta]: Error processing webhook notification'));
                    $this->logger->debug(['exception' => $e]);
                    return $resultRaw->setHttpResponseCode($httpBadRequestCode);
                }
            } else {
                return $resultRaw->setHttpResponseCode($httpBadRequestCode);
            }
        } else {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }
    }
}
