<?php

namespace Conekta\Payments\Controller;

use Magento\Framework\App\RouterInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;

class Router implements RouterInterface
{

    /**
     * @var \Magento\Framework\App\ActionFactory
     */
    protected $actionFactory;

    /**
     * Response
     *
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $_response;

    /**
     * @var \Conekta\Payments\Helper\Data
     */
    private $_conektaHelper;

    /**
     * @var ConektaLogger
     */
    private $_conektaLogger;

    /**
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\App\ResponseInterface $response
     */
    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\App\ResponseInterface $response,
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
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface
     */
    public function match(\Magento\Framework\App\RequestInterface $request)
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
            $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $pathRequest);
        } else {
            return;
        }
    }
}
