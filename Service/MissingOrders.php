<?php

namespace Conekta\Payments\Service;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaData;
use Conekta\Payments\Helper\Util;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Conekta\Payments\Model\WebhookRepository;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Customer\Model\CustomerFactory;
use Exception;

class MissingOrders
{
    /**
     * @var WebhookRepository
     */
    private WebhookRepository $webhookRepository;

    private ConektaLogger $_conektaLogger;
    private StoreManagerInterface $_storeManager;

    private QuoteFactory $quote;
    /**
     * @var ConektaData|mixed
     */
    private Util $utilHelper;
    private Product $_product;
    private CustomerFactory $customerFactory;
    private CustomerRepositoryInterface $customerRepository;
    private QuoteManagement $quoteManagement;
    private ConektaApiClient $conektaApiClient;

    public function __construct(
        WebhookRepository $webhookRepository,
        ConektaLogger $conektaLogger,
        StoreManagerInterface $storeManager,
        QuoteFactory $quote,
        Product $product,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        QuoteManagement $quoteManagement,
        ConektaApiClient $conektaApiClient
    ){
        $this->webhookRepository = $webhookRepository;
        $this->_conektaLogger = $conektaLogger;
        $this->_storeManager = $storeManager;
        $this->quote = $quote;
        $this->_product = $product;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->quoteManagement = $quoteManagement;
        $this->conektaApiClient = $conektaApiClient;

        $objectManager = ObjectManager::getInstance();
        $this->utilHelper = $objectManager->create(ConektaData::class);
    }

    /**
     * @throws LocalizedException
     */
    public function recover_order($event){
        try {
            //check order en order with external id
            $conektaOrderFound = $this->webhookRepository->findByMetadataOrderId($event);

            if ($conektaOrderFound->getId() != null || !empty($conektaOrderFound->getId()) ) {
                $this->_conektaLogger->info('order is ready', ['order' => $conektaOrderFound, 'is_set', isset($conektaOrderFound)]);
                return;
            }
            $conektaOrder = $event['data']['object'];
            $conektaCustomer = $conektaOrder['customer_info'];
            $metadata = $conektaOrder['metadata'];

            $store = $this->_storeManager->getStore(intval($metadata["store"]));

            $quoteCreated=$this->quote->create(); //Create object of quote

            $quoteCreated->setStore($store); //set store for which you create quote
            $quoteCreated->setIsVirtual($metadata[CartInterface::KEY_IS_VIRTUAL]);

            $quoteCreated->setCurrency();
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


            //add items in quote
            foreach($conektaOrder['line_items']["data"] as $item){
                $product=$this->_product->load($item["metadata"]['product_id']);
                $product->setPrice($this->utilHelper->convertFromApiPrice($item['unit_price']));
                $quoteCreated->addProduct(
                    $product,
                    intval($item['quantity'])
                );
            }

            $shippingNameReceiver = $this->utilHelper->splitName($conektaOrder["shipping_contact"]["receiver"]);
            $shipping_address = [
                'firstname'    => $shippingNameReceiver["firstname"],
                'lastname'     => $shippingNameReceiver["lastname"],
                'street' => [ $conektaOrder["shipping_contact"]["address"]["street1"], $conektaOrder["shipping_contact"]["address"]["street2"] ?? ""],
                'city' => $conektaOrder["shipping_contact"]["address"]["city"],
                'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
                'region' => $conektaOrder["shipping_contact"]["address"]["state"],
                'postcode' => $conektaOrder["shipping_contact"]["address"]["postal_code"],
                'telephone' =>  $conektaOrder["shipping_contact"]["phone"],
                'save_in_address_book' => intval( $conektaOrder["shipping_contact"]["metadata"]["save_in_address_book"]),
                'region_id' => $conektaOrder["shipping_contact"]["metadata"]["region_id"],
                'company'  => $conektaOrder["shipping_contact"]["metadata"]["company"],
            ];
            $billingAddressName = $this->utilHelper->splitName($conektaOrder["fiscal_entity"]["name"]);
            $billing_address = [
                'firstname'    => $billingAddressName["firstname"], //address Details
                'lastname'     => $billingAddressName["lastname"],
                'street' => [ $conektaOrder["fiscal_entity"]["address"]["street1"] , $conektaOrder["fiscal_entity"]["address"]["street2"] ?? "" ],
                'city' => $conektaOrder["fiscal_entity"]["address"]["city"],
                'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
                'region' => $conektaOrder["fiscal_entity"]["address"]["state"],
                'postcode' => $conektaOrder["fiscal_entity"]["address"]["postal_code"],
                'telephone' =>  $conektaCustomer["phone"],
                'save_in_address_book' =>  intval($conektaOrder["fiscal_entity"]["metadata"]["save_in_address_book"]),
                'region_id' =>$conektaOrder["fiscal_entity"]["metadata"]["region_id"],
                'company'  => $conektaOrder["fiscal_entity"]["metadata"]["company"]
            ];

            //Set Address to quote
            $quoteCreated->getBillingAddress()->addData($billing_address);

            $quoteCreated->getShippingAddress()->addData($shipping_address);

            // Collect Rates and Set Shipping & Payment Method
            $shippingAddress=$quoteCreated->getShippingAddress();

            $conektaShippingLines = $conektaOrder["shipping_lines"]["data"];

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingAmount($this->utilHelper->convertFromApiPrice($conektaShippingLines[0]["amount"]))
                ->setShippingMethod($conektaShippingLines[0]["method"]);

            $this->_conektaLogger->info('end $conektaShippingLines');


            //discount lines
            if (isset($conektaOrder["discount_lines"]) && isset($conektaOrder["discount_lines"]["data"])) {
                $quoteCreated->setCustomDiscount($this->getDiscountAmount($conektaOrder["discount_lines"]["data"]));
                $this->applyCoupon($conektaOrder["discount_lines"]["data"],$quoteCreated);
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
                'conekta_customer_id' => $conektaCustomer["customer_id"]
            ];
            $additionalInformation= array_merge($additionalInformation, $this->getAdditionalInformation($conektaOrder));
            $quoteCreated->getPayment()->setAdditionalInformation(   $additionalInformation);
            // Collect Totals & Save Quote
            $quoteCreated->collectTotals()->save();
            $this->_conektaLogger->info('Collect Totals & Save Quote');

            // Create Order From Quote
            $order = $this->quoteManagement->submit($quoteCreated);
            $this->_conektaLogger->info('end submit');


            $increment_id = $order->getRealOrderId();
            if (isset($metadata['remote_ip']) && $metadata['remote_ip']!=null) {
                $order->setRemoteIp($metadata['remote_ip'])->save();
            }
            $order->addCommentToStatusHistory("Missing Order from conekta ". "<a href='". ConfigProvider::URL_PANEL_PAYMENTS ."/".$conektaOrder["id"]. "' target='_blank'>".$conektaOrder["id"]."</a>")
                ->setIsCustomerNotified(true)
                ->save();
            $this->updateConektaReference($conektaOrder["charges"]["data"][0]["id"],  $increment_id);

        } catch (Exception $e) {
            $this->_conektaLogger->error('creating order '.$e->getMessage());
            throw  $e;
        }
    }

    private function getAdditionalInformation(array $conektaOrder) :array{
        switch ($conektaOrder["charges"]["data"][0]["payment_method"]["object"]){
            case "card_payment":
                return [
                    'cc_type' => $conektaOrder["charges"]["data"][0]["payment_method"]["brand"],
                    'card_type' => $conektaOrder["charges"]["data"][0]["payment_method"]["type"],
                    'cc_exp_month' => $conektaOrder["charges"]["data"][0]["payment_method"]["exp_month"],
                    'cc_exp_year' => $conektaOrder["charges"]["data"][0]["payment_method"]["exp_year"],
                    'cc_bin' => null,
                    'cc_last_4' => $conektaOrder["charges"]["data"][0]["payment_method"]["last4"],
                    'card_token' =>  null,
                ];
            case "bank_transfer_payment":
            case "cash_payment":
                return [];
        }
        return [];
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
    private function applyCoupon(array $discountLines, Quote $quote)  {
        foreach ($discountLines as $discountLine){
            if ($discountLine["type"] == "coupon"){
                $quote->setCouponCode($discountLine["code"]);
            }
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
