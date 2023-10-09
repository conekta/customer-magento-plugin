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
use Magento\Customer\Model\CustomerFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Model\Product;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Magento\Quote\Model\QuoteManagement;
use  Magento\Customer\Api\CustomerRepositoryInterface;

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
    private CustomerRepositoryInterface $customerRepository;

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
     * @param CustomerRepositoryInterface $customerRepository
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
        QuoteManagement $quoteManagement,
        CustomerRepositoryInterface $customerRepository
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
        $this->customerRepository = $customerRepository;
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
            $this->_conektaLogger->info('validate_order_exist  :: execute body json ', ['event' => $event['type']]);

            if ($event['type'] != self::EVENT_ORDER_PAID){
                return ;
            }

            //check order en order with external id
            $conektaOrderFound = $this->webhookRepository->findByMetadataOrderId($event);

            if ($conektaOrderFound->getId() != null || !empty($conektaOrderFound->getId()) ) {
                $this->_conektaLogger->info('order is ready', ['order' => $conektaOrderFound, 'is_set', isset($conektaOrderFound)]);
                return;
            }
            $conektaOrder = $event['data']['object'];
            $conektaCustomer = $conektaOrder['customer_info'];
            $metadata = $conektaOrder['metadata'];
            $this->_conektaLogger->info('after validate order ', ['store'=> $metadata["store"]]);



            $store = $this->_storeManager->getStore(intval($metadata["store"]));

            $this->_conektaLogger->info('store', ['store_id'=> $store->getId()]);



            $quoteCreated=$this->quote->create(); //Create object of quote
            $this->_conektaLogger->info('end quoting creating');

            $quoteCreated->setStore($store); //set store for which you create quote
            $this->_conektaLogger->info('end set store', ['currency'=> $conektaOrder["currency"]]);

            $quoteCreated->setCurrency();
            $this->_conektaLogger->info('end set current', [
                'currency=> ', $conektaOrder["currency"],
                'customer_custom_reference',$conektaCustomer['customer_custom_reference']]
            );

            $quoteCreated->setCustomerId(null);
            if (!$conektaCustomer['customer_custom_reference']){
                $customer = $this->customerFactory->create();
                $customer->setWebsiteId($store->getWebsiteId());
                $customer->loadByEmail($conektaCustomer['email']);// load customer by email address
                $this->_conektaLogger->info('end customer', ['email' =>$conektaCustomer['email'] ]);

                if(!$customer->getEntityId()){
                    //If not available then create this customer
                    $this->_conektaLogger->info('start create customer');

                    $customer->setWebsiteId($store->getWebsiteId())
                        ->setStore($store)
                        ->setFirstname($conektaOrder["shipping_contact"]["receiver"])
                        ->setLastname('doe')
                        ->setEmail($conektaCustomer['email'])
                        ->setPassword($conektaCustomer['email']);
                    $customer->save();
                    $this->_conektaLogger->info('end create customer');
                }
                $customer= $this->customerRepository->getById($customer->getEntityId());
                $quoteCreated->assignCustomer($customer); //Assign quote to customer
           }

            $this->_conektaLogger->info('end quote');

            //add items in quote
            foreach($conektaOrder['line_items']["data"] as $item){
                $product=$this->_product->load($item["metadata"]['product_id']);
                $product->setPrice($item['unit_price']);
                $quoteCreated->addProduct(
                    $product,
                    intval($item['quantity'])
                );
            }
            $this->_conektaLogger->info('end products', ['save_in_address_book' =>$metadata["save_in_address_book"]]);

            $shipping_address = [
                        'firstname'    => $conektaOrder["shipping_contact"]["receiver"], //address Details
                        'lastname'     => 'Doe',
                        'street' => $conektaOrder["shipping_contact"]["address"]["street1"],
                        'city' => $conektaOrder["shipping_contact"]["address"]["city"],
                        'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
                        'region' => $conektaOrder["shipping_contact"]["address"]["state"],
                        'postcode' => $conektaOrder["shipping_contact"]["address"]["postal_code"],
                        'telephone' =>  $conektaOrder["shipping_contact"]["phone"],
                        'save_in_address_book' =>  intval( $metadata["save_in_address_book"]),
                        'region_id' => $metadata["shipping_region_id"] ?? "941"
            ];
            $billing_address = [
                'firstname'    =>$conektaOrder["fiscal_entity"]["name"], //address Details
                'lastname'     => 'Doe',
                'street' => $conektaOrder["fiscal_entity"]["address"]["street1"],
                'city' => $conektaOrder["fiscal_entity"]["address"]["city"],
                'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
                'region' => $conektaOrder["fiscal_entity"]["address"]["state"],
                'postcode' => $conektaOrder["fiscal_entity"]["address"]["postal_code"],
                'telephone' =>  $conektaCustomer["phone"],
                'save_in_address_book' =>  intval( $metadata["save_in_address_book"]),
                'region_id' => $metadata["billing_region_id"] ?? "941"
            ];
            $this->_conektaLogger->info('$billing_address', ['data'=>$billing_address]);
            $this->_conektaLogger->info('$shipping_address', ['data'=>$shipping_address]);

            //Set Address to quote
            $quoteCreated->getBillingAddress()->addData($billing_address);

            $quoteCreated->getShippingAddress()->addData($shipping_address);

            // Collect Rates and Set Shipping & Payment Method
            $shippingAddress=$quoteCreated->getShippingAddress();

            $conektaShippingLines = $conektaOrder["shipping_lines"]["data"];

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod($conektaShippingLines[0]["method"]); //shipping method

            $this->_conektaLogger->info('end $conektaShippingLines');


            $quoteCreated->setPaymentMethod(ConfigProvider::CODE); //payment method
            $quoteCreated->setInventoryProcessed(false); //not affect inventory
            $quoteCreated->save(); //Now Save quote and your quote is ready
            $this->_conektaLogger->info('end save quote');


            // Set Sales Order Payment
            $quoteCreated->getPayment()->importData(['method' => ConfigProvider::CODE]);
            $additionalInformation = [
                'order_id' =>  $conektaOrder["id"],
                'txn_id' =>  $conektaOrder["charges"]["data"][0]["id"],
                'quote_id'=> $quoteCreated->getId(),
                'payment_method' => $this->getPaymentMethod( $conektaOrder["charges"]["data"][0]["payment_method"]["type"])
            ];
            $quoteCreated->getPayment()->setAdditionalInformation($additionalInformation);
            $this->_conektaLogger->info('Set Sales Order Payment');

            // Collect Totals & Save Quote
            $quoteCreated->collectTotals()->save();
            $this->_conektaLogger->info('Collect Totals & Save Quote');

            // Create Order From Quote
            $order = $this->quoteManagement->submit($quoteCreated);
            $this->_conektaLogger->info('end submit');


            $increment_id = $order->getRealOrderId();

            $order->addCommentToStatusHistory("Missing Order from conekta " . $increment_id)
                ->setIsCustomerNotified(true)
                ->save();
            $this->_conektaLogger->info('end');

        } catch (Exception $e) {
            $this->_conektaLogger->error('creating order '.$e->getMessage());
        }
    }

    private function getPaymentMethod(string $type) :string {
        switch ($type){
            case "card_payment":
                return ConfigProvider::PAYMENT_METHOD_CREDIT_CARD;
            case "cash_payment":
                return ConfigProvider::PAYMENT_METHOD_CASH;
            case "bank_transfer_payment":
                return ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER;
        }
        return "";
    }

}
