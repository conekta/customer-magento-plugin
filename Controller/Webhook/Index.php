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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory as CustomerFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Model\Product;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Quote\Model\QuoteManagement;


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
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $_storeManager;

    private CustomerFactory $customerFactory;

    private QuoteFactory $quote;

    private Product $_product ;

    private QuoteManagement $quoteManagement;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RawFactory $resultRawFactory
     * @param Data $helper
     * @param ConektaLogger $conektaLogger
     * @param WebhookRepository $webhookRepository
     * @param StoreManagerInterface $storeManager
     * @param CustomerFactory $customerFactory
     * @param QuoteFactory $quote
     * @param Product $product
     * @param QuoteManagement $quoteManagement
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        ConektaLogger $conektaLogger,
        WebhookRepository $webhookRepository,
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory,
        QuoteFactory $quote,
        Product $product,
        QuoteManagement $quoteManagement
    ) {
        parent::__construct($context);
        $this->_conektaLogger = $conektaLogger;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->webhookRepository = $webhookRepository;
        $this->_storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->quote = $quote;
        $this->_product = $product;
        $this->quoteManagement = $quoteManagement;
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
                $errorResponse = [
                    'error' => 'Invalid request data',
                    'message' => 'The request data is either empty or the request method is not POST.'
                ];
                return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_400);
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
                        $errorResponse = [
                            'error' => 'Order not found',
                            'message' => 'The requested order does not exist.'
                        ];
                        return $this->sendJsonResponse($errorResponse, Response::STATUS_CODE_404);
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
            $errorResponse = [
                'error' => 'Internal Server Error',
                'message' => 'An error occurred while processing the request.'
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

    /**
     * @throws LocalizedException
     */
    public function validate_order_exist($event){
        try {
            if ($event['type'] != self::EVENT_ORDER_PAID){
                return ;
            }

            //check order en order with external id
            $order = $this->webhookRepository->findByMetadataOrderId($event);
            if ($order->getId()) {
                return;
            }
            $conektaOrder = $event['data']['object'];
            $metadata = $conektaOrder['metadata'];
            $conektaCustomer = $conektaOrder['customer_info'];

            $store = $this->_storeManager->getStore($metadata["store"]);
            $websiteId = $store->getWebsiteId();

            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($conektaCustomer['email']);// load customer by email address

            $quote=$this->quote->create(); //Create object of quote
            $quote->setStore($store); //set store for which you create quote
            $quote->setCurrency($conektaOrder["currency"]);
            $quote->assignCustomer($customer); //Assign quote to customer


            //add items in quote
            foreach($conektaOrder['line_items']["data"] as $item){
                $product=$this->_product->load($item["metadata"]['product_id']);
                $product->setPrice($item['unit_price']);
                $quote->addProduct(
                    $product,
                    intval($item['quantity'])
                );
            }
            $shipping_address = [
                        'firstname'    => $conektaOrder["shipping_contact"]["receiver"], //address Details
                        'lastname'     => 'Doe',
                        'street' => $conektaOrder["shipping_contact"]["address"]["street1"],
                        'city' => $conektaOrder["shipping_contact"]["address"]["city"],
                        'country_id' => $conektaOrder["shipping_contact"]["address"]["country"],
                        'region' => $conektaOrder["shipping_contact"]["address"]["state"],
                        'postcode' => $conektaOrder["shipping_contact"]["address"]["postal_code"],
                        'telephone' =>  $conektaOrder["shipping_contact"]["phone"],
                        'save_in_address_book' =>   $metadata["save_in_address_book"]
            ];
            $billing_address = [
                'firstname'    =>$conektaOrder["fiscal_entity"]["name"], //address Details
                'lastname'     => 'Doe',
                'street' => $conektaOrder["fiscal_entity"]["address"]["street1"],
                'city' => $conektaOrder["fiscal_entity"]["address"]["city"],
                'country_id' => $conektaOrder["fiscal_entity"]["address"]["country"],
                'region' => $conektaOrder["fiscal_entity"]["address"]["state"],
                'postcode' => $conektaOrder["fiscal_entity"]["address"]["postal_code"],
                'telephone' =>  $conektaCustomer["phone"],
                'save_in_address_book' =>   $metadata["save_in_address_book"]
            ];
            //Set Address to quote
            $quote->getBillingAddress()->addData($billing_address);

            $quote->getShippingAddress()->addData($shipping_address);

            // Collect Rates and Set Shipping & Payment Method
            $shippingAddress=$quote->getShippingAddress();

            $conektaShippingLines = $conektaOrder["shipping_lines"]["data"];

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod($conektaShippingLines[0]["method"]); //shipping method

            $quote->setPaymentMethod(ConfigProvider::CODE); //payment method
            $quote->setInventoryProcessed(false); //not affect inventory
            $quote->save(); //Now Save quote and your quote is ready

            // Set Sales Order Payment
            $quote->getPayment()->importData(['method' => ConfigProvider::CODE]);

            // Collect Totals & Save Quote
            $quote->collectTotals()->save();

            // Create Order From Quote
            $order = $this->quoteManagement->submit($quote);

            $increment_id = $order->getRealOrderId();

            $order->addCommentToStatusHistory("Missing Order from conekta")
                ->setIsCustomerNotified(true)
                ->save();
        } catch (\Exception $e) {
            $this->_conektaLogger->error($e->getMessage());
        }
    }

}
