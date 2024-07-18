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
            $conektaCustomer = $conektaOrder['customer_info'];
            $metadata = $conektaOrder['metadata'];

            $quoteId = $metadata['quote_id'];
            $quoteCreated = $this->_cartRepository->get($quoteId);

            $orderFounded = $this->objectManager->create('Magento\Sales\Model\Order')->load($quoteCreated->getReservedOrderId(), OrderInterface::INCREMENT_ID);
            if ($orderFounded->getId() != null || !empty($orderFounded->getId()) ) {
                $this->_conektaLogger->info('order is ready', ['order' => $orderFounded, 'is_set', isset($orderFounded)]);
                return;
            }
            $quoteCreated->setCustomerEmail($conektaCustomer['email']);
            $quoteCreated->getPayment()->importData(['method' => ConfigProvider::CODE]);
            $additionalInformation = [
                'order_id' =>  $conektaOrder["id"],
                'txn_id' =>  $conektaOrder["charges"]["data"][0]["id"],
                'quote_id'=> $quoteCreated->getId(),
                'payment_method' => $this->getPaymentMethod($conektaOrder["charges"]["data"][0]["payment_method"]["object"]),
                'conekta_customer_id' => $conektaCustomer["customer_id"]
            ];
            $additionalInformation= array_merge($additionalInformation, $this->getAdditionalInformation($conektaOrder));
            $quoteCreated->getPayment()->setAdditionalInformation($additionalInformation);
            $this->saveMissingFieldsQuote($quoteCreated, $conektaOrder);
            $order = $this->quoteManagement->submit($quoteCreated);


            $order->save();

            $order->addCommentToStatusHistory("Missing Order from conekta ". "<a href='". ConfigProvider::URL_PANEL_PAYMENTS ."/".$conektaOrder["id"]. "' target='_blank'>".$conektaOrder["id"]."</a>")
                ->setIsCustomerNotified(true)
                ->save();
            $this->updateConektaReference($conektaOrder["charges"]["data"][0]["id"],  $order->getRealOrderId());
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
        $shippingNameReceiver = $this->utilHelper->splitName($conektaOrder["shipping_contact"]["receiver"]);
        $shipping_address = [
            'firstname'    => $shippingNameReceiver["firstname"],
            'lastname'     => $shippingNameReceiver["lastname"],
            'street' => [ $conektaOrder["shipping_contact"]["address"]["street1"], $conektaOrder["shipping_contact"]["address"]["street2"] ?? ""],
            'city' => $conektaOrder["shipping_contact"]["address"]["city"],
            'country_id' => strtoupper($conektaOrder["fiscal_entity"]["address"]["country"]),
            'region' => $conektaOrder["shipping_contact"]["address"]["state"],
            'postcode' => $conektaOrder["shipping_contact"]["address"]["postal_code"],
            'telephone' =>  $conektaOrder["shipping_contact"]["phone"] || "5200000000",
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
            'telephone' =>  $conektaOrder["fiscal_entity"]["phone"] ||  $conektaOrder["shipping_contact"]["phone"] || "5200000000",
            'region_id' =>$conektaOrder["fiscal_entity"]["metadata"]["region_id"],
            'company'  => $conektaOrder["fiscal_entity"]["metadata"]["company"]
        ];

        //Set Address to quote
        $quoteCreated->getBillingAddress()->addData($billing_address);

        $quoteCreated->getShippingAddress()->addData($shipping_address);
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
