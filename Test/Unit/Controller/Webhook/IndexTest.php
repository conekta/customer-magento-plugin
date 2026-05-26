<?php
namespace Conekta\Payments\Test\Unit\Controller\Webhook;

use Conekta\Payments\Controller\Webhook\Index;
use Conekta\Payments\Exception\EntityNotFoundException;
use Conekta\Payments\Exception\QuoteNotFoundException;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Conekta\Payments\Service\MissingOrders;
use Laminas\Http\Response;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Json\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private Index $controller;
    private MockObject $helper;
    private MockObject $conektaLogger;
    private MockObject $webhookRepository;
    private MockObject $missingOrders;
    private MockObject $request;
    private MockObject $resultRaw;
    private ?int $capturedHttpCode = null;
    private ?string $capturedBody = null;

    protected function setUp(): void
    {
        $this->capturedHttpCode = null;
        $this->capturedBody = null;

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['getMethod', 'getContent'])
            ->getMockForAbstractClass();

        $this->resultRaw = $this->createMock(Raw::class);
        $this->resultRaw->method('setHttpResponseCode')->willReturnCallback(function ($code) {
            $this->capturedHttpCode = $code;
            return $this->resultRaw;
        });
        $this->resultRaw->method('setHeader')->willReturnSelf();
        $this->resultRaw->method('setContents')->willReturnCallback(function ($content) {
            $this->capturedBody = $content;
            return $this->resultRaw;
        });

        $resultRawFactory = $this->getMockBuilder(\Magento\Framework\Controller\Result\RawFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $resultRawFactory->method('create')->willReturn($this->resultRaw);

        $resultJsonFactory = $this->createMock(JsonFactory::class);

        $this->helper = $this->createMock(Data::class);
        $this->helper->method('jsonEncode')->willReturnCallback(fn($data) => json_encode($data));

        $this->conektaLogger = $this->createMock(ConektaLogger::class);
        $this->webhookRepository = $this->createMock(WebhookRepository::class);
        $this->missingOrders = $this->createMock(MissingOrders::class);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        $this->controller = new Index(
            $context,
            $resultJsonFactory,
            $resultRawFactory,
            $this->helper,
            $this->conektaLogger,
            $this->webhookRepository,
            $this->missingOrders
        );
    }

    private function buildWebhookBody(string $event, ?string $paymentMethod = null): array
    {
        $body = [
            'type' => $event,
            'data' => [
                'object' => [
                    'metadata' => ['order_id' => '100000001'],
                    'charges' => [
                        'data' => []
                    ]
                ]
            ]
        ];

        if ($paymentMethod !== null) {
            $body['data']['object']['charges']['data'][] = [
                'payment_method' => ['object' => $paymentMethod]
            ];
        }

        return $body;
    }

    private function configureRequest(string $method, ?array $body): void
    {
        $this->request->method('getMethod')->willReturn($method);
        $this->request->method('getContent')->willReturn(
            $body !== null ? json_encode($body) : ''
        );
        $this->helper->method('jsonDecode')->willReturn($body);
    }

    // --- Invalid request tests ---

    public function testReturns400WhenBodyIsEmpty(): void
    {
        $this->configureRequest('POST', null);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_400, $this->capturedHttpCode);
        $this->assertNotNull($this->capturedBody);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Invalid request data', $decoded['error']);
    }

    public function testReturns400WhenMethodIsNotPost(): void
    {
        $body = $this->buildWebhookBody('webhook_ping');
        $this->configureRequest('GET', $body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_400, $this->capturedHttpCode);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Invalid request data', $decoded['error']);
    }

    // --- Webhook ping ---

    public function testWebhookPingReturns200(): void
    {
        $body = $this->buildWebhookBody('webhook_ping');
        $this->configureRequest('POST', $body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
        $this->assertNull($this->capturedBody);
    }

    // --- order.paid ---

    public function testOrderPaidCallsPayOrder(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'cash_payment');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->expects($this->once())
            ->method('payOrder')
            ->with($body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    public function testOrderPaidWithCardRecoversThenPays(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'card_payment');
        $this->configureRequest('POST', $body);

        $this->missingOrders->expects($this->once())
            ->method('recoverOrder')
            ->with($body);
        $this->webhookRepository->expects($this->once())
            ->method('payOrder')
            ->with($body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    // --- order.pending_payment ---

    public function testPendingPaymentNonCardRecoversMissingOrder(): void
    {
        $body = $this->buildWebhookBody('order.pending_payment', 'cash_payment');
        $this->configureRequest('POST', $body);

        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getId')->willReturn(1);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($order);

        $this->missingOrders->expects($this->once())
            ->method('recoverOrder')
            ->with($body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    public function testPendingPaymentCardDoesNotRecoverOrder(): void
    {
        $body = $this->buildWebhookBody('order.pending_payment', 'card_payment');
        $this->configureRequest('POST', $body);

        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getId')->willReturn(1);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($order);

        $this->missingOrders->expects($this->never())
            ->method('recoverOrder');

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    public function testPendingPaymentReturns404WhenOrderNotFound(): void
    {
        $body = $this->buildWebhookBody('order.pending_payment', 'cash_payment');
        $this->configureRequest('POST', $body);

        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getId')->willReturn(null);
        $this->webhookRepository->method('findByMetadataOrderId')->willReturn($order);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_404, $this->capturedHttpCode);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Order not found', $decoded['error']);
    }

    // --- order.expired / order.canceled ---

    public function testOrderExpiredCallsExpireOrder(): void
    {
        $body = $this->buildWebhookBody('order.expired');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->expects($this->once())
            ->method('expireOrder')
            ->with($body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    public function testOrderCanceledCallsExpireOrder(): void
    {
        $body = $this->buildWebhookBody('order.canceled');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->expects($this->once())
            ->method('expireOrder')
            ->with($body);

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_200, $this->capturedHttpCode);
    }

    // --- Error handling ---

    public function testQuoteNotFoundExceptionReturns204AndSkipsPayOrder(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'card_payment');
        $this->configureRequest('POST', $body);

        $this->missingOrders->expects($this->once())
            ->method('recoverOrder')
            ->willThrowException(new QuoteNotFoundException('Quote not found for quote_id 999'));
        $this->webhookRepository->expects($this->never())
            ->method('payOrder');

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_204, $this->capturedHttpCode);
    }

    public function testEntityNotFoundExceptionReturns404(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'cash_payment');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->method('payOrder')
            ->willThrowException(new EntityNotFoundException('Order not found'));

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_404, $this->capturedHttpCode);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Entity Not Found', $decoded['error']);
        $this->assertSame('Order not found', $decoded['message']);
    }

    public function testGenericExceptionReturns500(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'cash_payment');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->method('payOrder')
            ->willThrowException(new \RuntimeException('Something went wrong'));

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_500, $this->capturedHttpCode);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Internal Server Error', $decoded['error']);
        $this->assertSame('Something went wrong', $decoded['message']);
    }

    public function testThrowableErrorReturns500(): void
    {
        $body = $this->buildWebhookBody('order.paid', 'cash_payment');
        $this->configureRequest('POST', $body);

        $this->webhookRepository->method('payOrder')
            ->willThrowException(new \TypeError('Type error occurred'));

        $result = $this->controller->execute();

        $this->assertSame(Response::STATUS_CODE_500, $this->capturedHttpCode);
        $decoded = json_decode($this->capturedBody, true);
        $this->assertSame('Internal Server Error', $decoded['error']);
        $this->assertSame('Type error occurred', $decoded['message']);
    }

    // --- CSRF ---

    public function testValidateForCsrfReturnsTrue(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->assertTrue($this->controller->validateForCsrf($request));
    }

    public function testCreateCsrfValidationExceptionReturnsNull(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->assertNull($this->controller->createCsrfValidationException($request));
    }
}
