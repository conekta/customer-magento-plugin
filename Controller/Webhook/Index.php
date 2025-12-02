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
            
            if ($body && isset($body['data']['object']['charges']['data'][0]['payment_method']['object'])) {
                $paymentMethodObject = $body['data']['object']['charges']['data'][0]['payment_method']['object'];
                if ($this->isBnplPayment($paymentMethodObject)) {
                    $this->_conektaLogger->info('BNPL payment detected - adding 25 second delay at start of execute');
                    sleep(25);
                } 
            }
            
            if (!$body || $this->getRequest()->getMethod() !== 'POST') {
                $errorResponse = [
                    'error' => 'Invalid request data',
                    'message' => 'The request data is either empty or the request method is not POST.'
                ];
                return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_400);
            }

            $chargesData = $body['data']['object']['charges']['data'] ?? [];
            $paymentMethodObject = $chargesData[0]['payment_method']['object'] ?? null;

            $event = $body['type'];

            $this->_conektaLogger->info('Controller Index :: execute body json ', ['event' => $event]);

            switch ($event) {
                case self::EVENT_WEBHOOK_PING:
                    break;
                case self::EVENT_ORDER_PENDING_PAYMENT:
                    if ($paymentMethodObject === null || !$this->isCardPayment($paymentMethodObject)){
                        $this->missingOrder->recover_order($body);
                    }
                    $order = $this->webhookRepository->findByMetadataOrderId($body);
                    if (!$order->getId()) {
                        $errorResponse = [
                            'error' => 'Order not found',
                            'message' => 'The requested order does not exist.'
                        ];
                        return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_404);
                    }
                    break;
                case self::EVENT_ORDER_PAID:
                    $chargesData = $body['data']['object']['charges']['data'] ?? [];
                    $paymentMethodObject = $chargesData[0]['payment_method']['object'] ?? null;
                    
                    if ($paymentMethodObject !== null && 
                        ($this->isCardPayment($paymentMethodObject) || $this->isPayByBankPayment($paymentMethodObject))
                    ) {
                        $this->missingOrder->recover_order($body);
                    }
                    $this->webhookRepository->payOrder($body);
                    break;
                
                case self::EVENT_ORDER_EXPIRED:
                case self::EVENT_ORDER_CANCELED:
                    $this->webhookRepository->expireOrder($body);
                    break;
            }

        }catch (EntityNotFoundException $e) {
            $errorResponse = [
                'error' => 'Entity Not Found',
                'message' => $e->getMessage(),
            ];
            return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_404);
        }
        catch (Exception $e) {
            $this->_conektaLogger->error('Controller Index :: '. $e->getMessage());
            $errorResponse = [
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ];
            return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_500);
        }
        
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
     * Check if payment method is Pay By Bank
     *
     * @param string|null $paymentMethod
     * @return bool
     */
    private function isPayByBankPayment(?string $paymentMethod): bool {
        return $paymentMethod === "pay_by_bank_payment";
    }
}
