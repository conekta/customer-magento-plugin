<?php
namespace Conekta\Payments\Test\Unit\Model;

use Conekta\ApiException;
use Conekta\Model\OrderResponseCheckout;
use Conekta\Model\OrderResponse;
use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Api\Data\ConektaQuoteInterface;
use Conekta\Payments\Exception\ConektaException;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\ConektaQuote;
use Conekta\Payments\Model\ConektaQuoteFactory;
use Conekta\Payments\Model\ConektaQuoteRepository;
use Conekta\Payments\Model\ConektaQuoteRepositoryFactory;
use Conekta\Payments\Model\EmbedFormRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmbedFormRepositoryTest extends TestCase
{
    private EmbedFormRepository $repository;
    private MockObject $conektaLogger;
    private MockObject $conektaOrderApi;
    private MockObject $conektaQuoteFactory;
    private MockObject $conektaQuoteRepo;
    private MockObject $conektaQuoteRepositoryFactory;

    protected function setUp(): void
    {
        $this->conektaLogger = $this->createMock(ConektaLogger::class);
        $conektaQuoteInterface = $this->createMock(ConektaQuoteInterface::class);
        $this->conektaOrderApi = $this->createMock(ConektaApiClient::class);

        $this->conektaQuoteFactory = $this->getMockBuilder(ConektaQuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->conektaQuoteRepo = $this->getMockBuilder(ConektaQuoteRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getById', 'save'])
            ->getMock();

        $this->conektaQuoteRepositoryFactory = $this->getMockBuilder(ConektaQuoteRepositoryFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->conektaQuoteRepositoryFactory->method('create')->willReturn($this->conektaQuoteRepo);

        $this->repository = new EmbedFormRepository(
            $this->conektaLogger,
            $conektaQuoteInterface,
            $this->conektaOrderApi,
            $this->conektaQuoteFactory,
            $this->conektaQuoteRepositoryFactory
        );
    }

    private function buildValidOrderParams(): array
    {
        return [
            'currency' => 'MXN',
            'line_items' => [
                ['unit_price' => 5000, 'quantity' => 1],
            ],
            'shipping_contact' => [
                'phone' => '5512345678',
                'address' => ['phone' => '5512345678'],
            ],
            'checkout' => [
                'allowed_payment_methods' => ['card'],
                'monthly_installments_enabled' => false,
                'monthly_installments_options' => [],
                'on_demand_enabled' => false,
                'force_3ds_flow' => false,
            ],
            'customer_info' => ['email' => 'test@test.com'],
        ];
    }

    private function createMockCheckout(array $overrides = []): MockObject
    {
        $checkout = $this->createMock(OrderResponseCheckout::class);
        $checkout->method('getMonthlyInstallmentsOptions')->willReturn($overrides['installments'] ?? []);
        $checkout->method('getExpiresAt')->willReturn($overrides['expiresAt'] ?? time() + 3600);
        $checkout->method('getAllowedPaymentMethods')->willReturn($overrides['methods'] ?? ['card']);
        $checkout->method('getMonthlyInstallmentsEnabled')->willReturn($overrides['installmentsEnabled'] ?? false);
        $checkout->method('getOnDemandEnabled')->willReturn($overrides['onDemand'] ?? false);
        $checkout->method('getForce3dsFlow')->willReturn($overrides['force3ds'] ?? false);
        return $checkout;
    }

    private function createMockOrderResponse(?OrderResponseCheckout $checkout = null, ?string $paymentStatus = null): MockObject
    {
        $order = $this->createMock(OrderResponse::class);
        $order->method('getCheckout')->willReturn($checkout);
        $order->method('getPaymentStatus')->willReturn($paymentStatus);
        $order->method('getId')->willReturn('ord_123');
        return $order;
    }

    // --- Validation tests ---

    public function testThrowsWhenCurrencyIsNotMXN(): void
    {
        $params = $this->buildValidOrderParams();
        $params['currency'] = 'USD';

        $this->expectException(ConektaException::class);
        $this->expectExceptionMessage('moneda extranjera');

        $this->repository->generate(1, $params, 5000);
    }

    public function testThrowsWhenTotalBelowMinimum(): void
    {
        $params = $this->buildValidOrderParams();
        $params['line_items'] = [['unit_price' => 100, 'quantity' => 1]];

        $this->expectException(ConektaException::class);
        $this->expectExceptionMessage('compra superior');

        $this->repository->generate(1, $params, 1);
    }

    public function testThrowsWhenPhoneIsTooShort(): void
    {
        $params = $this->buildValidOrderParams();
        $params['shipping_contact']['phone'] = '123';
        $params['shipping_contact']['address']['phone'] = '123';

        $this->expectException(ConektaException::class);
        $this->expectExceptionMessage('no válido');

        $this->repository->generate(1, $params, 5000);
    }

    public function testThrowsWhenCashExceedsLimit(): void
    {
        $params = $this->buildValidOrderParams();
        $params['checkout']['allowed_payment_methods'] = ['cash'];

        $this->expectException(ConektaException::class);
        $this->expectExceptionMessage('monto máximo');

        $this->repository->generate(1, $params, 15000);
    }

    // --- New order creation ---

    public function testCreatesNewOrderWhenNoQuoteExists(): void
    {
        $params = $this->buildValidOrderParams();
        $orderResponse = $this->createMockOrderResponse();

        $this->conektaQuoteRepo->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->conektaOrderApi->expects($this->once())
            ->method('createOrder')
            ->with($params)
            ->willReturn($orderResponse);

        $newQuote = $this->createMock(ConektaQuote::class);
        $this->conektaQuoteFactory->method('create')->willReturn($newQuote);

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($orderResponse, $result);
    }

    public function testCreatesNewOrderWhenApiExceptionOccurs(): void
    {
        $params = $this->buildValidOrderParams();
        $orderResponse = $this->createMockOrderResponse();

        $conektaQuote = $this->createMock(ConektaQuote::class);
        $conektaQuote->method('getConektaOrderId')->willReturn('ord_old');
        $this->conektaQuoteRepo->method('getById')->willReturn($conektaQuote);

        $this->conektaOrderApi->method('getOrderByID')
            ->willThrowException(new ApiException('Not found'));

        $this->conektaOrderApi->expects($this->once())
            ->method('createOrder')
            ->willReturn($orderResponse);

        $newQuote = $this->createMock(ConektaQuote::class);
        $this->conektaQuoteFactory->method('create')->willReturn($newQuote);

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($orderResponse, $result);
    }

    // --- Checkout is null (2.4) ---

    public function testCreatesNewOrderWhenCheckoutIsNull(): void
    {
        $params = $this->buildValidOrderParams();

        $conektaQuote = $this->createMock(ConektaQuote::class);
        $conektaQuote->method('getConektaOrderId')->willReturn('ord_old');
        $this->conektaQuoteRepo->method('getById')->willReturn($conektaQuote);

        $existingOrder = $this->createMockOrderResponse(null);
        $this->conektaOrderApi->method('getOrderByID')->willReturn($existingOrder);

        $newOrderResponse = $this->createMockOrderResponse();
        $this->conektaOrderApi->expects($this->once())
            ->method('createOrder')
            ->with($params)
            ->willReturn($newOrderResponse);

        $newQuote = $this->createMock(ConektaQuote::class);
        $this->conektaQuoteFactory->method('create')->willReturn($newQuote);

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($newOrderResponse, $result);
    }

    // --- Order has payment status (2.1) ---

    public function testCreatesNewOrderWhenPaymentStatusExists(): void
    {
        $params = $this->buildValidOrderParams();
        $checkout = $this->createMockCheckout();

        $conektaQuote = $this->createMock(ConektaQuote::class);
        $conektaQuote->method('getConektaOrderId')->willReturn('ord_old');
        $this->conektaQuoteRepo->method('getById')->willReturn($conektaQuote);

        $existingOrder = $this->createMockOrderResponse($checkout, 'paid');
        $this->conektaOrderApi->method('getOrderByID')->willReturn($existingOrder);

        $newOrderResponse = $this->createMockOrderResponse();
        $this->conektaOrderApi->expects($this->once())
            ->method('createOrder')
            ->willReturn($newOrderResponse);

        $newQuote = $this->createMock(ConektaQuote::class);
        $this->conektaQuoteFactory->method('create')->willReturn($newQuote);

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($newOrderResponse, $result);
    }

    // --- Checkout expired (2.2) ---

    public function testCreatesNewOrderWhenCheckoutExpired(): void
    {
        $params = $this->buildValidOrderParams();

        $checkout = $this->createMockCheckout(['expiresAt' => time() - 100]);

        $conektaQuote = $this->createMock(ConektaQuote::class);
        $conektaQuote->method('getConektaOrderId')->willReturn('ord_old');
        $this->conektaQuoteRepo->method('getById')->willReturn($conektaQuote);

        $existingOrder = $this->createMockOrderResponse($checkout);
        $this->conektaOrderApi->method('getOrderByID')->willReturn($existingOrder);

        $newOrderResponse = $this->createMockOrderResponse();
        $this->conektaOrderApi->expects($this->once())
            ->method('createOrder')
            ->willReturn($newOrderResponse);

        $newQuote = $this->createMock(ConektaQuote::class);
        $this->conektaQuoteFactory->method('create')->willReturn($newQuote);

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($newOrderResponse, $result);
    }

    // --- Updates existing order ---

    public function testUpdatesExistingOrderWhenNoChanges(): void
    {
        $params = $this->buildValidOrderParams();
        $checkout = $this->createMockCheckout();

        $conektaQuote = $this->createMock(ConektaQuote::class);
        $conektaQuote->method('getConektaOrderId')->willReturn('ord_existing');
        $this->conektaQuoteRepo->method('getById')->willReturn($conektaQuote);

        $existingOrder = $this->createMockOrderResponse($checkout);
        $this->conektaOrderApi->method('getOrderByID')->willReturn($existingOrder);

        $updatedOrder = $this->createMockOrderResponse($checkout);
        $expectedParams = $params;
        unset($expectedParams['customer_info']);
        $this->conektaOrderApi->expects($this->once())
            ->method('updateOrder')
            ->with('ord_existing', $expectedParams)
            ->willReturn($updatedOrder);

        $this->conektaOrderApi->expects($this->never())->method('createOrder');

        $result = $this->repository->generate(1, $params, 5000);

        $this->assertSame($updatedOrder, $result);
    }

    // --- API error wraps in ConektaException ---

    public function testWrapsApiErrorInConektaException(): void
    {
        $params = $this->buildValidOrderParams();

        $this->conektaQuoteRepo->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->conektaOrderApi->method('createOrder')
            ->willThrowException(new \Exception('API down'));

        $this->expectException(ConektaException::class);
        $this->expectExceptionMessage('API down');

        $this->repository->generate(1, $params, 5000);
    }
}
