<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class LineItemsBuilder implements BuilderInterface
{
    private $_product;

    private $_conektaLogger;

    protected $_conektaHelper;

    public function __construct(
        Product $product,
        Escaper $_escaper,
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request LineItemsBuilder :: __construct');
        $this->_product = $product;
        $this->_conektaHelper = $conektaHelper;
        $this->_escaper = $_escaper;
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
        foreach ($items as $itemId => $item) {
            if ($version > 233) {
                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {
                    $request['line_items'][] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQtyOrdered()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                }
            } else {
                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {
                    $request['line_items'][] = [
                        'name' => $item->getName(),
                        'sku' => $item->getSku(),
                        'unit_price' => (int)($item->getPrice() * 100),
                        'description' => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity' => (int)($item->getQtyOrdered()),
                        'tags' => [
                            $item->getProductType()
                        ]
                    ];
                }
            }
        }

        $this->_conektaLogger->info('Request LineItemsBuilder :: build : return request', $request);

        return $request;
    }
}
