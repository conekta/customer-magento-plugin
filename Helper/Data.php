<?php
namespace Conekta\Payments\Helper;

use Conekta\Customer;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Escaper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends Util
{
    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;
    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;
    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;
    /**
     * @var Customer
     */
    protected $conektaCustomer;

    private $_storeManager;
    private $checkoutSession;

    private $customerSession;

    private $productRepository;

    private $_escaper;
    
    protected $_cartRepository;

    /**
     * Data constructor.
     * @param Context $context
     * @param ModuleListInterface $moduleList
     * @param EncryptorInterface $encryptor
     * @param ProductMetadataInterface $productMetadata
     * @param ConektaLogger $conektaLogger
     * @param Customer $conektaCustomer
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata,
        ConektaLogger $conektaLogger,
        Customer $conektaCustomer,
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
        $this->conektaCustomer = $conektaCustomer;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->productRepository = $productRepository;
        $this->_escaper = $_escaper;
        $this->_cartRepository = $cartRepository;
        $this->_storeManager = $storeManager;
    }

    public function getCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * @param $area
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($area, $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/' . $area . '/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return mixed
     */
    public function getModuleVersion()
    {
        return $this->_moduleList->getOne($this->_getModuleName())['setup_version'];
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        $sandboxMode = $this->getConfigData('conekta/conekta_global', 'sandbox_mode');

        if ($sandboxMode) {
            $privateKey = $this->_encryptor->decrypt($this->getConfigData(
                'conekta/conekta_global',
                'test_private_api_key'
            ));
        } else {
            $privateKey = $this->_encryptor->decrypt($this->getConfigData(
                'conekta/conekta_global',
                'live_private_api_key'
            ));
        }
        return $privateKey;
    }

    /**
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
     * @return mixed
     */
    public function getApiVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/api_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function pluginType()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function pluginVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getMageVersion()
    {
        return $this->_productMetadata->getVersion();
    }

    /**
     * @param $orderParams
     * @param $chargeParams
     */
    public function deleteSavedCard($orderParams, $chargeParams)
    {
        $this->conektaLogger->info('deleteSavedCard: Remove Decline Card From Conekta Customer');

        try {
            $paymentSourceId = '';
            if (isset($chargeParams['payment_method']['payment_source_id'])) {
                $paymentSourceId = $chargeParams['payment_method']['payment_source_id'];
            }

            $customerId = '';
            if (isset($orderParams['customer_info']['customer_id'])) {
                $customerId = $orderParams['customer_info']['customer_id'];
            }

            if ($customerId && $paymentSourceId) {
                $customer = $this->conektaCustomer->find($customerId);
                $customer->deletePaymentSourceById($paymentSourceId);
            }
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage());
        }
    }

    public function getMetadataAttributes($metadataPath)
    {
        $attributes = $this->getConfigData('conekta/conekta_global', $metadataPath);
        $attributesArray = explode(",", $attributes);
        
        return $attributesArray;
    }

    public function is3DSEnabled()
    {
        return (boolean)$this->getConfigData('conekta_cc', 'iframe_enabled');
    }
    
    public function isSaveCardEnabled()
    {
        return (boolean)$this->getConfigData('conekta_cc', 'enable_saved_card');
    }

    public function isCreditCardEnabled()
    {
        return  (boolean)$this->getConfigData('conekta_cc', 'active');
    }

    public function isOxxoEnabled()
    {
        return  (boolean)$this->getConfigData('conekta_oxxo', 'active');
    }

    public function isSpeiEnabled()
    {
        return  (boolean)$this->getConfigData('conekta_spei', 'active');
    }

    /**
     * @return int
     */
    public function getExpiredAt()
    {
        $timeFormat = $this->getConfigData('conekta/conekta_global', 'days_or_hours');
        $expirationValue = null;
        $expirationUnit = null;

        //hours expiration disabled temporaly
        if (!$timeFormat && false) {
            $expirationValue = $this->getConfigData('conekta/conekta_global', 'expiry_hours');
            $expirationUnit = "hours";
        } else {
            $expirationValue = $this->getConfigData('conekta/conekta_global', 'expiry_days');
            $expirationUnit = "days";
        }

        if (empty($expirationValue)) {
            $expirationValue = 3;
        }

        $expiryDate = strtotime("+" . $expirationValue . " " . $expirationUnit);

        return $expiryDate;
    }

    private function customFormat($array, $glue)
    {
        $ret = '';
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                if (count($item) == 0) {
                    $item = 'null';
                    $ret .=  $key . ' : ' . $item . $glue;
                    continue;
                }
                foreach ($item as $k => $i) {
                    $ret .=  $key . '_' . $k . ' : ' . $i  . $glue;
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
                $ret .=  $key . ' : ' . $item . $glue;
            }
        }
        $ret = substr($ret, 0, 0-strlen($glue));
        return $ret;
    }

    public function getMetadataAttributesConekta($items)
    {
        $productAttributes = $this->getMetadataAttributes('metadata_additional_products');
        $request = [];
        if (count($productAttributes) > 0 && !empty($productAttributes[0])) {
            foreach ($items as $item) {
                if ($item->getProductType() != 'configurable') {
                    $productValues = [];
                    $productId = $item->getProductId();
                    $product = $this->productRepository->getById($productId);
                    foreach ($productAttributes as $attr) {
                        $productValues[$attr] = $this->removeSpecialCharacter($product->getData($attr));
                    }
                    $request['Product-' . $productId] = $this->customFormat($productValues, ' | ');
                }
            }
        }
        $orderAttributes = $this->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0 && !empty($orderAttributes[0])) {
            foreach ($orderAttributes as $attr) {
                $quoteValue = $this->checkoutSession->getQuote()->getData($attr);
                if ($quoteValue == null) {
                    $request[$attr] = 'null';
                } elseif (is_array($quoteValue)) {
                    $request[$attr] = $this->customFormat($quoteValue, ' | ');
                } elseif (!is_string($quoteValue)) {
                    if ($attr == 'customer_gender') {
                        $customer = $this->customerSession->getCustomer();
                        $customerDataGender = $customer->getData('gender');
                        $gender = $customer->getAttribute('gender')->getSource()->getOptionText($customerDataGender);
                        $request[$attr] = strtolower($gender);
                    } elseif ($attr == 'is_changed') {
                        if ($quoteValue  == 0) {
                            $request[$attr] = 'no';
                        } elseif ($quoteValue  == 1) {
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
                        if ($quoteValue  == '0') {
                            $request[$attr] = 'no';
                        } elseif ($quoteValue  == '1') {
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

    public function getMagentoMetadata()
    {
        return [
            'plugin' => 'Magento',
            'plugin_version' => $this->getMageVersion(),
            'plugin_conekta_version' => $this->pluginVersion()
        ];
    }

    public function getLineItems($items, $isQuoteItem = true)
    {
        $version = (int)str_replace('.', '', $this->getMageVersion());
        $request = [];
        $quantityMethod = $isQuoteItem? "getQty":"getQtyOrdered";
        foreach ($items as $itemId => $item) {
            if ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    
                    $price = $item->getPrice();
                    $qty= (int)$item->{$quantityMethod}();
                    if (!empty($item->getParentItem())) {
                        $parent = $item->getParentItem();
                        
                        if ($parent->getProductType() == 'configurable') {
                            $price = $item->getParentItem()->getPrice();
                            $qty = (int)$item->getParentItem()->{$quantityMethod}();
                        
                        } elseif ($parent->getProductType() == 'bundle' && $isQuoteItem) {
                            //If it is a quote item, then qty of item has not been calculate yet
                            $qty = $qty * (int)$item->getParentItem()->{$quantityMethod}();
                        }
                    }
                    
                    $name = $this->removeSpecialCharacter($item->getName());
                    $description = $this->removeSpecialCharacter(
                        $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku())
                    );
                    $description = substr($description, 0, 250);
                    
                    $request[] = [
                        'name' => $name,
                        'sku' => $this->removeSpecialCharacter($item->getSku()),
                        'unit_price' => $this->convertToApiPrice($price),
                        'description' => $description,
                        'quantity' => $qty,
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];

                }
            } else {
                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {
                    $request[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => $this->convertToApiPrice($item->getPrice()),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->{$quantityMethod}()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                }
            }
        }
        return $request;
    }

    public function getUrlWebhookOrDefault()
    {
        $urlWebhook = $this->getConfigData('conekta/conekta_global', 'conekta_webhook');
        if (empty($urlWebhook)) {
            $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
            $urlWebhook = $baseUrl . "conekta/webhook/listener";
        }
        return $urlWebhook;
    }

    public function getShippingLines($quoteId, $isCheckout = true)
    {
        $quote = $this->_cartRepository->get($quoteId);
        $shippingAddress = $quote->getShippingAddress();
        
        $shippingLines = [];
        
        if ($quote->getIsVirtual()) {
            $shippingLines[] = ['amount' => 0 ];
        } elseif ($shippingAddress) {
            $shippingLine['amount'] = $this->convertToApiPrice($shippingAddress->getShippingAmount());

            //Chekout orders doesn't allow method and carrier parameters
            if (!$isCheckout) {
                $shippingLine['method'] = $shippingAddress->getShippingMethod();
                $shippingLine['carrier'] = $shippingAddress->getShippingDescription();
            }

            $shippingLines[] = $shippingLine;
        }

        return $shippingLines;
    }

    public function getShippingContact($quoteId)
    {
        $quote = $this->_cartRepository->get($quoteId);
        $address = null;
        
        $shippingContact = [];
        
        if ($quote->getIsVirtual()) {
            $address = $quote->getBillingAddress();
        } else {
            $address = $quote->getShippingAddress();
        }
        
        if ($address) {
            $phone = $this->removePhoneSpecialCharacter($address->getTelephone());

            $shippingContact = [
                'receiver' => $this->getCustomerName($address),
                'phone' => $phone,
                'address' => [
                    'city' => $address->getCity(),
                    'state' => $address->getRegionCode(),
                    'country' => $address->getCountryId(),
                    'postal_code' => $this->onlyNumbers($address->getPostcode()),
                    'phone' => $phone,
                    'email' => $address->getEmail()
                ]
            ];

            $street = $address->getStreet();
            $streetStr = isset($street[0]) ? $street[0] : 'NO STREET';
            $shippingContact['address']['street1'] = $this->removeSpecialCharacter($streetStr);
            if (isset($street[1])) {
                $shippingContact['address']['street2'] = $this->removeSpecialCharacter($street[1]);
            }
        }
        return $shippingContact;
    }

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

    public function getDiscountLines()
    {
        $quote = $this->checkoutSession->getQuote();
        $totalDiscount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
        $totalDiscount = abs(round($totalDiscount, 2));

        $discountLines = [];
        if (!empty($totalDiscount)) {
            $totalDiscount = $this->convertToApiPrice($totalDiscount);
            $discountLine["code"] = "Discounts";
            $discountLine["type"] = "coupon";
            $discountLine["amount"] = $totalDiscount;
            $discountLines[] = $discountLine;
        }

        return $discountLines;
    }

    public function getTaxLines($items)
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
            'amount' => $ctr_amount
        ];

        return $taxLines;
    }
}
