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
        $this->_conektaLogger   = $conektaLogger;
        $this->_conektaLogger->info('Request LineItemsBuilder LineItemsBuilder :: __construct');
        $this->_product         = $product;
        $this->_conektaHelper   = $conektaHelper;
        $this->_escaper         = $_escaper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request LineItemsBuilder :: build');

        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $payment    = $buildSubject['payment'];
        $order      = $payment->getOrder();
        $version    = (int)str_replace('.', '', $this->_conektaHelper->getMageVersion());
        $request    = [];
        $items      = $order->getItems();

        foreach ($items as $itemId => $item) {

            $this->_conektaLogger->info('ITEM DATA');
            $this->_conektaLogger->info($item->getName());
            $this->_conektaLogger->info($item->getSku());
            $this->_conektaLogger->info($item->getPrice());


            if ($version == '233') {

                $this->_conektaLogger->info('Version  == 233');
                $this->_conektaLogger->info($version);

                if ($item->getProductType() != 'bundle' && $item->getProductType() != 'configurable') {

                    $this->_conektaLogger->info('PRODUCT TYPE not bundle and not configurable ');
                    $this->_conektaLogger->info('ITEM');
                    $this->_conektaLogger->info($item->getName());
                    $this->_conektaLogger->info($item->getSku());
                    $this->_conektaLogger->info($item->getPrice());

                    $request['line_items'][] = [
                        'name'          => $item->getName(),
                        'sku'           => $item->getSku(),
                        'unit_price'    => (int)($item->getPrice() * 100),
                        'description'   => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity'      => (int)($item->getQtyOrdered()),
                        'tags'          => [
                                            $item->getProductType()
                                        ]
                    ];
                }else{
                    $this->_conektaLogger->info('getProductType');
                    $this->_conektaLogger->info($item->getProductType());
                }

            } else {

                $this->_conektaLogger->info('Version: '.$version);

                if ($item->getProductType() != 'bundle' && $item->getPrice() > 0) {

                    $this->_conektaLogger->info('Not Bundle and price > 0: ');

                    $request['line_items'][] = [
                        'name'          => $item->getName(),
                        'sku'           => $item->getSku(),
                        'unit_price'    => (int)($item->getPrice() * 100),
                        'description'   => $this->_escaper->escapeHtml($item->getName() . ' - ' . $item->getSku()),
                        'quantity'      => (int)($item->getQtyOrdered()),
                        'tags'          => [
                                            $item->getProductType()
                                        ]
                    ];
                }else{
                    $this->_conektaLogger->info('getProductType');
                    $this->_conektaLogger->info($item->getProductType());
                }
            }
        }

        $this->_conektaLogger->info('Request LineItemsBuilder :: build : return request', $request);

        return $request;
    }
}
