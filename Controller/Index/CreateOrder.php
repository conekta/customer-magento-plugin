<?php

namespace Conekta\Payments\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Class CreateOrder
 * @package Conekta\Payment\Controller\Index
 */
class CreateOrder extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonHelper;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var \Conekta\Payments\Helper\ConektaOrder
     */
    protected $conektaOrderHelper;

    /**
     * CreateOrder constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Conekta\Payments\Helper\ConektaOrder $conektaOrderHelper
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Conekta\Payments\Helper\ConektaOrder $conektaOrderHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->resultJsonFactory = $jsonFactory;
        $this->conektaOrderHelper = $conektaOrderHelper;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $isAjax =  $this->getRequest()->isXmlHttpRequest();
        $response = [];

        if ($isAjax) {
            try {
                /** @var \Magento\Framework\Controller\Result\Json $resultJson */
                $data = $this->getRequest()->getPostValue();
                $guestEmail = $data['guestEmail'];
                $checkoutId = $this->conektaOrderHelper->createOrder($guestEmail);
                $response['checkout_id'] = $checkoutId;
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($response);
        return $resultJson;
    }
}
