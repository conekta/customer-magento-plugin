<?php
namespace Conekta\Payments\Test\Unit\Controller;

use Conekta\Payments\Controller\Router;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;
    private MockObject $actionFactory;
    private MockObject $request;
    private MockObject $conektaHelper;
    private MockObject $forwardAction;

    protected function setUp(): void
    {
        $this->forwardAction = $this->createMock(ActionInterface::class);

        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->actionFactory->method('create')
            ->with(Forward::class)
            ->willReturn($this->forwardAction);

        $response = $this->createMock(ResponseInterface::class);
        $this->conektaHelper = $this->createMock(ConektaHelper::class);

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(['setControllerName', 'setAlias', 'getPathInfo'])
            ->getMockForAbstractClass();

        // chain methods return self
        $this->request->method('setModuleName')->willReturnSelf();
        $this->request->method('setControllerName')->willReturnSelf();
        $this->request->method('setActionName')->willReturnSelf();

        $this->router = new Router(
            $this->actionFactory,
            $response,
            $this->conektaHelper
        );
    }

    public function testReturnsNullWhenModuleIsAlreadyConekta(): void
    {
        $this->request->method('getModuleName')->willReturn('conekta');

        $result = $this->router->match($this->request);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenPathDoesNotMatch(): void
    {
        $this->request->method('getModuleName')->willReturn('catalog');
        $this->request->method('getPathInfo')->willReturn('/some/random/path');

        $this->conektaHelper->method('getUrlWebhookOrDefault')
            ->willReturn('https://mystore.com/conekta/webhook/index');

        $result = $this->router->match($this->request);

        $this->assertNull($result);
    }

    public function testMatchesApplePayDomainAssociationPath(): void
    {
        $this->request->method('getModuleName')->willReturn('catalog');
        $this->request->method('getPathInfo')
            ->willReturn('/.well-known/apple-developer-merchantid-domain-association');

        $this->request->expects($this->once())->method('setModuleName')->with('conekta');
        $this->request->expects($this->once())->method('setControllerName')->with('applepay');
        $this->request->expects($this->once())->method('setActionName')->with('domainassociation');

        $result = $this->router->match($this->request);

        $this->assertNotNull($result);
        $this->assertSame($this->forwardAction, $result);
    }

    public function testMatchesWebhookPath(): void
    {
        $this->request->method('getModuleName')->willReturn('catalog');
        $this->request->method('getPathInfo')->willReturn('/conekta/webhook/index');

        $this->conektaHelper->method('getUrlWebhookOrDefault')
            ->willReturn('https://mystore.com/conekta/webhook/index');

        $this->request->expects($this->once())->method('setModuleName')->with('conekta');
        $this->request->expects($this->once())->method('setControllerName')->with('webhook');
        $this->request->expects($this->once())->method('setActionName')->with('index');

        $result = $this->router->match($this->request);

        $this->assertNotNull($result);
        $this->assertSame($this->forwardAction, $result);
    }

    public function testMatchesCustomWebhookPath(): void
    {
        $this->request->method('getModuleName')->willReturn('catalog');
        $this->request->method('getPathInfo')->willReturn('/my-custom-webhook');

        $this->conektaHelper->method('getUrlWebhookOrDefault')
            ->willReturn('https://mystore.com/my-custom-webhook');

        $result = $this->router->match($this->request);

        $this->assertNotNull($result);
        $this->assertSame($this->forwardAction, $result);
    }
}
