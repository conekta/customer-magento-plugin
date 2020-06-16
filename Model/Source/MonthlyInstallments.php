<?php

namespace Conekta\Payments\Model\Source;

class MonthlyInstallments implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 3,
                'label' => __('3 Meses')
            ],
            [
                'value' => 6,
                'label' => __('6 Meses')
            ],
            [
                'value' => 9,
                'label' => __('9 Meses')
            ],
            [
                'value' => 12,
                'label' => __('12 Meses')
            ],
            [
                'value' => 18,
                'label' => __('18 Meses')
            ]
        ];
    }
    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
                3 => __('3 Meses'),
                6 => __('6 Meses'),
                9 => __('9 Meses'),
                12 => __('12 Meses')
            ];
    }
}
