<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\WebhookRepository;
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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\App\Request\InvalidRequestException;

class Index extends Action implements CsrfAwareActionInterface
{
    private const EVENT_WEBHOOK_PING = 'webhook_ping';
    private const EVENT_ORDER_CREATED = 'order.created';
    private const EVENT_ORDER_PENDING_PAYMENT = 'order.pending_payment';
    private const EVENT_ORDER_PAID = 'order.paid';
    private const EVENT_ORDER_EXPIRED = 'order.expired';
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

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RawFactory $resultRawFactory
     * @param Data $helper
     * @param ConektaLogger $conektaLogger
     * @param WebhookRepository $webhookRepository
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        ConektaLogger $conektaLogger,
        WebhookRepository $webhookRepository
    ) {
        parent::__construct($context);
        $this->_conektaLogger = $conektaLogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->webhookRepository = $webhookRepository;
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
        $this->_conektaLogger->info('Controller Index :: execute');

        $response = Response::STATUS_CODE_200;
        $resultRaw = $this->resultRawFactory->create();

        try {
            $body = $this->helper->jsonDecode($this->getRequest()->getContent());

            if (!$body || $this->getRequest()->getMethod() !== 'POST') {
                return Response::STATUS_CODE_400;
            }

            $event = $body['type'];

            $this->_conektaLogger->info('Controller Index :: execute body json ', ['event' => $event]);
            $this->validate_order_exist($body);

            switch ($event) {
                case self::EVENT_WEBHOOK_PING:
                    break;
                case self::EVENT_ORDER_CREATED:
                case self::EVENT_ORDER_PENDING_PAYMENT:
                    $order = $this->webhookRepository->findByMetadataOrderId($body);
                    if (!$order->getId()) {
                        $response = Response::STATUS_CODE_400;
                    }
                    break;
                
                case self::EVENT_ORDER_PAID:
                    $this->webhookRepository->payOrder($body);
                    break;
                
                case self::EVENT_ORDER_EXPIRED:
                    $this->webhookRepository->expireOrder($body);
                    break;
            }

        } catch (Exception $e) {
            $this->_conektaLogger->error('Controller Index :: '. $e->getMessage());
            $response = Response::STATUS_CODE_400;
        }
        
        return $resultRaw->setHttpResponseCode($response);
    }

    /**
     * @throws LocalizedException
     */
    public function validate_order_exist($event){

        if ($event['type'] != self::EVENT_ORDER_CREATED){
            return ;
        }

        //check order en order with external id
        $order = $this->webhookRepository->findByMetadataOrderId($event);
        if ($order->getId()) {
            return;
        }





       // check order en api
        // create order en magento
    }
}
