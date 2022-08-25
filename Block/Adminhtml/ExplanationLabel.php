<?php

namespace Conekta\Payments\Block\Adminhtml;

use Magento\Framework\Escaper;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\CollectionFactory;

class ExplanationLabel extends AbstractElement
{

    public function __construct( // phpcs:ignore
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $escaper, $data);
    }

    /**
     * Get Html element
     *
     * @return string
     */
    public function getElementHtml()
    {
        return 'Select a maximum of 12 attributes in total from the following attribute lists';
    }
}
