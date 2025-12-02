<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Exception\EntityNotFoundException;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Conekta\Payments\Service\MissingOrders;
use Exception;
use Laminas\Http\Response;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Customer\Model\CustomerFactory;
use Magento\Quote\Model\QuoteFactory;


class Index extends Action implements CsrfAwareActionInterface
{
    private const EVENT_WEBHOOK_PING = 'webhook_ping';
    private const EVENT_ORDER_PENDING_PAYMENT = 'order.pending_payment';
    private const EVENT_ORDER_PAID = 'order.paid';
    private const EVENT_ORDER_EXPIRED = 'order.expired';
    private const EVENT_ORDER_CANCELED = 'order.canceled';
    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;
    /**
     * @var RawFactory
     */
    protected RawFactory $resultRawFactory;
    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;
    /**
     * @var WebhookRepository
     */
    private WebhookRepository $webhookRepository;

    private MissingOrders $missingOrder;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RawFactory $resultRawFactory
     * @param Data $helper
     * @param ConektaLogger $conektaLogger
     * @param WebhookRepository $webhookRepository
     * @param MissingOrders $_missingOrders
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        ConektaLogger $conektaLogger,
        WebhookRepository $webhookRepository,
        MissingOrders $_missingOrders
    ) {
        parent::__construct($context);
        $this->_conektaLogger = $conektaLogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->webhookRepository = $webhookRepository;
        $this->missingOrder = $_missingOrders;
    }

    /**
     * Create CSRF Validation Exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * CSRF Validation
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Execute
     *
     * @return int|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $response = Response::STATUS_CODE_200;
        $resultRaw = $this->resultRawFactory->create();

        try {
            $body = $this->helper->jsonDecode($this->getRequest()->getContent());
            
            // Log the raw webhook request immediately
            $this->_conektaLogger->info('Webhook received - Raw request', [
                'content' => $this->getRequest()->getContent(),
                'method' => $this->getRequest()->getMethod()
            ]);
            
            if (!$body || $this->getRequest()->getMethod() !== 'POST') {
                $errorResponse = [
                    'error' => 'Invalid request data',
                    'message' => 'The request data is either empty or the request method is not POST.'
                ];
                $this->_conektaLogger->error('Webhook :: Invalid request', ['body' => $body, 'method' => $this->getRequest()->getMethod()]);
                return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_400);
            }

            $chargesData = $body['data']['object']['charges']['data'] ?? [];
            $paymentMethodObject = $chargesData[0]['payment_method']['object'] ?? null;

            $event = $body['type'];

            $this->_conektaLogger->info('Controller Index :: execute body json ', ['event' => $event, 'payment_method' => $paymentMethodObject]);

            // Process webhook based on event type
            switch ($event) {
                case self::EVENT_WEBHOOK_PING:
                    $this->_conektaLogger->info('Webhook :: Ping received');
                    break;
                    
                case self::EVENT_ORDER_PENDING_PAYMENT:
                    $this->_conektaLogger->info('Webhook :: Processing pending_payment event', [
                        'payment_method' => $paymentMethodObject
                    ]);
                    
                    if ($paymentMethodObject === null || !$this->isCardPayment($paymentMethodObject)){
                        try {
                            $this->missingOrder->recover_order($body);
                        } catch (Exception $e) {
                            $this->_conektaLogger->error('Webhook :: Error recovering order in pending_payment: ' . $e->getMessage());
                        }
                    }
                    
                    try {
                        $order = $this->webhookRepository->findByMetadataOrderId($body);
                        if (!$order->getId()) {
                            // Para métodos de pago offline (como pay_by_bank), es normal que la orden
                            // aún no exista en pending_payment, así que no es un error
                            if ($this->isOfflinePaymentMethod($paymentMethodObject)) {
                                $this->_conektaLogger->info('Webhook :: Pending payment for offline method, order will be created later', [
                                    'payment_method' => $paymentMethodObject
                                ]);
                                // Responder con 200 OK para que Conekta no reintente
                                break;
                            }
                            
                            $errorResponse = [
                                'error' => 'Order not found',
                                'message' => 'The requested order does not exist.'
                            ];
                            $this->_conektaLogger->error('Webhook :: Order not found in pending_payment', ['body' => $body]);
                            return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_404);
                        }
                    } catch (Exception $e) {
                        $this->_conektaLogger->error('Webhook :: Error finding order in pending_payment: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // Para métodos de pago offline, es aceptable que la orden no exista todavía
                        if ($this->isOfflinePaymentMethod($paymentMethodObject)) {
                            $this->_conektaLogger->info('Webhook :: Pending payment for offline method, accepting webhook', [
                                'payment_method' => $paymentMethodObject
                            ]);
                            // Responder con 200 OK
                            break;
                        }
                        
                        // Para otros métodos, retornar error
                        $errorResponse = [
                            'error' => 'Error processing webhook',
                            'message' => $e->getMessage()
                        ];
                        return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_500);
                    }
                    break;
                    
                case self::EVENT_ORDER_PAID:
                    $this->_conektaLogger->info('Webhook :: Processing paid event');
                    
                    $chargesData = $body['data']['object']['charges']['data'] ?? [];
                    $paymentMethodObject = $chargesData[0]['payment_method']['object'] ?? null;
                    
                    // For BNPL payments, check if order exists before recovery
                    $isBnpl = $this->isBnplPayment($paymentMethodObject);
                    if ($isBnpl) {
                        $this->_conektaLogger->info('Webhook :: BNPL payment detected');
                    }
                    
                    if ($paymentMethodObject !== null && $this->isCardPayment($paymentMethodObject)){
                        try {
                            $this->missingOrder->recover_order($body);
                        } catch (Exception $e) {
                            $this->_conektaLogger->error('Webhook :: Error recovering order in paid event: ' . $e->getMessage());
                        }
                    }
                    
                    $this->webhookRepository->payOrder($body);
                    break;
                
                case self::EVENT_ORDER_EXPIRED:
                case self::EVENT_ORDER_CANCELED:
                    $this->_conektaLogger->info('Webhook :: Processing ' . $event . ' event');
                    $this->webhookRepository->expireOrder($body);
                    break;
                    
                default:
                    $this->_conektaLogger->warning('Webhook :: Unknown event type: ' . $event);
                    break;
            }

        } catch (EntityNotFoundException $e) {
            $this->_conektaLogger->error('Webhook :: EntityNotFoundException - ' . $e->getMessage());
            $errorResponse = [
                'error' => 'Entity Not Found',
                'message' => $e->getMessage(),
            ];
            return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_404);
        } catch (Exception $e) {
            $this->_conektaLogger->error('Webhook :: Exception - ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $errorResponse = [
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ];
            return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_500);
        }
        
        $this->_conektaLogger->info('Webhook :: Responding with 200 OK');
        return $resultRaw->setHttpResponseCode($response);
    }

    private function sendJsonResponse($data, $httpStatusCode)
    {
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setHttpResponseCode($httpStatusCode);
        $resultRaw->setHeader('Content-Type', 'application/json', true);
        $resultRaw->setContents( $this->helper->jsonEncode(($data)));

        return $resultRaw;
    }
    private function isCardPayment(string $paymentMethod):bool {
        return $paymentMethod == "card_payment";
    }

    /**
     * Check if payment method is BNPL
     *
     * @param string|null $paymentMethod
     * @return bool
     */
    private function isBnplPayment(?string $paymentMethod): bool {
        return $paymentMethod === "bnpl_payment";
    }

    /**
     * Check if payment method is an offline payment method
     *
     * @param string|null $paymentMethod
     * @return bool
     */
    private function isOfflinePaymentMethod(?string $paymentMethod): bool {
        if ($paymentMethod === null) {
            return false;
        }
        
        $offlineMethods = [
            'pay_by_bank_payment',
            'cash_payment',
            'bank_transfer_payment',
            'bnpl_payment'
        ];
        
        return in_array($paymentMethod, $offlineMethods);
    }
}
