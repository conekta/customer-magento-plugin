<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TimeFormat implements OptionSourceInterface
{
    /**
     * To option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Days')], ['value' => 0, 'label' => __('Hours')]];
    }
}
