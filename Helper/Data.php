<?php
namespace Conekta\Payments\Helper;

use Conekta\Customer;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Escaper;
use Magento\Quote\Api\CartRepositoryInterface;

class Data extends AbstractHelper
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
        CartRepositoryInterface $cartRepository
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

    /**
     * @return string
     */
    public function getExpiredAt()
    {
        $datetime = new \Datetime();
        $datetime->add(new \DateInterval('P3D'));
        return $datetime->format('U');
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

    public function getMetadataAttributesConketa($items)
    {
        $productAttributes = $this->getMetadataAttributes('metadata_additional_products');
        $request = [];
        if (count($productAttributes) > 0) {
            foreach ($items as $item) {
                if ($item->getProductType() != 'configurable') {
                    $productValues = [];
                    $productId = $item->getProductId();
                    $product = $this->productRepository->getById($productId);
                    foreach ($productAttributes as $attr) {
                        $productValues[$attr] = $product->getData($attr);
                    }
                    $request['Product-' . $productId] = $this->customFormat($productValues, ' | ');
                }
            }
        }
        $orderAttributes = $this->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0) {
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
            'plugin_version' => $this->getMageVersion()
        ];
    }

    public function getLineItems($items, $isQuoteItem = true)
    {
        $version = (int)str_replace('.', '', $this->getMageVersion());
        $request = [];
        $quantityMethod = $isQuoteItem? "getQty":"getQtyOrdered";
        foreach ($items as $itemId => $item) {
            if ($version > 240) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    
                    $price = (int) $item->getPrice();
                    $qty= (int)$item->{$quantityMethod}();
                    if ($price === 0 && !empty($item->getParentItem())) {
                        $price = (int) $item->getParentItem()->getPrice();
                        $qty = (int)$item->getParentItem()->{$quantityMethod}();
                    }

                    $request[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => $price * 100,
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => $qty,
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];

                }
            } elseif ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    $request[] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->{$quantityMethod}()),
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
                        'unit_price' => (int)($item->getPrice() * 100),
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

    public function getShippingLines($quoteId){

        $quote = $this->_cartRepository->get($quoteId);
        $shippingAddress = $quote->getShippingAddress();
        $this->_conektaLogger->info('Request ShippingLinesBuilder :: build', ['isVirtual'=>$quote->getIsVirtual()]);
        $shippingLines = [];
        
        if ($quote->getIsVirtual()) {
            $shippingLines['amount'] = 0;
        } elseif($shippingAddress) {
            $shipping_lines['amount'] = (int)($shippingAddress->getShippingAmount() * 100);
            $shipping_lines['method'] = $quote->getShippingAddress()->getShippingMethod();
            $shipping_lines['carrier'] = $quote->getShippingAddress()->getShippingDescription();
        }

        return $shippingLines;
    }


}
