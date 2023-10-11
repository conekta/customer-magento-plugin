<?php
namespace Conekta\Payments\Controller\Webhook;

use Conekta\Payments\Helper\Util;
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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Conekta\Payments\Helper\Data as ConektaData;
use Magento\Framework\App\ObjectManager;
use Conekta\Payments\Api\ConektaApiClient;
use Magento\Quote\Api\Data\PaymentInterface;

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
    private Util $utilHelper;

    /**
     * @var ConektaApiClient
     */
    private ConektaApiClient $conektaApiClient;

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
     * @param ConektaApiClient $conektaApiClient
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
        CustomerRepositoryInterface $customerRepository,
        ConektaApiClient $conektaApiClient
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

        $objectManager = ObjectManager::getInstance();
        $this->utilHelper = $objectManager->create(ConektaData::class);
        $this->conektaApiClient = $conektaApiClient;
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
                'currency=> ', $conektaOrder["currency"]]
            );
            $customerName = $this->utilHelper->splitName($conektaCustomer['name']);

            $quoteCreated->setCustomerEmail($conektaCustomer['email']);
            $quoteCreated->setCustomerFirstname($customerName["firstname"]);
            $quoteCreated->setCustomerLastname($customerName["lastname"]);
            $quoteCreated->setCustomerIsGuest(true);
            if (isset($conektaCustomer['customer_custom_reference']) && !empty($conektaCustomer['customer_custom_reference'])){
                $customer = $this->customerFactory->create();
                $customer->setWebsiteId($store->getWebsiteId());
                $customer->load($conektaCustomer['customer_custom_reference']);// load customer by id
                $this->_conektaLogger->info('end customer', ['email' =>$conektaCustomer['email'] ]);

                $customer= $this->customerRepository->getById($customer->getEntityId());
                $quoteCreated->assignCustomer($customer); //Assign quote to customer
           }

            $this->_conektaLogger->info('end quote');

            //add items in quote
            foreach($conektaOrder['line_items']["data"] as $item){
                $product=$this->_product->load($item["metadata"]['product_id']);
                $product->setPrice($this->utilHelper->convertFromApiPrice($item['unit_price']));
                $quoteCreated->addProduct(
                    $product,
                    intval($item['quantity'])
                );
            }
            $this->_conektaLogger->info('end products', ['save_in_address_book' =>$metadata["save_in_address_book"]]);
            $shippingNameReceiver = $this->utilHelper->splitName($conektaOrder["shipping_contact"]["receiver"]);
            $shipping_address = [
                        'firstname'    => $shippingNameReceiver["firstname"],
                        'lastname'     => $shippingNameReceiver["lastname"],
                        'street' => $conektaOrder["shipping_contact"]["address"]["street1"],
                        'city' => $conektaOrder["shipping_contact"]["address"]["city"],
                        'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
                        'region' => $conektaOrder["shipping_contact"]["address"]["state"],
                        'postcode' => $conektaOrder["shipping_contact"]["address"]["postal_code"],
                        'telephone' =>  $conektaOrder["shipping_contact"]["phone"],
                        'save_in_address_book' =>  intval( $metadata["save_in_address_book"]),
                        'region_id' => $metadata["shipping_region_id"] ?? "941"
            ];
            $billingAddressName = $this->utilHelper->splitName($conektaOrder["fiscal_entity"]["name"]);
            $billing_address = [
                'firstname'    => $billingAddressName["firstname"], //address Details
                'lastname'     => $billingAddressName["lastname"],
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
                ->setShippingAmount($this->utilHelper->convertFromApiPrice($conektaShippingLines[0]["amount"]))
                ->setShippingMethod($conektaShippingLines[0]["method"]); //shipping method

            $this->_conektaLogger->info('end $conektaShippingLines');


            //discount lines
            if (isset($conektaOrder["discount_lines"]) && isset($conektaOrder["discount_lines"]["data"])) {
                $quoteCreated->setCustomDiscount($this->getDiscountAmount($conektaOrder["discount_lines"]["data"]));
            }

            $quoteCreated->setPaymentMethod(ConfigProvider::CODE);
            $quoteCreated->setInventoryProcessed(false);
            $quoteCreated->save();
            $this->_conektaLogger->info('end save quote');


            // Set Sales Order Payment
            $quoteCreated->getPayment()->importData(['method' => ConfigProvider::CODE]);
            $additionalInformation = [
                'order_id' =>  $conektaOrder["id"],
                'txn_id' =>  $conektaOrder["charges"]["data"][0]["id"],
                'quote_id'=> $quoteCreated->getId(),
                'payment_method' => $this->getPaymentMethod($conektaOrder["charges"]["data"][0]["payment_method"]["object"]),
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    'cc_type' =>$conektaOrder["charges"]["data"][0]["payment_method"]["brand"],
                    'card_type' => $conektaOrder["charges"]["data"][0]["payment_method"]["type"],
                    'cc_exp_month' => $conektaOrder["charges"]["data"][0]["payment_method"]["exp_month"],
                    'cc_exp_year' => $conektaOrder["charges"]["data"][0]["payment_method"]["exp_year"],
                    'cc_bin' => "",
                    'cc_last_4' => $conektaOrder["charges"]["data"][0]["payment_method"]["last4"],
                    'card_token' =>  "",
                ]
            ];
            $quoteCreated->getPayment()->setAdditionalInformation($additionalInformation);
            // Collect Totals & Save Quote
            $quoteCreated->collectTotals()->save();
            $this->_conektaLogger->info('Collect Totals & Save Quote');

            // Create Order From Quote
            $order = $this->quoteManagement->submit($quoteCreated);
            $this->_conektaLogger->info('end submit');


            $increment_id = $order->getRealOrderId();

            $order->addCommentToStatusHistory("Missing Order from conekta ". "<a href='". ConfigProvider::URL_PANEL_PAYMENTS ."/".$conektaOrder["id"]. "' target='_blank'>".$conektaOrder["id"]."</a>")
                ->setIsCustomerNotified(true)
                ->save();
            $this->updateConektaReference($conektaOrder["charges"]["data"][0]["id"],  $increment_id);

            $this->_conektaLogger->info('end');

        } catch (Exception $e) {
            $this->_conektaLogger->error('creating order '.$e->getMessage());
        }
    }
    private function updateConektaReference(string $chargeId, string $orderId){
         $chargeUpdate= [
             "reference_id"=> $orderId,
         ];
         try {
             $this->conektaApiClient->updateCharge($chargeId,  $chargeUpdate);
         }catch (Exception $e) {
             $this->_conektaLogger->error("updating conekta charge". $e->getMessage(), ["charge_id"=> $chargeId, "reference_id"=> $orderId]);
         }
    }

    private function getDiscountAmount(array $discountLines) :float {
       $discountValue = 0;
       foreach ($discountLines as $discountLine){
           $discountValue += $this->utilHelper->convertFromApiPrice($discountLine["amount"]);
       }

       return $discountValue * -1;
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
