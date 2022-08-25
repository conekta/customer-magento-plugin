<?php

namespace Conekta\Payments\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * Get Allowed types
     *
     * @return array
     */
    public function getAllowedTypes()
    {
        return [
            'VI', 'MC', 'AE'
        ];
    }
}
