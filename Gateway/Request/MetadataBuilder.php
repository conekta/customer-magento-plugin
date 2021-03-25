<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Payment\Gateway\Helper\SubjectReader;

class MetadataBuilder implements BuilderInterface
{
    private $_conektaLogger;

    protected $orderFactory;

    protected $_conektaHelper;

    protected $productRepository;

    private $subjectReader;

    private $repo;

    public function __construct(
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        ProductRepository $productRepository,
        SubjectReader $subjectReader,
        OrderRepositoryInterface $repo
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request MetadataBuilder :: __construct');
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
        $this->orderFactory = $orderFactory;
        $this->productRepository = $productRepository;
        $this->subjectReader = $subjectReader;
        $this->repo = $repo;
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
        $orderID = $payment->getOrder()->getId();
        $this->_conektaLogger->info('ID',[$order]);
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
        $this->_conektaLogger->info('hola');
        $orderIncrementId = $order->getOrderIncrementId();
        $this->_conektaLogger->info('hola1');
        // $this->_conektaLogger->info('ORDERMODELdata1',[$order->getOrderModel()->load()->getData('')]);
        // $this->_conektaLogger->info('ORDERMODELdata2',[$order->getOrderModel()->getData()]);
        // $this->_conektaLogger->info('hola3');
        // // $newOrder = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        // // $this->_conektaLogger->info('newOrder',[$newOrder]);

        // $this->_conektaLogger->info('getPAYMENTdata',[$payment->getPayment()->getData()]);
        // $this->_conektaLogger->info('getPAYMENThas',[$payment->getPayment()->getAdditionalInformation()]);
        $orderAttributes = $this->_conektaHelper->getMetadataAttributes('metadata_additional_order');
        if (count($orderAttributes) > 0) {
            foreach ($orderAttributes as $attr) {
                // if ($order->getData($attr) == null) {
                //     $request['metadata'][$attr] = '';
                //     continue;
                // }
                // if (is_array($order->getData($attr))) {
                //     $request['metadata'][$attr] = $order->getData($attr);
                //     continue;
                // }
                // $req = $request['metadata'];
                // $this->_conektaLogger->info('Request MetadataBuilder :: build : return req', $req);
                // $this->_conektaLogger->info('punto de control');
                // $var1 = [$attr];
                // $this->_conektaLogger->info('OK atributo', $var1);
                // $var2 = [$order];
                // $this->_conektaLogger->info('ORDER: ', $var2);
                // $var3 = [$order->getData('status')];
                // $this->_conektaLogger->info('get STATUS', $var3);
                $request['metadata'][$attr] = $order->getData($attr);
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