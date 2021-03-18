<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use \Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;

class LineItemsBuilder implements BuilderInterface
{
    private $_product;

    private $_conektaLogger;

    protected $orderRepository;

    protected $_conektaHelper;

    protected $productRepository;

    public function __construct(
        Product $product,
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        OrderRepositoryInterface $orderRepository,
        ProductRepository $productRepository
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request LineItemsBuilder :: __construct');
        $this->_product = $product;
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request LineItemsBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
        $version = (int)str_replace('.', '', $this->_conektaHelper->getMageVersion());
        $request = [];
        $items = $order->getItems();
        $productAttributes = $this->_conektaHelper->getProductAttributes();
        foreach ($items as $itemId => $item) {
            $newItem = [];
            if ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    $newItem = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQtyOrdered()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                    $newItem['metadata'] = $this->getAttributeValues($item,$productAttributes); 
                    $request['line_items'][] = $newItem;
                }
            } else {
                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {
                    $newItem = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQtyOrdered()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                    $newItem['metadata'] = $this->getAttributeValues($item,$productAttributes); 
                    $request['line_items'][] = $newItem;
                }
            }
        }

        $this->_conektaLogger->info('Request LineItemsBuilder :: build : return request', $request);

        return $request;
    }

    private function getAttributeValues($item,$productAttributes) 
    {
        if (count($productAttributes) > 0) {
            $productId = $item->getProductId();
            $product = $this->productRepository->getById($productId);
            $productValues = [];
            foreach ($productAttributes as $attr) {
                $productValues[$attr] = $product->getData($attr);
            }
        }
        return $productValues;
    }
}
