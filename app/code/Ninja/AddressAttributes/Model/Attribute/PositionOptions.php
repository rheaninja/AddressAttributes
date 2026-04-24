<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Model\Attribute;

use Magento\Framework\Data\OptionSourceInterface;

class PositionOptions implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'after_shipping_address', 'label' => __('After Shipping Address')],
            ['value' => 'before_shipping_address', 'label' => __('Before Shipping Address')],
            ['value' => 'before_shipping_method', 'label' => __('Before Shipping Method')],
            ['value' => 'before_payment_method', 'label' => __('Before Payment Method')],
            ['value' => 'after_payment_method', 'label' => __('After Payment Method')]
        ];
    }
}