<?php

namespace Conekta\Payments\Helper;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Escaper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends Util
{
    /**
     * @var ModuleListInterface
     */
    protected ModuleListInterface $_moduleList;
    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $_encryptor;
    /**
     * @var ProductMetadataInterface
     */
    protected ProductMetadataInterface $_productMetadata;
    /**
     * @var ConektaLogger
     */
    protected ConektaLogger $conektaLogger;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $_storeManager;
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;
    /**
     * @var Escaper
     */
    private Escaper $_escaper;
    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $_cartRepository;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ModuleListInterface $moduleList
     * @param EncryptorInterface $encryptor
     * @param ProductMetadataInterface $productMetadata
     * @param ConektaLogger $conektaLogger
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param ProductRepository $productRepository
     * @param Escaper $_escaper
     * @param CartRepositoryInterface $cartRepository
     * @param StoreManagerInterface $storeManager
     */

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata,
        ConektaLogger $conektaLogger,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        ProductRepository $productRepository,
        Escaper $_escaper,
        CartRepositoryInterface $cartRepository,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->_moduleList = $moduleList;
        $this->_encryptor = $encryptor;
        $this->_productMetadata = $productMetadata;
        $this->conektaLogger = $conektaLogger;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->_escaper = $_escaper;
        $this->_cartRepository = $cartRepository;
        $this->_storeManager = $storeManager;
    }

    /**
     * Get currency code
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCurrencyCode(): string
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get Config Data
     *
     * @param string $area
     * @param string $field
     * @param mixed $storeId
     * @return mixed
     */
    public function getConfigData($area, $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/' . $area . '/' . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Module Version
     *
     * @return mixed
     */
    public function getModuleVersion()
    {
        return $this->_moduleList->getOne($this->_getModuleName())['setup_version'];
    }

    /**
     * Get private key
     *
     * @return string
     */
    public function getPrivateKey(): string
    {
        $sandboxMode = $this->getConfigData('conekta/conekta_global', 'sandbox_mode');

        if ($sandboxMode) {
            $privateKey = $this->_encryptor->decrypt(
                $this->getConfigData(
                    'conekta/conekta_global',
                    'test_private_api_key'
                )
            );
        } else {
            $privateKey = $this->_encryptor->decrypt(
                $this->getConfigData(
                    'conekta/conekta_global',
                    'live_private_api_key'
                )
            );
        }
        return $privateKey;
    }

    /**
     * Get Public key
     *
     * @return mixed
     */
    public function getPublicKey()
    {
        $sandboxMode = $this->getConfigData('conekta/conekta_global', 'sandbox_mode');
        if ($sandboxMode) {
            $publicKey = $this->getConfigData('conekta/conekta_global', 'test_public_api_key');
        } else {
            $publicKey = $this->getConfigData('conekta/conekta_global', 'live_public_api_key');
        }
        return $publicKey;
    }

    /**
     * Get Api version
     *
     * @return mixed
     */
    public function getApiVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/api_version',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Public type
     *
     * @return mixed
     */
    public function pluginType()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_type',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Plugin version
     *
     * @return mixed
     */
    public function pluginVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_version',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Mage version
     *
     * @return string
     */
    public function getMageVersion(): string
    {
        return $this->_productMetadata->getVersion();
    }

    /**
     * Get metadata attributes
     *
     * @param mixed $metadataPath
     * @return false|string[]
     */
    public function getMetadataAttributes($metadataPath)
    {
        $attributes = $this->getConfigData('conekta/conekta_global', $metadataPath);
        return explode(",", $attributes  ?? '');
    }

    /**
     * Is 3D enable
     *
     * @return bool
     */
    public function is3DSEnabled(): bool
    {
        return (boolean)$this->getConfigData('conekta_cc', 'iframe_enabled');
    }

    /**
     * Is save card enabled
     *
     * @return bool
     */
    public function isSaveCardEnabled(): bool
    {
        return (boolean)$this->getConfigData('conekta_cc', 'enable_saved_card');
    }

    /**
     * Is credit card enabled
     *
     * @return bool
     */
    public function isCreditCardEnabled(): bool
    {
        return (boolean)$this->getConfigData('conekta_cc', 'active');
    }

    /**
     * Is cash enabled
     *
     * @return bool
     */
    public function isCashEnabled(): bool
    {
        return (boolean)$this->getConfigData('conekta_cash', 'active');
    }

    /**
     * Is Bank Transfer enabled
     *
     * @return bool
     */
    public function isBankTransferEnabled(): bool
    {
        return (boolean)$this->getConfigData('conekta_bank_transfer', 'active');
    }

    /**
     * Get expired At
     *
     * @return int
     */
    public function getExpiredAt(): int
    {
        $timeFormat = $this->getConfigData('conekta/conekta_global', 'days_or_hours');

        //hours expiration disabled temporally
        if (! $timeFormat && false) {
            $expirationValue = $this->getConfigData('conekta/conekta_global', 'expiry_hours');
            $expirationUnit = "hours";
        } else {
            $expirationValue = $this->getConfigData('conekta/conekta_global', 'expiry_days');
            $expirationUnit = "days";
        }

        if (empty($expirationValue)) {
            $expirationValue = 1;
        }

        return strtotime("+" . $expirationValue . " " . $expirationUnit);
    }

    /**
     * Custom format
     *
     * @param mixed $array
     * @param mixed $glue
     * @return false|string
     */
    private function customFormat($array, $glue)
    {
        $ret = '';
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                if (count($item) == 0) {
                    $item = 'null';
                    $ret .= $key . ' : ' . $item . $glue;
                    continue;
                }
                foreach ($item as $k => $i) {
                    $ret .= $key . '_' . $k . ' : ' . $i . $glue;
                }
            } else {
                if ($item == '') {
                    $item = 'null';
                } elseif ($key == 'has_options' || $key == 'new') {
                    if ($item == '0') {
                        $item = 'no';
                    } elseif ($item == '1') {
                        $item = 'yes';
                    }
                }
                $ret .= $key . ' : ' . $item . $glue;
            }
        }
        return substr($ret, 0, 0 - strlen($glue));
    }

    /**
     * @param $productAttributes
     * @param $product
     * @return array
     */
    private function processProductAttributes($productAttributes, $product): array
    {
        $productValues = [];

        foreach ($productAttributes as $attribute) {
            $attributeValue = $product->getData($attribute);

            if(!array($attributeValue)){
                $productValues[$attribute] = $this->removeSpecialCharacter($attributeValue);
            }

            if (is_array($attributeValue)){
                foreach ($attributeValue as $subAttrName => $subAttrValue) {
                    $subAttrKey = sprintf("%s_%s", $attribute, $subAttrName);
                    $productValues[$subAttrKey] = $this->removeSpecialCharacter($subAttrValue);
                }
            }
        }

        return $productValues;
    }

    /**
     * Get Metadata Attributes conekta
     *
     * @param mixed $items
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getMetadataAttributesConekta($items): array
    {
        $productAttributes = $this->getMetadataAttributes('metadata_additional_products');

        $request = [];
        if (count($productAttributes) > 0 && ! empty($productAttributes[0])) {
            foreach ($items as $item) {
                if ($item->getProductType() != 'configurable') {
                    $productId = $item->getProductId();
                    $product = $this->productRepository->getById($productId);
                    $productValues = $this->processProductAttributes($productAttributes, $product);

                    $request['Product-' . $productId] = $this->customFormat($productValues, ' | ');
                }
            }
        }
        $orderAttributes = $this->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0 && ! empty($orderAttributes[0])) {
            foreach ($orderAttributes as $attr) {
                $quoteValue = $this->checkoutSession->getQuote()->getData($attr);
                if ($quoteValue == null) {
                    $request[$attr] = 'null';
                } elseif (is_array($quoteValue)) {
                    $request[$attr] = $this->customFormat($quoteValue, ' | ');
                } elseif (! is_string($quoteValue)) {
                    if ($attr == 'customer_gender') {
                        $customer = $this->customerSession->getCustomer();
                        $customerDataGender = $customer->getData('gender');
                        $gender = $customer->getAttribute('gender')->getSource()->getOptionText($customerDataGender);
                        $request[$attr] = strtolower($gender);
                    } elseif ($attr == 'is_changed') {
                        if ($quoteValue == 0) {
                            $request[$attr] = 'no';
                        } elseif ($quoteValue == 1) {
                            $request[$attr] = 'yes';
                        }
                    } else {
                        $request[$attr] = (string)$quoteValue;
                    }
                } else {
                    if ($attr == 'is_active' ||
                        $attr == 'is_virtual' ||
                        $attr == 'is_multi_shipping' ||
                        $attr == 'customer_is_guest' ||
                        $attr == 'is_persistent'
                    ) {
                        if ($quoteValue == '0') {
                            $request[$attr] = 'no';
                        } elseif ($quoteValue == '1') {
                            $request[$attr] = 'yes';
                        }
                    } else {
                        $request[$attr] = $quoteValue;
                    }
                }
            }
        }
        return $request;
    }

    /**
     * Get magento metadata
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getMagentoMetadata(): array
    {
        return [
            'plugin'                 => 'Magento',
            'magento_version'        => $this->getMageVersion(),
            'plugin_conekta_version' => $this->pluginVersion(),
            'store'                  => $this->getStore()->getId(),
            'remote_ip'              => $this->_remoteAddress->getRemoteAddress()
        ];
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getExtraMetadata($quoteId) :array{

        $response = [];
        $quote = $this->_cartRepository->get($quoteId);
        $response['save_in_address_book'] = $quote->getBillingAddress()->getSaveInAddressBook();
        $response['shipping_region_id'] = $quote->getShippingAddress()->getRegionId();
        $response['billing_region_id'] = $quote->getBillingAddress()->getRegionId();
        return $response;
    }

    /**
     * Get line items
     *
     * @param mixed $items
     * @param mixed $isQuoteItem
     * @return array
     */
    public function getLineItems(array $items, $isQuoteItem = true): array
    {
        $version = (int)str_replace('.', '', $this->getMageVersion());
        $request = [];
        $quantityMethod = $isQuoteItem ? "getQty" : "getQtyOrdered";
        foreach ($items as $item) {
            if ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    $price = $item->getPrice();
                    $qty = (int)$item->{$quantityMethod}();
                    if (! empty($item->getParentItem())) {
                        $parent = $item->getParentItem();

                        if ($parent->getProductType() == 'configurable') {
                            $price = $item->getParentItem()->getPrice();
                            $qty = (int)$item->getParentItem()->{$quantityMethod}();
                        } elseif ($parent->getProductType() == 'bundle' && $isQuoteItem) {
                            //If it is a quote item, then qty of item has not been calculated yet
                            $qty = $qty * (int)$item->getParentItem()->{$quantityMethod}();
                        }
                    }

                    $name = $this->removeSpecialCharacter($item->getName());
                    $description = $this->removeSpecialCharacter(
                        $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku())
                    );
                    $description = substr($description, 0, 250);

                    $request[] = [
                        'name'        => $name,
                        'sku'         => $this->removeSpecialCharacter($item->getSku()),
                        'unit_price'  => $this->convertToApiPrice($price),
                        'description' => $description,
                        'quantity'    => $qty,
                        'tags'        => [
                            $item->getProductType()
                        ],
                        'metadata' => [
                            "product_id" => $item->getProductId()
                        ]
                    ];
                }
            } else {
                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {
                    $request[] = [
                        'name'        => $item->getName(),
                        'sku'         => $item->getSku(),
                        'unit_price'  => $this->convertToApiPrice($item->getPrice()),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity'    => (int)($item->{$quantityMethod}()),
                        'tags'        => [
                            $item->getProductType()
                        ],
                        'metadata' => [
                            "product_id" => $item->getProductId()
                        ]
                    ];
                }
            }
        }
        return $request;
    }

    /**
     * Get Url webhook or default
     *
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function getUrlWebhookOrDefault()
    {
        $urlWebhook = $this->getConfigData('conekta/conekta_global', 'conekta_webhook');
        if (empty($urlWebhook)) {
            $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
            $urlWebhook = $baseUrl . "conekta/webhook/index";
        }
        return $urlWebhook;
    }

    /**
     * Get shipping lines
     *
     * @param mixed $quoteId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getShippingLines($quoteId): array
    {
        $quote = $this->_cartRepository->get($quoteId);
        $shippingAddress = $quote->getShippingAddress();

        $shippingLines = [];

        if ($quote->getIsVirtual()) {
            $shippingLines[] = [
                'amount' => 0,
                'method' => 'virtual'
            ];
        } elseif ($shippingAddress) {
            $shippingLine['amount'] = $this->convertToApiPrice($shippingAddress->getShippingAmount());
            $shippingLine['method'] = $shippingAddress->getShippingMethod();
            $shippingLine['carrier'] = $shippingAddress->getShippingDescription();

            $shippingLines[] = $shippingLine;
        }

        return $shippingLines;
    }

    /**
     * Get shipping contact
     *
     * @param mixed $quoteId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getShippingContact(int $quoteId): array
    {
        $quote = $this->_cartRepository->get($quoteId);
        $address = $quote->getShippingAddress();

        if ($quote->getIsVirtual()) {
            $address = $quote->getBillingAddress();
        }
        $phone = $this->removePhoneSpecialCharacter($address->getTelephone());

        $shippingContact = [
            'receiver' => $this->getCustomerName($address),
            'phone'    => $phone,
            'address'  => [
                'city'        => $address->getCity(),
                'state'       => $address->getRegion(),
                'country'     => $address->getCountryId(),
                'postal_code' => $this->onlyNumbers($address->getPostcode()),
                'phone'       => $phone,
                'email'       => $address->getEmail()
            ],
            'metadata' => [
                'company'=> $address->getCompany()
            ]
        ];

        $street = $address->getStreet();
        $streetStr = $street[0] ?? 'NO STREET';
        $shippingContact['address']['street1'] = $this->removeSpecialCharacter($streetStr);
        if (isset($street[1])) {
            $shippingContact['address']['street2'] = $this->removeSpecialCharacter($street[1]);
        }
        return $shippingContact;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getBillingAddress(int $quoteId): array
    {
        $quote = $this->_cartRepository->get($quoteId);
        $address = $quote->getBillingAddress();

        $phone = $this->removePhoneSpecialCharacter($address->getTelephone());

        $billingContact = [
            'name' => $this->getCustomerName($address),
            'phone'    => $phone,
            'address'  => [
                'city'            => $address->getCity(),
                'state'           => $address->getRegion(),
                'country'         => $address->getCountryId(),
                'postal_code'     => $this->onlyNumbers($address->getPostcode()),
                'external_number' => $address->getId() !== null ? strval($address->getId()) : "",
            ],
            'metadata' => [
                'company'=> $address->getCompany()
            ]
        ];

        $street = $address->getStreet();
        $streetStr = $street[0] ?? 'NO STREET';
        $billingContact['address']['street1'] = $this->removeSpecialCharacter($streetStr);
        if (isset($street[1])) {
            $billingContact['address']['street2'] = $this->removeSpecialCharacter($street[1]);
        }
        return $billingContact;
    }

    /**
     * Get customer name
     *
     * @param mixed $shipping
     * @return array|string|string[]|null
     */
    public function getCustomerName($shipping)
    {
        $customerName = sprintf(
            '%s %s %s',
            $shipping->getFirstname(),
            $shipping->getMiddlename(),
            $shipping->getLastname()
        );

        return $this->removeNameSpecialCharacter($customerName);
    }

    /**
     * Get discount lines
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getDiscountLines(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $totalDiscount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
        $totalDiscount = abs(round($totalDiscount, 2));

        $discountLines = [];
        if (! empty($totalDiscount)) {
            $totalDiscount = $this->convertToApiPrice($totalDiscount);
            $discountLine["code"] = $quote->getCouponCode() ?? "Discounts";
            $discountLine["type"] = "coupon";
            $discountLine["amount"] = $totalDiscount;
            $discountLines[] = $discountLine;
        }

        return $discountLines;
    }

    /**
     * Get Tax lines
     *
     * @param mixed $items
     * @return array
     */
    public function getTaxLines($items): array
    {
        $taxLines = [];
        $ctr_amount = 0;
        foreach ($items as $item) {
            if ($item->getProductType() != 'bundle' && $item->getTaxAmount() > 0) {
                $ctr_amount += $this->convertToApiPrice($item->getTaxAmount());
            }
        }

        $taxLines[] = [
            'description' => 'Tax',
            'amount'      => $ctr_amount
        ];

        return $taxLines;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStore(): StoreInterface
    {
        return  $this->_storeManager->getStore();
    }
}
