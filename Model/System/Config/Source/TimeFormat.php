<?php
declare(strict_types=1);

namespace Conekta\Payments\Model\System\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class TimeFormat implements ArrayInterface
{
    /**
     * Options array
     *
     * @var array
     */
    // public $options = null;

    /**
     * @return array
     */
    // public function toOptionArray()
    // {
    //     if (!$this->options) {
    //         $this->options = [
    //             ['value' => 'new', 'label' => __('New')],
    //             ['value' => 'refurbished', 'label' => __('Refurbished')],
    //         ];
    //     }

        // return $this->options;

    public function toOptionArray()
    {
        return [['value' => 1, 'label' => __('Days')], ['value' => 0, 'label' => __('Hours')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [1 => __('Days'), 0 => __('Hours')];
    }
}
