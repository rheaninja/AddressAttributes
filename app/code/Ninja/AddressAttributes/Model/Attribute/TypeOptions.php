<?php
namespace Ninja\AddressAttributes\Model\Attribute;

use Magento\Framework\Data\OptionSourceInterface;

class TypeOptions implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'text', 'label' => __('Text')],
            ['value' => 'select', 'label' => __('Select')],
            ['value' => 'radio', 'label' => __('Radio')],
            ['value' => 'checkbox', 'label' => __('Checkbox')],
            ['value' => 'date', 'label' => __('Date')]
        ];
    }
}
