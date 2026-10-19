<?php
namespace Conekta\Payments\Test\Unit\Model;

use Conekta\ApiException;
use Conekta\Model\OrderResponse;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Api\Data\ConektaSalesOrderInterface;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WebhookRepositoryTest extends TestCase
{
    private const CONEKTA_ORDER_ID = 'ord_2zTest123';

    private string $orderState;
    private bool $orderCanceled;
    private bool $invoiceRegistered;
    private array $requestedConektaOrderIds;
    private MockObject $conektaApiClient;

    protected function setUp(): void
    {
        $this->orderState = Order::STATE_PENDING_PAYMENT;
        $this->orderCanceled = false;
        $this->invoiceRegistered = false;
        $this->requestedConektaOrderIds = [];
        $this->conektaApiClient = $this->createMock(ConektaApiClient::class);
    }

    public function testForgedPaidBodyDoesNotPayOrderWhenConektaOrderIsPending(): void
    {
        $this->givenConektaOrder('pending_payment', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->payOrder($this->buildBody('paid', 100));

        $this->assertSame([self::CONEKTA_ORDER_ID], $this->requestedConektaOrderIds);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderState);
        $this->assertFalse($this->invoiceRegistered);
    }

    public function testPaysAndInvoicesPendingOrderWhenConektaConfirmsPaymentAndAmount(): void
    {
        $this->givenConektaOrder('paid', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->payOrder($this->buildBody('paid', 1599900));

        $this->assertSame(Order::STATE_PROCESSING, $this->orderState);
        $this->assertTrue($this->invoiceRegistered);
    }

    public function testInvoicesAuthorizedCardOrderInProcessingWithoutInvoice(): void
    {
        $this->orderState = Order::STATE_PROCESSING;
        $this->givenConektaOrder('paid', 50000);
        $repository = $this->buildRepository($this->buildOrder(500.00));

        $repository->payOrder($this->buildBody('paid', 50000));

        $this->assertSame(Order::STATE_PROCESSING, $this->orderState);
        $this->assertTrue($this->invoiceRegistered);
    }

    public function testAcceptsRoundingDifferenceWithinTolerance(): void
    {
        $this->givenConektaOrder('paid', 1599899);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->payOrder($this->buildBody('paid', 1599899));

        $this->assertSame(Order::STATE_PROCESSING, $this->orderState);
        $this->assertTrue($this->invoiceRegistered);
    }

    public function testDoesNotPayOrderWhenConektaPaidAmountIsLowerThanOrderTotal(): void
    {
        $this->givenConektaOrder('paid', 10000);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->payOrder($this->buildBody('paid', 10000));

        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderState);
        $this->assertFalse($this->invoiceRegistered);
    }

    public function testDoesNotReopenCanceledOrder(): void
    {
        $this->orderState = Order::STATE_CANCELED;
        $this->givenConektaOrder('paid', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->payOrder($this->buildBody('paid', 1599900));

        $this->assertSame(Order::STATE_CANCELED, $this->orderState);
        $this->assertFalse($this->invoiceRegistered);
    }

    public function testDoesNotInvoiceTwiceWhenOrderAlreadyHasInvoice(): void
    {
        $this->orderState = Order::STATE_PROCESSING;
        $this->givenConektaOrder('paid', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00, true));

        $repository->payOrder($this->buildBody('paid', 1599900));

        $this->assertSame(Order::STATE_PROCESSING, $this->orderState);
        $this->assertFalse($this->invoiceRegistered);
    }

    public function testPropagatesConektaApiErrorWithoutChangingOrder(): void
    {
        $this->conektaApiClient->method('getOrderByID')
            ->willThrowException(new ApiException('Not Found', 404));
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $thrown = null;
        try {
            $repository->payOrder($this->buildBody('paid', 1599900));
        } catch (ApiException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(ApiException::class, $thrown);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderState);
        $this->assertFalse($this->invoiceRegistered);
    }

    public function testForgedExpiredBodyDoesNotCancelOrderWhenConektaOrderIsPending(): void
    {
        $this->givenConektaOrder('pending_payment', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->expireOrder($this->buildBody('expired', 1599900));

        $this->assertFalse($this->orderCanceled);
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $this->orderState);
    }

    public function testCancelsPendingOrderWhenConektaConfirmsExpiration(): void
    {
        $this->givenConektaOrder('expired', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $repository->expireOrder($this->buildBody('expired', 1599900));

        $this->assertTrue($this->orderCanceled);
        $this->assertSame(Order::STATE_CANCELED, $this->orderState);
    }

    public function testFetchConektaOrderReturnsApiOrderAsArray(): void
    {
        $this->givenConektaOrder('paid', 1599900);
        $repository = $this->buildRepository($this->buildOrder(15999.00));

        $conektaOrder = $repository->fetchConektaOrder($this->buildBody('pending_payment', 100));

        $this->assertSame(self::CONEKTA_ORDER_ID, $conektaOrder['id']);
        $this->assertSame('paid', $conektaOrder['payment_status']);
        $this->assertSame(1599900, $conektaOrder['amount']);
        $this->assertSame('999', $conektaOrder['metadata']['quote_id']);
    }

    private function givenConektaOrder(string $paymentStatus, int $amount): void
    {
        $this->conektaApiClient->method('getOrderByID')->willReturnCallback(
            function (string $id) use ($paymentStatus, $amount) {
                $this->requestedConektaOrderIds[] = $id;
                return new OrderResponse([
                    'id' => $id,
                    'payment_status' => $paymentStatus,
                    'amount' => $amount,
                    'currency' => 'MXN',
                    'metadata' => ['quote_id' => '999'],
                ]);
            }
        );
    }

    private function buildBody(string $paymentStatus, int $amount): array
    {
        return [
            'type' => 'order.paid',
            'data' => [
                'object' => [
                    'id' => self::CONEKTA_ORDER_ID,
                    'payment_status' => $paymentStatus,
                    'amount' => $amount,
                    'charges' => ['data' => [['payment_method' => ['object' => 'cash_payment']]]],
                ],
            ],
        ];
    }

    private function buildOrder(float $grandTotal, bool $hasInvoices = false): Order
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getIncrementId', 'getState', 'setState', 'setStatus', 'getGrandTotal',
                'hasInvoices', 'addCommentToStatusHistory', 'save', 'canCancel', 'cancel',
            ])
            ->getMock();
        $order->method('getId')->willReturn(1001);
        $order->method('getIncrementId')->willReturn('000000123');
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getState')->willReturnCallback(fn () => $this->orderState);
        $order->method('setState')->willReturnCallback(function ($state) use ($order) {
            $this->orderState = $state;
            return $order;
        });
        $order->method('setStatus')->willReturnSelf();
        $order->method('save')->willReturnSelf();
        $order->method('canCancel')->willReturn(true);
        $order->method('cancel')->willReturnCallback(function () use ($order) {
            $this->orderCanceled = true;
            return $order;
        });
        $history = $this->createMock(History::class);
        $history->method('setIsCustomerNotified')->willReturnSelf();
        $order->method('addCommentToStatusHistory')->willReturn($history);

        return $order;
    }

    private function buildRepository(Order $order): WebhookRepository
    {
        $orderLoader = $this->getMockBuilder(OrderInterface::class)
            ->addMethods(['loadByIncrementId'])
            ->getMockForAbstractClass();
        $orderLoader->method('loadByIncrementId')->willReturn($order);

        $mapping = $this->createMock(ConektaSalesOrderInterface::class);
        $mapping->method('loadByConektaOrderId')->willReturnSelf();
        $mapping->method('getIncrementOrderId')->willReturn('000000123');

        $invoice = $this->createMock(Invoice::class);
        $invoice->method('register')->willReturnCallback(function () use ($invoice) {
            $this->invoiceRegistered = true;
            return $invoice;
        });
        $invoice->method('getOrder')->willReturn($order);
        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->method('prepareInvoice')->willReturn($invoice);

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addObject')->willReturnSelf();

        return new WebhookRepository(
            $orderLoader,
            $invoiceService,
            $this->createMock(InvoiceSender::class),
            $transaction,
            $this->createMock(ConektaLogger::class),
            $mapping,
            $this->conektaApiClient
        );
    }
}
