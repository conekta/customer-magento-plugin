<?php

namespace Conekta\Payments\Service;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Payments\Model\Ui\EmbedForm\ConfigProvider;
use Conekta\Payments\Model\WebhookRepository;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Exception;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Conekta\Payments\Helper\Util;
use Conekta\Payments\Helper\Data as ConektaData;
class MissingOrders
{
    /**
     * @var WebhookRepository
     */
    private WebhookRepository $webhookRepository;

    private ConektaLogger $_conektaLogger;

    private QuoteManagement $quoteManagement;
    private ConektaApiClient $conektaApiClient;

    protected CartRepositoryInterface $_cartRepository;


    private ObjectManager $objectManager;

    private Util $utilHelper;


    public function __construct(
        WebhookRepository $webhookRepository,
        ConektaLogger $conektaLogger,
        QuoteManagement $quoteManagement,
        ConektaApiClient $conektaApiClient,
        CartRepositoryInterface $cartRepository
    ){
        $this->webhookRepository = $webhookRepository;
        $this->_conektaLogger = $conektaLogger;
        $this->quoteManagement = $quoteManagement;
        $this->conektaApiClient = $conektaApiClient;

        $this->objectManager = ObjectManager::getInstance();
        $this->_cartRepository = $cartRepository;
        $this->utilHelper = $this->objectManager->create(ConektaData::class);
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
            $conektaCustomer = $conektaOrder['customer_info'] ?? [];
            $metadata = $conektaOrder['metadata'];
            $storeId = $metadata['store'];
            $quoteId = $metadata['quote_id'];
            $quoteCreated = $this->_cartRepository->get($quoteId);
            $quoteCreated->setStoreId($storeId);

            $orderFounded = $this->objectManager->create('Magento\Sales\Model\Order')->load($quoteCreated->getReservedOrderId(), OrderInterface::INCREMENT_ID);
            if ($orderFounded->getId() != null || !empty($orderFounded->getId()) ) {
                $this->_conektaLogger->info('order is ready', ['order' => $orderFounded, 'is_set', isset($orderFounded)]);
                return;
            }
            $quoteCreated->setCustomerEmail($conektaCustomer['email'] ?? $quoteCreated->getCustomerEmail());
            $quoteCreated->getPayment()->importData(['method' => ConfigProvider::CODE]);
            $chargeData = $conektaOrder['charges']['data'][0] ?? null;
            $paymentMethodObject = $chargeData['payment_method']['object'] ?? 'null';
            $txnId = $chargeData['id'] ?? null;

            $additionalInformation = [
                'order_id' =>  $conektaOrder["id"],
                'quote_id'=> $quoteCreated->getId(),
                'payment_method' => $this->getPaymentMethod($paymentMethodObject),
                'conekta_customer_id' => $conektaCustomer["customer_id"] ?? null
            ];
            if ($txnId) {
                $additionalInformation['txn_id'] = $txnId;
            }
            $additionalInformation= array_merge($additionalInformation, $this->getAdditionalInformation($chargeData));
            $quoteCreated->getPayment()->setAdditionalInformation($additionalInformation);
            $this->saveMissingFieldsQuote($quoteCreated, $conektaOrder);
            $order = $this->quoteManagement->submit($quoteCreated);
            $order->setStoreId($storeId);
            $order->save();

            $order->addCommentToStatusHistory("Missing Order from conekta ". "<a href='". ConfigProvider::URL_PANEL_PAYMENTS ."/".$conektaOrder["id"]. "' target='_blank'>".$conektaOrder["id"]."</a>")
                ->setIsCustomerNotified(true)
                ->save();
            if ($txnId) {
                $this->updateConektaReference($txnId,  $order->getRealOrderId());
            }
            return ;

        }catch (NoSuchEntityException $e){
            $this->_conektaLogger->error($e->getMessage());
            return;
        }
        catch (Exception | LocalizedException $e) {
            $this->_conektaLogger->error('recovery order '.$e->getMessage());
            throw  $e;
        }
    }

    private function saveMissingFieldsQuote(Quote  $quoteCreated, array $conektaOrder){
        $shippingContact = $conektaOrder["shipping_contact"] ?? [];
        $shippingAddressData = $shippingContact["address"] ?? [];
        $shippingMetadata = $shippingContact["metadata"] ?? [];

        $shippingNameReceiver = $this->utilHelper->splitName($shippingContact["receiver"] ?? "");
        $shipping_address = [
            'firstname'    => $shippingNameReceiver["firstname"] ?? "",
            'lastname'     => $shippingNameReceiver["lastname"] ?? "",
            'street' => [ $shippingAddressData["street1"] ?? "", $shippingAddressData["street2"] ?? ""],
            'city' => $shippingAddressData["city"] ?? "",
            'country_id' => strtoupper($shippingAddressData["country"] ?? ($conektaOrder["fiscal_entity"]["address"]["country"] ?? "")),
            'region' => $shippingAddressData["state"] ?? "",
            'postcode' => $shippingAddressData["postal_code"] ?? "",
            'telephone' =>   $shippingContact["phone"] ?? "5200000000",
            'region_id' => $shippingMetadata["region_id"] ?? null,
            'company'  => $shippingMetadata["company"] ?? "",
        ];

        $fiscalEntity = $conektaOrder["fiscal_entity"] ?? [];
        $fiscalAddress = $fiscalEntity["address"] ?? [];
        $fiscalMetadata = $fiscalEntity["metadata"] ?? [];
        $billingAddressName = $this->utilHelper->splitName($fiscalEntity["name"] ?? "");
        $billing_address = [
            'firstname'    => $billingAddressName["firstname"] ?? "",
            'lastname'     => $billingAddressName["lastname"] ?? "",
            'street' => [ $fiscalAddress["street1"] ?? "" , $fiscalAddress["street2"] ?? "" ],
            'city' => $fiscalAddress["city"] ?? "",
            'country_id' => strtoupper($fiscalAddress["country"] ?? ($shippingAddressData["country"] ?? "")),
            'region' => $fiscalAddress["state"] ?? "",
            'postcode' => $fiscalAddress["postal_code"] ?? "",
            'telephone' => $fiscalEntity["phone"] ??  ($shippingContact["phone"] ?? "5200000000"),
            'region_id' => $fiscalMetadata["region_id"] ?? null,
            'company'  => $fiscalMetadata["company"] ?? ""
        ];

        //Set Address to quote
        $quoteCreated->getBillingAddress()->addData($billing_address);

        $quoteCreated->getShippingAddress()->addData($shipping_address);
    }
    private function getAdditionalInformation(?array $chargeData) :array{
        $paymentObject = $chargeData['payment_method']['object'] ?? null;
        switch ($paymentObject){
            case "card_payment":
                return [
                    'cc_type' => $chargeData["payment_method"]["brand"] ?? null,
                    'card_type' => $chargeData["payment_method"]["type"] ?? null,
                    'cc_exp_month' => $chargeData["payment_method"]["exp_month"] ?? null,
                    'cc_exp_year' => $chargeData["payment_method"]["exp_year"] ?? null,
                    'cc_bin' => null,
                    'cc_last_4' => $chargeData["payment_method"]["last4"] ?? null,
                    'card_token' =>  null,
                ];
            case "bank_transfer_payment":
            case "bnpl_payment":
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

    private function getPaymentMethod(string $type) :string {
        switch ($type){
            case "card_payment":
                return ConfigProvider::PAYMENT_METHOD_CREDIT_CARD;
            case "cash_payment":
                return ConfigProvider::PAYMENT_METHOD_CASH;
            case "bank_transfer_payment":
                return ConfigProvider::PAYMENT_METHOD_BANK_TRANSFER;
            case "bnpl_payment":
                return ConfigProvider::PAYMENT_METHOD_BNPL;
        }
        return "";
    }
}
