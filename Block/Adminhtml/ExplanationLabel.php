<?php

namespace Conekta\Payments\Block\Adminhtml;

class ExplanationLabel extends \Magento\Framework\Data\Form\Element\AbstractElement
{

    public function __construct( // phpcs:ignore
        \Magento\Framework\Data\Form\Element\Factory $factoryElement,
        \Magento\Framework\Data\Form\Element\CollectionFactory $factoryCollection,
        \Magento\Framework\Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
    }

    /**
     * @return string
     */
    public function getElementHtml()
    {
        return 'Select a maximum of 12 attributes in total from the following attribute lists';
    }
}
