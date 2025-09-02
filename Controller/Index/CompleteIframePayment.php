<?php

namespace Conekta\Payments\Controller\Index;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Logger\Logger;
use Conekta\Payments\Model\ConektaQuoteRepository;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Exception\LocalizedException;

class CompleteIframePayment extends Action implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    
    /**
     * @var Json
     */
    protected $jsonHelper;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var Session
     */
    private $checkoutSession;
    
    /**
     * @var ConektaQuoteRepository
     */
    private $conektaQuoteRepository;
    
    /**
     * @var ConektaApiClient
     */
    private $conektaApiClient;
    
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;
    
    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;
    
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * CompleteIframePayment constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param Json $jsonHelper
     * @param Logger $logger
     * @param Session $checkoutSession
     * @param ConektaQuoteRepository $conektaQuoteRepository
     * @param ConektaApiClient $conektaApiClient
     * @param CartManagementInterface $cartManagement
     * @param GuestCartManagementInterface $guestCartManagement
     * @param QuoteFactory $quoteFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Json $jsonHelper,
        Logger $logger,
        Session $checkoutSession,
        ConektaQuoteRepository $conektaQuoteRepository,
        ConektaApiClient $conektaApiClient,
        CartManagementInterface $cartManagement,
        GuestCartManagementInterface $guestCartManagement,
        QuoteFactory $quoteFactory
    ) {
        $this->resultJsonFactory = $jsonFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->conektaQuoteRepository = $conektaQuoteRepository;
        $this->conektaApiClient = $conektaApiClient;
        $this->cartManagement = $cartManagement;
        $this->guestCartManagement = $guestCartManagement;
        $this->quoteFactory = $quoteFactory;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        if (!$this->getRequest()->isPost()) {
            return $resultJson->setData(['error' => 'Invalid request method']);
        }

        try {
            $data = $this->getRequest()->getPostValue();
            $this->logger->info('CompleteIframePayment :: execute', $data);
            
            $conektaOrderId = $data['order_id'] ?? null;
            $cartId = $data['cartId'] ?? null;
            
            if (!$conektaOrderId || !$cartId) {
                throw new LocalizedException(__('Missing required parameters'));
            }

            // Get the quote from Conekta mapping
            $conektaQuote = $this->conektaQuoteRepository->getByConektaOrderId($conektaOrderId);
            $quote = $this->quoteFactory->create()->load($conektaQuote->getQuoteId());
            
            if (!$quote->getId()) {
                throw new LocalizedException(__('Quote not found'));
            }

            // Verify the Conekta order is paid
            $conektaOrder = $this->conektaApiClient->getOrderByID($conektaOrderId);
            if ($conektaOrder->getPaymentStatus() !== 'paid') {
                throw new LocalizedException(__('Payment not completed'));
            }

            // Set payment information on the quote
            $payment = $quote->getPayment();
            $payment->setMethod('conekta_ef');
            
            // Set additional information from the iframe payment
            foreach ($data['paymentMethod']['additional_data'] as $key => $value) {
                $payment->setAdditionalInformation($key, $value);
            }
            
            // Place the order
            $orderId = $quote->getCustomerEmail() 
                ? $this->cartManagement->placeOrder($quote->getId())
                : $this->guestCartManagement->placeOrder($cartId);

            $this->logger->info('CompleteIframePayment :: order placed', ['order_id' => $orderId]);
            
            return $resultJson->setData([
                'success' => true,
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('CompleteIframePayment :: error: ' . $e->getMessage());
            return $resultJson->setData([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
} 