<?php

namespace Conekta\Payments\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Url;

class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    protected $actionFactory;

    /**
     * @var ResponseInterface
     */
    protected $_response;

    /**
     * @var ConektaHelper
     */
    private $_conektaHelper;

    /**
     * @var ConektaLogger
     */
    private $_conektaLogger;

    /**
     * @param ActionFactory $actionFactory
     * @param ResponseInterface $response
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        ActionFactory $actionFactory,
        ResponseInterface $response,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->actionFactory = $actionFactory;
        $this->_response = $response;
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
    }

    /**
     * Validate and Match
     *
     * @param RequestInterface $request
     * @return ActionInterface
     */
    public function match(RequestInterface $request)
    {
        if ($request->getModuleName() === 'conekta') {
            return;
        }
        
        $pathRequest = trim($request->getPathInfo(), '/');

        $urlWebhook = $this->_conektaHelper->getUrlWebhookOrDefault();
        $urlWebhook = trim($urlWebhook, '/');
        $pathWebhook = substr($urlWebhook, -strlen($pathRequest));

        //If paths are identical, then redirects to webhook controller
        if ($pathRequest === $pathWebhook) {
            $request->setModuleName('conekta')->setControllerName('webhook')->setActionName('index');
            $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $pathRequest);
        } else {
            return;
        }
    }
}
