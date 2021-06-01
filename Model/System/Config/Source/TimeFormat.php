<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class TimeFormat implements ArrayInterface
{
    /**
     * @return array
     */
    
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Days')], ['value' => 0, 'label' => __('Hours')]];
    }
}
