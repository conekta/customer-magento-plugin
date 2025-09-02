<?php

namespace Conekta\Payments\Plugin;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Logger\Logger;
use Conekta\Payments\Model\ConektaQuoteRepository;
use Conekta\Payments\Model\ConektaSalesOrderFactory;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;

class GuestPaymentInformationManagementPlugin
{
    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * @var ConektaQuoteRepository
     */
    private $conektaQuoteRepository;
    
    /**
     * @var ConektaApiClient
     */
    private $conektaApiClient;
    
    /**
     * @var ConektaSalesOrderFactory
     */
    private $conektaSalesOrderFactory;
    
    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;
    
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param Logger $logger
     * @param ConektaQuoteRepository $conektaQuoteRepository
     * @param ConektaApiClient $conektaApiClient
     * @param ConektaSalesOrderFactory $conektaSalesOrderFactory
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param OrderFactory $orderFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Logger $logger,
        ConektaQuoteRepository $conektaQuoteRepository,
        ConektaApiClient $conektaApiClient,
        ConektaSalesOrderFactory $conektaSalesOrderFactory,
        GuestCartRepositoryInterface $guestCartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->conektaQuoteRepository = $conektaQuoteRepository;
        $this->conektaApiClient = $conektaApiClient;
        $this->conektaSalesOrderFactory = $conektaSalesOrderFactory;
        $this->guestCartRepository = $guestCartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Around save payment information and place order
     *
     * @param GuestPaymentInformationManagement $subject
     * @param callable $proceed
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return int
     * @throws LocalizedException
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagement $subject,
        callable $proceed,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ) {
        $this->logger->info('GuestPaymentInformationManagementPlugin :: aroundSavePaymentInformationAndPlaceOrder', [
            'cartId' => $cartId,
            'method' => $paymentMethod->getMethod(),
            'additionalData' => $paymentMethod->getAdditionalData()
        ]);
        
        // Check if this is a Conekta EmbedForm iframe payment
        $additionalData = $paymentMethod->getAdditionalData();
        $isConektaEmbedForm = $paymentMethod->getMethod() === 'conekta_ef';
        $isIframePayment = isset($additionalData['iframe_payment']) && $additionalData['iframe_payment'] === true;
        
        if ($isConektaEmbedForm && $isIframePayment) {
            $this->logger->info('GuestPaymentInformationManagementPlugin :: detected iframe payment, bypassing standard flow');
            
            // For iframe payments, the order might already be created by the webhook
            // Let's check if an order already exists for this Conekta order
            $conektaOrderId = $additionalData['order_id'] ?? null;
            if ($conektaOrderId) {
                $existingOrder = $this->findExistingOrder($conektaOrderId);
                if ($existingOrder) {
                    $this->logger->info('GuestPaymentInformationManagementPlugin :: returning existing order', [
                        'order_id' => $existingOrder->getId()
                    ]);
                    return $existingOrder->getId();
                }
            }
            
            // If no existing order, we need to create one
            // For now, we'll return an error indicating this needs to be handled
            throw new LocalizedException(__('Iframe payment processing not yet fully implemented. Order ID: %1', $conektaOrderId ?? 'unknown'));
        }
        
        // For non-iframe payments, use the original method
        return $proceed($cartId, $email, $paymentMethod, $billingAddress);
    }

    /**
     * Handle iframe payment completion
     *
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return int
     * @throws LocalizedException
     */
    private function handleIframePayment(
        string $cartId,
        string $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): int {
        $additionalData = $paymentMethod->getAdditionalData();
        $conektaOrderId = $additionalData['order_id'] ?? null;
        
        if (!$conektaOrderId) {
            throw new LocalizedException(__('Missing Conekta order ID for iframe payment'));
        }
        
        try {
            // Get the quote from Conekta mapping
            $conektaQuote = $this->conektaQuoteRepository->getByConektaOrderId($conektaOrderId);
            
            // Verify the Conekta order is paid
            $conektaOrder = $this->conektaApiClient->getOrderByID($conektaOrderId);
            if ($conektaOrder->getPaymentStatus() !== 'paid') {
                throw new LocalizedException(__('Payment not completed in Conekta'));
            }
            
            // Check if order already exists
            $existingOrder = $this->findExistingOrder($conektaOrderId);
            if ($existingOrder) {
                $this->logger->info('GuestPaymentInformationManagementPlugin :: order already exists', [
                    'order_id' => $existingOrder->getId()
                ]);
                return $existingOrder->getId();
            }
            
            // Create order from quote
            $orderId = $this->createOrderFromIframePayment($conektaQuote->getQuoteId(), $paymentMethod);
            
            $this->logger->info('GuestPaymentInformationManagementPlugin :: iframe order created', [
                'order_id' => $orderId
            ]);
            
            return $orderId;
            
        } catch (\Exception $e) {
            $this->logger->error('GuestPaymentInformationManagementPlugin :: error: ' . $e->getMessage());
            throw new LocalizedException(__('Error processing iframe payment: %1', $e->getMessage()));
        }
    }
    
    /**
     * Find existing order by Conekta order ID
     *
     * @param string $conektaOrderId
     * @return \Magento\Sales\Model\Order|null
     */
    private function findExistingOrder(string $conektaOrderId)
    {
        try {
            // First, find the mapping in conekta_sales_order table
            $conektaSalesOrder = $this->conektaSalesOrderFactory->create();
            $conektaSalesOrder->loadByConektaOrderId($conektaOrderId);
            
            if (!$conektaSalesOrder->getId()) {
                return null;
            }
            
            // Then get the actual Magento order
            $order = $this->orderFactory->create();
            $order->loadByIncrementId($conektaSalesOrder->getIncrementOrderId());
            
            return $order->getId() ? $order : null;
        } catch (\Exception $e) {
            $this->logger->error('Error finding existing order: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create order from iframe payment
     *
     * @param int $quoteId
     * @param PaymentInterface $paymentMethod
     * @return int
     * @throws LocalizedException
     */
    private function createOrderFromIframePayment(int $quoteId, PaymentInterface $paymentMethod): int
    {
        // This is a simplified implementation
        // In a real scenario, you'd need to handle the order creation process
        // including setting payment information, validating the quote, etc.
        
        throw new LocalizedException(__('Order creation from iframe payment not yet implemented'));
    }
} 