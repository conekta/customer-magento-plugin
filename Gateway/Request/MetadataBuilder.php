<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class MetadataBuilder implements BuilderInterface
{
    private $_conektaLogger;

    protected $_conektaHelper;

    protected $productRepository;

    private $subjectReader;

    private $checkoutSession;

    private $customerSession;

    public function __construct(
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ProductRepository $productRepository,
        SubjectReader $subjectReader,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request MetadataBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
        $this->productRepository = $productRepository;
        $this->subjectReader = $subjectReader;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request MetadataBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $this->subjectReader->readPayment($buildSubject);
        $order = $payment->getOrder();
        $items = $order->getItems();
        $productAttributes = $this->_conektaHelper->getMetadataAttributes('metadata_additional_products');
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
                    $request['metadata']['Product-' . $productId] = $this->customFormat($productValues, ' | ');
                }
            }
        }
        $orderAttributes = $this->_conektaHelper->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0) {
            foreach ($orderAttributes as $attr) {
                $quoteValue = $this->checkoutSession->getQuote()->getData($attr);
                if ($quoteValue == null) {
                    $request['metadata'][$attr] = 'null';
                } elseif (is_array($quoteValue)) {
                    $request['metadata'][$attr] = $this->customFormat($quoteValue, ' | ');
                } elseif (!is_string($quoteValue)) {
                    if ($attr == 'customer_gender') {
                        $customer = $this->customerSession->getCustomer();
                        $customerDataGender = $customer->getData('gender');
                        $gender = $customer->getAttribute('gender')->getSource()->getOptionText($customerDataGender);
                        $request['metadata'][$attr] = strtolower($gender);
                    } elseif ($attr == 'is_changed') {
                        if ($quoteValue  == 0) {
                            $request['metadata'][$attr] = 'no';
                        } elseif ($quoteValue  == 1) {
                            $request['metadata'][$attr] = 'yes';
                        }
                    } else {
                        $request['metadata'][$attr] = (string)$quoteValue;
                    }
                } else {
                    if ($attr == 'is_active' ||
                        $attr == 'is_virtual' ||
                        $attr == 'is_multi_shipping' ||
                        $attr == 'customer_is_guest' ||
                        $attr == 'is_persistent'
                    ) {
                        if ($quoteValue  == '0') {
                            $request['metadata'][$attr] = 'no';
                        } elseif ($quoteValue  == '1') {
                            $request['metadata'][$attr] = 'yes';
                        }
                    } else {
                        $request['metadata'][$attr] = $quoteValue;
                    }
                }
                
            }
        }
        $this->_conektaLogger->info('Request MetadataBuilder :: build : return request', $request);

        return $request;
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
}
