<?php

namespace Conekta\Payments\Controller\Index;

use Conekta\Payments\Api\EmbedFormRepositoryInterface;
use Conekta\Payments\Exception\ConektaException;
use Conekta\Payments\Logger\Logger;
use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Checkout\Model\Session;

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
     * @var Logger
     */
    protected $logger;
    /**
     * @var \Conekta\Payments\Helper\ConektaOrder
     */
    protected $conektaOrderHelper;

    private $embedFormRepository;

    private $checkoutSession;

    /**
     * CreateOrder constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Conekta\Payments\Helper\ConektaOrder $conektaOrderHelper
     * @param Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Conekta\Payments\Helper\ConektaOrder $conektaOrderHelper,
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
     * @return \Magento\Framework\Controller\ResultInterface
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
                $order = (array)$this->embedFormRepository->generate(
                    $quoteSession->getId(),
                    $orderParams,
                    $quoteSession->getSubtotal()
                );
                
                $response['checkout_id'] = $order['checkout']['id'];
            } catch (\Exception $e) {
                
                $errorMessage = 'Ha ocurrido un error inesperado. Notifique al dueño de la tienda.';
                if ($e instanceof ConektaException) {
                    $errorMessage = $e->getMessage();
                } else {
                    $this->logger->critical($e, $orderParams);
                }

                $resultJson->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST);
                $response['error_message'] = $errorMessage;
            }
        }
        
        $resultJson->setData($response);
        return $resultJson;
    }
}
