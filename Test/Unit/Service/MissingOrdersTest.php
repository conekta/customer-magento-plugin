<?php
namespace Conekta\Payments\Test\Unit\Service;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaData;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Conekta\Payments\Service\MissingOrders;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\History as StatusHistory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MissingOrdersTest extends TestCase
{
    private MissingOrders $missingOrders;
    private MockObject $webhookRepository;
    private MockObject $conektaLogger;
    private MockObject $quoteManagement;
    private MockObject $conektaApiClient;
    private MockObject $cartRepository;
    private MockObject $objectManager;

    /** @var array<string, int> track calls to key methods */
    private array $callCounts;

    protected function setUp(): void
    {
        $this->callCounts = [
            'cartRepository_get' => 0,
            'quoteManagement_submit' => 0,
            'conektaApi_updateCharge' => 0,
            'order_save' => 0,
            'quote_setStoreId' => 0,
        ];

        $this->webhookRepository = $this->createMock(WebhookRepository::class);
        $this->conektaLogger = $this->createMock(ConektaLogger::class);
        $this->quoteManagement = $this->createMock(QuoteManagement::class);
        $this->conektaApiClient = $this->createMock(ConektaApiClient::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);

        $reflection = new ReflectionClass(MissingOrders::class);
        $this->missingOrders = $reflection->newInstanceWithoutConstructor();

        $props = [
            'webhookRepository' => $this->webhookRepository,
            '_conektaLogger' => $this->conektaLogger,
            'quoteManagement' => $this->quoteManagement,
            'conektaApiClient' => $this->conektaApiClient,
            '_cartRepository' => $this->cartRepository,
        ];

        foreach ($props as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue($this->missingOrders, $value);
        }

        $utilHelper = $this->createMock(ConektaData::class);
        $utilHelper->method('splitName')->willReturnCallback(function (string $fullName) {
            $parts = explode(' ', $fullName, 2);
            return [
                'firstname' => $parts[0] ?? '',
                'lastname' => $parts[1] ?? '',
            ];
        });

        $utilProp = $reflection->getProperty('utilHelper');
        $utilProp->setAccessible(true);
        $utilProp->setValue($this->missingOrders, $utilHelper);

        $this->objectManager = $this->getMockBuilder(\Magento\Framework\App\ObjectManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $omProp = $reflection->getProperty('objectManager');
        $omProp->setAccessible(true);
        $omProp->setValue($this->missingOrders, $this->objectManager);
    }

    private function buildEvent(
        string $conektaOrderId = 'ord_abc123',
        string $paymentMethod = 'card_payment',
        ?string $quoteId = '999',
        ?string $storeId = '1'
    ): array {
        return [
            'type' => 'order.paid',
            'data' => [
                'object' => [
                    'id' => $conektaOrderId,
                    'customer_info' => [
                        'email' => 'test@example.com',
                        'customer_id' => 'cus_123',
                    ],
                    'metadata' => [
                        'order_id' => '100000001',
                        'quote_id' => $quoteId,
                        'store' => $storeId,
                    ],
                    'charges' => [
                        'data' => [
                            [
                                'id' => 'chrg_123',
                                'payment_method' => [
                                    'object' => $paymentMethod,
                                    'brand' => 'visa',
                                    'type' => 'credit',
                                    'exp_month' => '12',
                                    'exp_year' => '2027',
                                    'last4' => '4242',
                                ],
                            ],
                        ],
                    ],
                    'shipping_contact' => [
                        'receiver' => 'John Doe',
                        'phone' => '5512345678',
                        'address' => [
                            'street1' => 'Calle 1',
                            'street2' => '',
                            'city' => 'CDMX',
                            'state' => 'CDMX',
                            'country' => 'mx',
                            'postal_code' => '06600',
                        ],
                        'metadata' => [
                            'region_id' => '123',
                            'company' => 'Test Co',
                        ],
                    ],
                    'fiscal_entity' => [
                        'name' => 'John Doe',
                        'phone' => '5512345678',
                        'address' => [
                            'street1' => 'Calle 1',
                            'street2' => '',
                            'city' => 'CDMX',
                            'state' => 'CDMX',
                            'country' => 'mx',
                            'postal_code' => '06600',
                        ],
                        'metadata' => [
                            'region_id' => '123',
                            'company' => 'Test Co',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createMockOrder(?int $orderId = null): MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        return $order;
    }

    private function createMockQuote(): MockObject
    {
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('importData')->willReturnSelf();
        $payment->method('setAdditionalInformation')->willReturnSelf();

        $shippingAddress = $this->createMock(QuoteAddress::class);
        $shippingAddress->method('addData')->willReturnSelf();

        $billingAddress = $this->createMock(QuoteAddress::class);
        $billingAddress->method('addData')->willReturnSelf();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCustomerEmail', 'getCustomerEmail'])
            ->onlyMethods(['getPayment', 'getShippingAddress', 'getBillingAddress', 'getReservedOrderId', 'getId', 'setStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getReservedOrderId')->willReturn('100000001');
        $quote->method('getId')->willReturn(999);
        $quote->method('getCustomerEmail')->willReturn('test@example.com');

        return $quote;
    }

    private function setupFullRecoveryMocks(MockObject $quote): MockObject
    {
        $noOrder = $this->createMockOrder(null);
        $noOrder->method('load')->willReturnSelf();
        $this->objectManager->method('create')
            ->with('Magento\Sales\Model\Order')
            ->willReturn($noOrder);

        $statusHistory = $this->createMock(StatusHistory::class);
        $statusHistory->method('setIsCustomerNotified')->willReturnSelf();
        $statusHistory->method('save')->willReturnSelf();

        $savedStoreId = null;
        $newOrder = $this->createMock(Order::class);
        $newOrder->method('setStoreId')->willReturnCallback(function ($id) use ($newOrder, &$savedStoreId) {
            $savedStoreId = $id;
            return $newOrder;
        });
        $newOrder->method('save')->willReturnCallback(function () {
            $this->callCounts['order_save']++;
            return null;
        });
        $newOrder->method('addCommentToStatusHistory')->willReturn($statusHistory);
        $newOrder->method('getRealOrderId')->willReturn('100000001');

        $this->quoteManagement->method('submit')
            ->with($quote)
            ->willReturnCallback(function () use ($newOrder) {
                $this->callCounts['quoteManagement_submit']++;
                return $newOrder;
            });

        return $newOrder;
    }

    // --- Order already exists by conekta order id ---

    public function testReturnsEarlyWhenOrderAlreadyExistsByConektaId(): void
    {
        $event = $this->buildEvent();
        $existingOrder = $this->createMockOrder(42);

        $this->webhookRepository->method('findByMetadataOrderId')
            ->with($event)
            ->willReturn($existingOrder);

        $this->cartRepository->method('get')->willReturnCallback(function () {
            $this->callCounts['cartRepository_get']++;
            return $this->createMockQuote();
        });

        $this->missingOrders->recover_order($event);

        $this->assertSame(0, $this->callCounts['cartRepository_get'], 'No debió buscar el quote');
        $this->assertSame(0, $this->callCounts['quoteManagement_submit'], 'No debió crear orden');
    }

    // --- Order already exists by increment id ---

    public function testReturnsEarlyWhenOrderAlreadyExistsByIncrementId(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $quote = $this->createMockQuote();
        $this->cartRepository->method('get')->with('999')->willReturn($quote);

        $existingOrder = $this->createMockOrder(42);
        $existingOrder->method('load')->willReturnSelf();
        $this->objectManager->method('create')->willReturn($existingOrder);

        $this->quoteManagement->method('submit')->willReturnCallback(function () {
            $this->callCounts['quoteManagement_submit']++;
            return $this->createMock(Order::class);
        });

        $this->missingOrders->recover_order($event);

        $this->assertSame(0, $this->callCounts['quoteManagement_submit'], 'No debió crear orden si ya existe por incrementId');
    }

    // --- Successful recovery ---

    public function testRecoverOrderCreatesOrderSuccessfully(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $quote = $this->createMockQuote();
        $this->cartRepository->method('get')->with('999')->willReturn($quote);

        $newOrder = $this->setupFullRecoveryMocks($quote);

        $capturedChargeArgs = null;
        $chargeResponse = $this->createMock(\Conekta\Model\ChargeResponse::class);
        $this->conektaApiClient->method('updateCharge')
            ->willReturnCallback(function ($chargeId, $data) use (&$capturedChargeArgs, $chargeResponse) {
                $capturedChargeArgs = ['chargeId' => $chargeId, 'data' => $data];
                $this->callCounts['conektaApi_updateCharge']++;
                return $chargeResponse;
            });

        $this->missingOrders->recover_order($event);

        $this->assertSame(1, $this->callCounts['quoteManagement_submit'], 'Debió crear la orden');
        $this->assertSame(1, $this->callCounts['order_save'], 'Debió guardar la orden');
        $this->assertSame(1, $this->callCounts['conektaApi_updateCharge'], 'Debió actualizar la referencia en Conekta');
        $this->assertSame('chrg_123', $capturedChargeArgs['chargeId']);
        $this->assertSame(['reference_id' => '100000001'], $capturedChargeArgs['data']);
    }

    // --- Recovery with card_payment has additional info ---

    public function testRecoverOrderWithCardPaymentSetsCardInfo(): void
    {
        $event = $this->buildEvent(paymentMethod: 'card_payment');

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $capturedAdditionalInfo = null;

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('importData')->willReturnSelf();
        $payment->method('setAdditionalInformation')
            ->willReturnCallback(function ($info) use (&$capturedAdditionalInfo) {
                $capturedAdditionalInfo = $info;
            });

        $shippingAddress = $this->createMock(QuoteAddress::class);
        $shippingAddress->method('addData')->willReturnSelf();
        $billingAddress = $this->createMock(QuoteAddress::class);
        $billingAddress->method('addData')->willReturnSelf();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCustomerEmail', 'getCustomerEmail'])
            ->onlyMethods(['getPayment', 'getShippingAddress', 'getBillingAddress', 'getReservedOrderId', 'getId', 'setStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getReservedOrderId')->willReturn('100000001');
        $quote->method('getId')->willReturn(999);
        $quote->method('getCustomerEmail')->willReturn('test@example.com');

        $this->cartRepository->method('get')->willReturn($quote);

        $this->setupFullRecoveryMocks($quote);

        $this->missingOrders->recover_order($event);

        $this->assertNotNull($capturedAdditionalInfo, 'Debió setear additional information');
        $this->assertSame('card', $capturedAdditionalInfo['payment_method']);
        $this->assertSame('visa', $capturedAdditionalInfo['cc_type']);
        $this->assertSame('4242', $capturedAdditionalInfo['cc_last_4']);
        $this->assertSame('chrg_123', $capturedAdditionalInfo['txn_id']);
        $this->assertSame('cus_123', $capturedAdditionalInfo['conekta_customer_id']);
    }

    // --- NoSuchEntityException (quote not found) ---

    public function testRecoverOrderSwallowsNoSuchEntityException(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $this->cartRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Quote not found')));

        $loggedMessages = [];
        $this->conektaLogger->method('error')
            ->willReturnCallback(function ($msg) use (&$loggedMessages) {
                $loggedMessages[] = $msg;
            });

        $this->missingOrders->recover_order($event);

        $this->assertSame(0, $this->callCounts['quoteManagement_submit'], 'No debió intentar crear orden');
        $this->assertNotEmpty($loggedMessages, 'Debió loguear el error');
        $this->assertStringContainsString('Quote not found', $loggedMessages[0]);
    }

    // --- Generic exception is rethrown ---

    public function testRecoverOrderRethrowsGenericException(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $this->cartRepository->method('get')
            ->willThrowException(new \Exception('DB connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DB connection failed');

        $this->missingOrders->recover_order($event);
    }

    // --- LocalizedException is rethrown ---

    public function testRecoverOrderRethrowsLocalizedException(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $this->cartRepository->method('get')
            ->willThrowException(new LocalizedException(__('Something localized')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Something localized');

        $this->missingOrders->recover_order($event);
    }

    // --- Recovery with cash_payment (no card additional info) ---

    public function testRecoverOrderWithCashPaymentHasNoCardInfo(): void
    {
        $event = $this->buildEvent(paymentMethod: 'cash_payment');

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $capturedAdditionalInfo = null;

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('importData')->willReturnSelf();
        $payment->method('setAdditionalInformation')
            ->willReturnCallback(function ($info) use (&$capturedAdditionalInfo) {
                $capturedAdditionalInfo = $info;
            });

        $shippingAddress = $this->createMock(QuoteAddress::class);
        $shippingAddress->method('addData')->willReturnSelf();
        $billingAddress = $this->createMock(QuoteAddress::class);
        $billingAddress->method('addData')->willReturnSelf();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['setCustomerEmail', 'getCustomerEmail'])
            ->onlyMethods(['getPayment', 'getShippingAddress', 'getBillingAddress', 'getReservedOrderId', 'getId', 'setStoreId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getReservedOrderId')->willReturn('100000001');
        $quote->method('getId')->willReturn(999);
        $quote->method('getCustomerEmail')->willReturn('test@example.com');

        $this->cartRepository->method('get')->willReturn($quote);

        $this->setupFullRecoveryMocks($quote);

        $this->missingOrders->recover_order($event);

        $this->assertNotNull($capturedAdditionalInfo, 'Debió setear additional information');
        $this->assertSame('cash', $capturedAdditionalInfo['payment_method']);
        $this->assertArrayNotHasKey('cc_type', $capturedAdditionalInfo);
        $this->assertArrayNotHasKey('cc_last_4', $capturedAdditionalInfo);
        $this->assertArrayNotHasKey('cc_exp_month', $capturedAdditionalInfo);
    }

    // --- Conekta API failure doesn't break recovery ---

    public function testRecoverOrderContinuesWhenConektaUpdateFails(): void
    {
        $event = $this->buildEvent();

        $notFoundOrder = $this->createMockOrder(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($notFoundOrder);

        $quote = $this->createMockQuote();
        $this->cartRepository->method('get')->willReturn($quote);

        $this->setupFullRecoveryMocks($quote);

        $this->conektaApiClient->method('updateCharge')
            ->willThrowException(new \Exception('Conekta API timeout'));

        $loggedMessages = [];
        $this->conektaLogger->method('error')
            ->willReturnCallback(function ($msg) use (&$loggedMessages) {
                $loggedMessages[] = $msg;
            });

        $this->missingOrders->recover_order($event);

        $this->assertSame(1, $this->callCounts['quoteManagement_submit'], 'Debió crear la orden');
        $this->assertSame(1, $this->callCounts['order_save'], 'Debió guardar la orden');
        $this->assertNotEmpty($loggedMessages, 'Debió loguear el error de Conekta API');
        $this->assertStringContainsString('updating conekta charge', $loggedMessages[0]);
    }
}
