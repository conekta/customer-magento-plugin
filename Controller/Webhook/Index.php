<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Json\Helper\Data;
use Magento\Payment\Model\Method\Logger;

use Magento\Framework\App\Request\InvalidRequestException;

class Index extends Action implements CsrfAwareActionInterface
{

    private const EVENT_WEBHOOK_PING = 'webhook_ping';
    private const EVENT_ORDER_CREATED = 'order.created';
    private const EVENT_ORDER_PENDING_PAYMENT = 'order.pending_payment';
    private const EVENT_ORDER_PAID = 'order.paid';
    private const EVENT_ORDER_EXPIRED = 'order.expired';

    private const HTTP_BAD_REQUEST_CODE = 400;
    private const HTTP_OK_REQUEST_CODE = 200;

    protected $resultJsonFactory;

    protected $resultRawFactory;

    protected $helper;

    private $logger;

    private $_conektaLogger;

    private $webhookRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        Logger $logger,
        ConektaLogger $conektaLogger,
        WebhookRepository $webhookRepository
    ) {
        parent::__construct($context);
        $this->_conektaLogger = $conektaLogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->webhookRepository = $webhookRepository;
    }

    /** * @inheritDoc */
    public function createCsrfValidationException(RequestInterface $request): ?       InvalidRequestException
    {
        return null;
    }
    /** * @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $this->_conektaLogger->info('Controller Index :: execute');

        $body = null;
        $response = self::HTTP_BAD_REQUEST_CODE;
        
        try {
            $resultRaw = $this->resultRawFactory->create();

            $body = $this->helper->jsonDecode($this->getRequest()->getContent());

            if (!$body || $this->getRequest()->getMethod() !== 'POST') {
                return $response;
            }

            $event = $body['type'];

            $this->_conektaLogger->info('Controller Index :: execute body json ', ['event' => $event]);

            $response = self::HTTP_OK_REQUEST_CODE;
            switch ($event) {
                case self::EVENT_WEBHOOK_PING:
                    $response = self::HTTP_OK_REQUEST_CODE;
                    break;
                
                case self::EVENT_ORDER_CREATED:
                case self::EVENT_ORDER_PENDING_PAYMENT:
                    $order = $this->webhookRepository->findByMetadataOrderId($body);
                    if (!$order->getId()) {
                        $response = self::HTTP_BAD_REQUEST_CODE;
                    }
                    break;
                
                case self::EVENT_ORDER_PAID:
                    $this->webhookRepository->payOrder($body);
                    break;
                
                case self::EVENT_ORDER_EXPIRED:
                    $this->webhookRepository->expireOrder($body);
                    break;
                
                default:
                    //If the event not exist, response Bad Request
                    $response = self::HTTP_BAD_REQUEST_CODE;
            }

        } catch (Exception $e) {
            $this->_conektaLogger->error('Controller Index :: '. $e->getMessage());
            $response = self::HTTP_BAD_REQUEST_CODE;
        }
        
        return $resultRaw->setHttpResponseCode($response);
    }
}
