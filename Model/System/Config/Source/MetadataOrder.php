<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class MetadataOrder implements ArrayInterface
{
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */

    protected $attributeRepository;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order
     */

    protected $orderResource;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Api\AttributeRepositoryInterface $attributeRepository,
        \Magento\Sales\Model\ResourceModel\Order $orderResource
    ) {
            $this->searchCriteriaBuilder = $searchCriteriaBuilder;
            $this->attributeRepository = $attributeRepository;
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
        $orderAttributes = array_keys($this->orderResource->getConnection()->describeTable('sales_order'));
        $optionsMetadata = [];

        foreach ($orderAttributes as $attr) {
            $label = ucwords(str_replace('_',' ',$attr));
            $optionsMetadata[$attr] = $label;
        }

        return $optionsMetadata;
    }
}
