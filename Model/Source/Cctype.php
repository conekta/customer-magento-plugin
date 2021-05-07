<?php

namespace Conekta\Payments\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return [
            'VI', 'MC', 'AE'
        ];
    }
}
