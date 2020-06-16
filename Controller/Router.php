<?php

namespace Conekta\Payments\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\RouterInterface;

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
     * @param \Magento\Framework\App\ActionFactory $actionFactory
     * @param \Magento\Framework\App\ResponseInterface $response
     */
    public function __construct(
        \Magento\Framework\App\ActionFactory $actionFactory,
        \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->actionFactory = $actionFactory;
        $this->_response = $response;
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

        $_identifier = trim($request->getPathInfo(), '/');
        $pathInfo = explode('/', $_identifier);
        $identifier = implode('/', $pathInfo);
        $info = explode('/', $identifier);
        if (count($info) < 3) {
            return;
        }

        if ($info[0] === "conekta" && $info[1] === "webhook" && $info[2] === "listener") {
            $request->setModuleName('conekta')->setControllerName('webhook')->setActionName('index');
            $request->setAlias(\Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS, $_identifier);
        } else {
            return;
        }

        //return $this->actionFactory->create(Forward::class);
    }
}
