<?php

namespace Conekta\Payments\Controller\Index;

use Conekta\Payments\Api\EmbedFormRepositoryInterface;
use Conekta\Payments\Exception\ConektaException;
use Conekta\Payments\Helper\ConektaOrder;
use Conekta\Payments\Logger\Logger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Webapi\Exception;

class CreateOrder extends Action implements HttpPostActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    /**
     * @var Json
     */
    protected $jsonHelper;
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ConektaOrder
     */
    protected $conektaOrderHelper;
    /**
     * @var EmbedFormRepositoryInterface
     */
    private $embedFormRepository;
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * CreateOrder constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $jsonFactory
     * @param ConektaOrder $conektaOrderHelper
     * @param Logger $logger
     * @param EmbedFormRepositoryInterface $embedFormRepository
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $jsonFactory,
        ConektaOrder $conektaOrderHelper,
        Logger $logger,
        EmbedFormRepositoryInterface $embedFormRepository,
        Session $checkoutSession
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->resultJsonFactory = $jsonFactory;
        $this->conektaOrderHelper = $conektaOrderHelper;
        $this->embedFormRepository = $embedFormRepository;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $isAjax =  $this->getRequest()->isXmlHttpRequest();
        $response = [];
        
        $resultJson = $this->resultJsonFactory->create();
        $orderParams = [];
        if ($isAjax) {
            try {
                /** @var \Magento\Framework\Controller\Result\Json $resultJson */
                $data = $this->getRequest()->getPostValue();
                $guestEmail = $data['guestEmail'];
                
                //generate order params
                $orderParams = $this->conektaOrderHelper->generateOrderParams($guestEmail);

                $quoteSession = $this->checkoutSession->getQuote();

                //genrates checkout form
                $order = $this->embedFormRepository->generate(
                    $quoteSession->getId(),
                    $orderParams,
                    $quoteSession->getSubtotal()
                );
                
                $response['checkout_id'] = $order->getCheckout()->getId();
            } catch (\Exception $e) {
                $errorMessage = 'Ha ocurrido un error inesperado. Notifique al dueño de la tienda.';
                $errorMessage = $e->getMessage();
                if ($e instanceof ConektaException) {
                    $errorMessage = $e->getMessage();
                } else {
                    $this->logger->critical($e, $orderParams);
                }

                $resultJson->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
                $response['error_message'] = $errorMessage;
            }
        }
        
        $resultJson->setData($response);
        return $resultJson;
    }
}
