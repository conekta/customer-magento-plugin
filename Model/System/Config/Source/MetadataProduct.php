<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class MetadataProduct implements ArrayInterface
{
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $attributeRepository;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Api\AttributeRepositoryInterface $attributeRepository
    ) {
            $this->searchCriteriaBuilder = $searchCriteriaBuilder;
            $this->attributeRepository = $attributeRepository;
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
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $attributeRepository = $this->attributeRepository->getList(
            \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
            $searchCriteria
        );

        $optionsMetadata = [];
        
        foreach ($attributeRepository->getItems() as $item) {
            if ($item->getAttributeCode() == 'media_gallery') {
                continue;
            }
            $optionsMetadata[$item->getAttributeCode()] = $item->getFrontendLabel();
        }
        
        return $optionsMetadata;
    }
}
