<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class MetadataOrder implements ArrayInterface
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */

    protected $orderResource;

    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order $orderResource
    ) {
            $this->orderResource = $orderResource;
    }
    
    public function toOptionArray()
    {
        $result = [];
        foreach ($this->getOptions() as $value => $label) {
            $result[] = [
                 'value' => $value,
                 'label' => $label,
             ];
        }
        return $result;
    }

    public function getOptions()
    {
        $orderAttributes = array_keys($this->orderResource->getConnection()->describeTable('quote'));
        $optionsMetadata = [];

        foreach ($orderAttributes as $attr) {
            if ($attr == 'entity_id') {
                continue;
            }
            $label = ucwords(str_replace('_', ' ', $attr));
            $optionsMetadata[$attr] = $label;
        }

        return $optionsMetadata;
    }
}
