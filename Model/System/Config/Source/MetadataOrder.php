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
    // protected $orderRepository;
    protected $orderInterface;

    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface
        // \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
            $this->searchCriteriaBuilder = $searchCriteriaBuilder;
            $this->orderInterface = $orderInterface;
            // $this->orderRepository = $orderRepository;
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
        // $searchCriteria = $this->searchCriteriaBuilder->create();
        // $order = $this->orderInterface->getConstants();
        // // $order = $this->orderRepository->getData();
        // var_dump($order);die();

        $optionsMetadata = [];
        
        // // foreach ($attributeRepository->getItems() as $items) {
        // //     $optionsMetadata[$items->getAttributeCode()] = $items->getFrontendLabel();
        // // }

        return $optionsMetadata;
    }
}
