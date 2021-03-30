<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Checkout\Model\Session;

class MetadataBuilder implements BuilderInterface
{
    private $_conektaLogger;

    protected $_conektaHelper;

    protected $productRepository;

    private $subjectReader;

    private $session;

    public function __construct(
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        ProductRepository $productRepository,
        SubjectReader $subjectReader,
        Session $session
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request MetadataBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
        $this->productRepository = $productRepository;
        $this->subjectReader = $subjectReader;
        $this->session = $session;
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
                    $request['metadata']['Product-' . $productId] = $this->customImplode($productValues, ' | ');
                }
            }
        }
        $orderAttributes = $this->_conektaHelper->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0) {
            foreach ($orderAttributes as $attr) {
                $quoteValue = $this->session->getQuote()->getData($attr);
                if ($quoteValue == null) {
                    $request['metadata'][$attr] = 'null';
                    continue;
                }
                if (is_array($quoteValue)) {
                    $request['metadata'][$attr] = $this->customImplode($quoteValue, ' | ');
                    continue;
                }
                $request['metadata'][$attr] = strval($quoteValue);
            } 
        }

        $this->_conektaLogger->info('Request MetadataBuilder :: build : return request', $request);

        return $request;
    }

    private function customImplode($array, $glue) {
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
                }
                $ret .=  $key . ' : ' . $item . $glue;
            }
        }
        $ret = substr($ret, 0, 0-strlen($glue));
        return $ret;
    }
}