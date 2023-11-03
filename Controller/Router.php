<?php

namespace Conekta\Payments\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url;

class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    protected ActionFactory $actionFactory;

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $_response;

    /**
     * @var ConektaHelper
     */
    private ConektaHelper $_conektaHelper;


    /**
     * @param ActionFactory $actionFactory
     * @param ResponseInterface $response
     * @param ConektaHelper $conektaHelper
     */
    public function __construct(
        ActionFactory $actionFactory,
        ResponseInterface $response,
        ConektaHelper $conektaHelper
    ) {
        $this->actionFactory = $actionFactory;
        $this->_response = $response;
        $this->_conektaHelper = $conektaHelper;
    }

    /**
     * Validate and Match
     *
     * @param RequestInterface $request
     * @throws NoSuchEntityException
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
        }
    }
}
