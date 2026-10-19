<?php
namespace Conekta\Payments\Test\Unit\Controller\Webhook;

use Conekta\ApiException;
use Conekta\Model\ChargeResponse;
use Conekta\Model\OrderResponse;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Controller\Webhook\Index;
use Conekta\Payments\Helper\Data as ConektaData;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Conekta\Payments\Service\MissingOrders;
use Laminas\Http\Response;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Json\Helper\Data;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebhookFlowTest extends TestCase
{
    private const CONEKTA_ORDER_ID = 'ord_2zFlow123';
    private const INCREMENT_ID = '000000123';
    private const RECOVERED_INCREMENT_ID = '000000777';
    private const API_QUOTE_ID = '999';
    private const ORDER_TOTAL = 1599.00;
    private const ORDER_TOTAL_IN_CENTS = 159900;

    private array $mappings;
    private array $orders;
    private array $orderStates;
    private array $invoiceCounts;
    private array $canceledOrders;
    private array $apiOrders;
    private array $requestedQuoteIds;
    private array $loggedErrors;
    private int $submittedQuotes;
    private ?int $httpCode;
    private ?string $responseBody;
    private string $lookedUpConektaOrderId;
    private MockObject $request;

    protected function setUp(): void
    {
        $this->mappings = [];
        $this->orders = [];
        $this->orderStates = [];
        $this->invoiceCounts = [];
        $this->canceledOrders = [];
        $this->apiOrders = [];
        $this->requestedQuoteIds = [];
        $this->loggedErrors = [];
        $this->submittedQuotes = 0;
        $this->httpCode = null;
        $this->responseBody = null;
        $this->lookedUpConektaOrderId = '';
    }

    public function testForgedPaidWebhookDoesNotPayPendingCashOrder(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('pending_payment', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'cash_payment', 'paid', 100));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(0, $this->invoiceCounts[self::INCREMENT_ID]);
        $this->assertLoggedError('Conekta order is not paid');
    }

    public function testPaidCashOrderIsProcessedAndInvoiced(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'cash_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_PROCESSING, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(1, $this->invoiceCounts[self::INCREMENT_ID]);
    }

    public function testPaidBankTransferOrderIsProcessedAndInvoiced(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'bank_transfer_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_PROCESSING, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(1, $this->invoiceCounts[self::INCREMENT_ID]);
    }

    public function testPaidPayByBankOrderIsProcessedAndInvoiced(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'pay_by_bank_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_PROCESSING, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(1, $this->invoiceCounts[self::INCREMENT_ID]);
    }

    public function testPaidCardOrderAuthorizedInProcessingIsInvoiced(): void
    {
        $this->givenStoreOrder(Order::STATE_PROCESSING);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'card_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(0, $this->submittedQuotes);
        $this->assertSame(Order::STATE_PROCESSING, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(1, $this->invoiceCounts[self::INCREMENT_ID]);
    }

    public function testReplayedPaidWebhookDoesNotInvoiceTwice(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);
        $body = $this->webhook('order.paid', 'cash_payment', 'paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($body);
        $this->post($body);

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(1, $this->invoiceCounts[self::INCREMENT_ID]);
        $this->assertLoggedError('already invoiced');
    }

    public function testUnderpaidConektaOrderIsNotProcessed(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('paid', 10000);

        $this->post($this->webhook('order.paid', 'cash_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(0, $this->invoiceCounts[self::INCREMENT_ID]);
        $this->assertLoggedError('lower than order total');
    }

    public function testPaidWebhookDoesNotReopenCanceledOrder(): void
    {
        $this->givenStoreOrder(Order::STATE_CANCELED);
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.paid', 'cash_payment', 'paid', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame(Order::STATE_CANCELED, $this->orderStates[self::INCREMENT_ID]);
        $this->assertSame(0, $this->invoiceCounts[self::INCREMENT_ID]);
    }

    public function testMissingCardOrderIsRecoveredFromConektaApiAndInvoiced(): void
    {
        $this->givenConektaApiOrder('paid', self::ORDER_TOTAL_IN_CENTS);
        $body = $this->webhook('order.paid', 'card_payment', 'paid', self::ORDER_TOTAL_IN_CENTS);
        $body['data']['object']['metadata']['quote_id'] = '666';

        $this->post($body);

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame([self::API_QUOTE_ID], $this->requestedQuoteIds);
        $this->assertSame(1, $this->submittedQuotes);
        $this->assertSame(Order::STATE_PROCESSING, $this->orderStates[self::RECOVERED_INCREMENT_ID]);
        $this->assertSame(1, $this->invoiceCounts[self::RECOVERED_INCREMENT_ID]);
    }

    public function testInventedConektaOrderIdDoesNotCreateOrPayAnyOrder(): void
    {
        $body = $this->webhook('order.paid', 'card_payment', 'paid', self::ORDER_TOTAL_IN_CENTS);
        $body['data']['object']['metadata']['quote_id'] = self::API_QUOTE_ID;

        $this->post($body);

        $this->assertSame(Response::STATUS_CODE_500, $this->httpCode);
        $this->assertSame('Internal Server Error', json_decode((string)$this->responseBody, true)['error']);
        $this->assertSame([], $this->requestedQuoteIds);
        $this->assertSame(0, $this->submittedQuotes);
        $this->assertSame([], $this->invoiceCounts);
    }

    public function testPendingPaymentWebhookRecoversMissingCashOrderFromConektaApi(): void
    {
        $this->givenConektaApiOrder('pending_payment', self::ORDER_TOTAL_IN_CENTS, 'cash_payment');
        $body = $this->webhook('order.pending_payment', 'cash_payment', 'pending_payment', self::ORDER_TOTAL_IN_CENTS);
        $body['data']['object']['metadata']['quote_id'] = '666';

        $this->post($body);

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertSame([self::API_QUOTE_ID], $this->requestedQuoteIds);
        $this->assertSame(1, $this->submittedQuotes);
        $this->assertSame(0, $this->invoiceCounts[self::RECOVERED_INCREMENT_ID]);
    }

    public function testForgedExpiredWebhookDoesNotCancelPendingOrder(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('pending_payment', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.expired', 'cash_payment', 'expired', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertArrayNotHasKey(self::INCREMENT_ID, $this->canceledOrders);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderStates[self::INCREMENT_ID]);
        $this->assertLoggedError('not expired or canceled');
    }

    public function testExpiredWebhookCancelsPendingOrderConfirmedByConekta(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('expired', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.expired', 'cash_payment', 'expired', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertTrue($this->canceledOrders[self::INCREMENT_ID]);
        $this->assertSame(Order::STATE_CANCELED, $this->orderStates[self::INCREMENT_ID]);
    }

    public function testCanceledWebhookCancelsPendingOrderConfirmedByConekta(): void
    {
        $this->givenStoreOrder(Order::STATE_PENDING_PAYMENT);
        $this->givenConektaApiOrder('canceled', self::ORDER_TOTAL_IN_CENTS);

        $this->post($this->webhook('order.canceled', 'cash_payment', 'canceled', self::ORDER_TOTAL_IN_CENTS));

        $this->assertSame(Response::STATUS_CODE_200, $this->httpCode);
        $this->assertTrue($this->canceledOrders[self::INCREMENT_ID]);
        $this->assertSame(Order::STATE_CANCELED, $this->orderStates[self::INCREMENT_ID]);
    }

    private function givenStoreOrder(string $state): void
    {
        $this->mappings[self::CONEKTA_ORDER_ID] = self::INCREMENT_ID;
        $this->orders[self::INCREMENT_ID] = $this->buildStoreOrder(self::INCREMENT_ID, $state);
    }

    private function givenConektaApiOrder(string $paymentStatus, int $amount, string $paymentMethod = 'card_payment'): void
    {
        $this->apiOrders[self::CONEKTA_ORDER_ID] = [
            'id' => self::CONEKTA_ORDER_ID,
            'payment_status' => $paymentStatus,
            'amount' => $amount,
            'currency' => 'MXN',
            'customer_info' => ['email' => 'buyer@example.com', 'customer_id' => 'cus_123'],
            'metadata' => ['quote_id' => self::API_QUOTE_ID, 'store' => '1'],
            'charges' => ['data' => [['id' => 'chrg_123', 'payment_method' => ['object' => $paymentMethod]]]],
        ];
    }

    private function webhook(string $event, string $paymentMethod, string $paymentStatus, int $amount): array
    {
        return [
            'type' => $event,
            'data' => [
                'object' => [
                    'id' => self::CONEKTA_ORDER_ID,
                    'payment_status' => $paymentStatus,
                    'amount' => $amount,
                    'metadata' => [],
                    'charges' => ['data' => [['payment_method' => ['object' => $paymentMethod]]]],
                ],
            ],
        ];
    }

    private function post(array $body): void
    {
        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['getMethod', 'getContent'])
            ->getMockForAbstractClass();
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getContent')->willReturn(json_encode($body));
        $this->buildController()->execute();
    }

    private function assertLoggedError(string $fragment): void
    {
        $matches = array_filter($this->loggedErrors, fn (string $message) => str_contains($message, $fragment));
        $this->assertNotEmpty($matches, 'Expected an error log containing: ' . $fragment);
    }

    private function buildController(): Index
    {
        $logger = $this->createMock(ConektaLogger::class);
        $logger->method('error')->willReturnCallback(function (string $message) {
            $this->loggedErrors[] = $message;
        });

        $apiClient = $this->createMock(ConektaApiClient::class);
        $apiClient->method('getOrderByID')->willReturnCallback(function (string $id) {
            if (!isset($this->apiOrders[$id])) {
                throw new ApiException('Object not found', 404);
            }
            return new OrderResponse($this->apiOrders[$id]);
        });
        $apiClient->method('updateCharge')->willReturn($this->createMock(ChargeResponse::class));

        $webhookRepository = new WebhookRepository(
            $this->buildOrderLoader(),
            $this->buildInvoiceService(),
            $this->createMock(InvoiceSender::class),
            $this->buildTransaction(),
            $logger,
            $this->buildMapping(),
            $apiClient
        );

        $missingOrders = new MissingOrders(
            $webhookRepository,
            $logger,
            $this->buildQuoteManagement(),
            $apiClient,
            $this->buildCartRepository(),
            $this->buildUtilHelper(),
            $this->buildOrderFactory(),
            $this->createMock(OrderRepositoryInterface::class)
        );

        $resultRaw = $this->createMock(Raw::class);
        $resultRaw->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($resultRaw) {
            $this->httpCode = $code;
            return $resultRaw;
        });
        $resultRaw->method('setHeader')->willReturnSelf();
        $resultRaw->method('setContents')->willReturnCallback(function ($content) use ($resultRaw) {
            $this->responseBody = $content;
            return $resultRaw;
        });
        $resultRawFactory = $this->getMockBuilder(RawFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $resultRawFactory->method('create')->willReturn($resultRaw);

        $jsonHelper = $this->createMock(Data::class);
        $jsonHelper->method('jsonDecode')->willReturnCallback(fn ($json) => json_decode($json, true));
        $jsonHelper->method('jsonEncode')->willReturnCallback(fn ($data) => json_encode($data));

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        return new Index(
            $context,
            $this->createMock(JsonFactory::class),
            $resultRawFactory,
            $jsonHelper,
            $logger,
            $webhookRepository,
            $missingOrders
        );
    }

    private function buildStoreOrder(string $incrementId, string $state): Order
    {
        $this->orderStates[$incrementId] = $state;
        $this->invoiceCounts[$incrementId] = 0;

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getIncrementId', 'getRealOrderId', 'getState', 'setState', 'setStatus', 'setStoreId',
                'getGrandTotal', 'hasInvoices', 'addCommentToStatusHistory', 'save', 'canCancel', 'cancel',
            ])
            ->getMock();
        $order->method('getId')->willReturn(1001);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getRealOrderId')->willReturn($incrementId);
        $order->method('getGrandTotal')->willReturn(self::ORDER_TOTAL);
        $order->method('hasInvoices')->willReturnCallback(fn () => $this->invoiceCounts[$incrementId] > 0);
        $order->method('getState')->willReturnCallback(fn () => $this->orderStates[$incrementId]);
        $order->method('setState')->willReturnCallback(function ($state) use ($order, $incrementId) {
            $this->orderStates[$incrementId] = $state;
            return $order;
        });
        $order->method('setStatus')->willReturnSelf();
        $order->method('setStoreId')->willReturnSelf();
        $order->method('save')->willReturnSelf();
        $order->method('canCancel')->willReturnCallback(fn () => $this->orderStates[$incrementId] !== Order::STATE_CANCELED);
        $order->method('cancel')->willReturnCallback(function () use ($order, $incrementId) {
            $this->canceledOrders[$incrementId] = true;
            return $order;
        });
        $history = $this->createMock(History::class);
        $history->method('setIsCustomerNotified')->willReturnSelf();
        $history->method('save')->willReturnSelf();
        $order->method('addCommentToStatusHistory')->willReturn($history);

        return $order;
    }

    private function buildEmptyOrder(): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(null);
        $order->method('load')->willReturnSelf();
        return $order;
    }

    private function buildOrderLoader(): OrderInterface
    {
        $orderLoader = $this->getMockBuilder(OrderInterface::class)
            ->addMethods(['loadByIncrementId'])
            ->getMockForAbstractClass();
        $orderLoader->method('loadByIncrementId')->willReturnCallback(
            fn ($incrementId) => $this->orders[$incrementId] ?? $this->buildEmptyOrder()
        );
        return $orderLoader;
    }

    private function buildMapping(): ConektaSalesOrderInterface
    {
        $mapping = $this->createMock(ConektaSalesOrderInterface::class);
        $mapping->method('loadByConektaOrderId')->willReturnCallback(function ($conektaOrderId) use ($mapping) {
            $this->lookedUpConektaOrderId = $conektaOrderId;
            return $mapping;
        });
        $mapping->method('getIncrementOrderId')->willReturnCallback(
            fn () => $this->mappings[$this->lookedUpConektaOrderId] ?? null
        );
        return $mapping;
    }

    private function buildInvoiceService(): InvoiceService
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->method('prepareInvoice')->willReturnCallback(function (Order $order) {
            $invoice = $this->createMock(Invoice::class);
            $invoice->method('register')->willReturnCallback(function () use ($invoice, $order) {
                $this->invoiceCounts[$order->getIncrementId()]++;
                return $invoice;
            });
            $invoice->method('getOrder')->willReturn($order);
            return $invoice;
        });
        return $invoiceService;
    }

    private function buildTransaction(): Transaction
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addObject')->willReturnSelf();
        return $transaction;
    }

    private function buildQuoteManagement(): QuoteManagement
    {
        $quoteManagement = $this->createMock(QuoteManagement::class);
        $quoteManagement->method('submit')->willReturnCallback(function () {
            $this->submittedQuotes++;
            $state = $this->apiOrders[self::CONEKTA_ORDER_ID]['payment_status'] === 'paid'
                ? Order::STATE_PROCESSING
                : Order::STATE_PENDING_PAYMENT;
            $order = $this->buildStoreOrder(self::RECOVERED_INCREMENT_ID, $state);
            $this->orders[self::RECOVERED_INCREMENT_ID] = $order;
            $this->mappings[self::CONEKTA_ORDER_ID] = self::RECOVERED_INCREMENT_ID;
            return $order;
        });
        return $quoteManagement;
    }

    private function buildCartRepository(): CartRepositoryInterface
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->willReturnCallback(function ($quoteId) {
            $this->requestedQuoteIds[] = (string)$quoteId;
            return $this->buildQuote();
        });
        return $cartRepository;
    }

    private function buildQuote(): Quote
    {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('importData')->willReturnSelf();
        $payment->method('setAdditionalInformation')->willReturnSelf();

        $address = $this->createMock(QuoteAddress::class);
        $address->method('addData')->willReturnSelf();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCustomerEmail', 'getCustomerEmail'])
            ->onlyMethods(['getPayment', 'getShippingAddress', 'getBillingAddress', 'getReservedOrderId', 'getId', 'setStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->method('getReservedOrderId')->willReturn(self::RECOVERED_INCREMENT_ID);
        $quote->method('getId')->willReturn((int)self::API_QUOTE_ID);

        return $quote;
    }

    private function buildUtilHelper(): ConektaData
    {
        $utilHelper = $this->createMock(ConektaData::class);
        $utilHelper->method('splitName')->willReturn(['firstname' => 'Buyer', 'lastname' => 'Test']);
        return $utilHelper;
    }

    private function buildOrderFactory(): OrderFactory
    {
        $orderFactory = $this->getMockBuilder(OrderFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $orderFactory->method('create')->willReturnCallback(fn () => $this->buildEmptyOrder());
        return $orderFactory;
    }
}
