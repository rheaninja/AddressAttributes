<?php
// app/code/Ninja/AddressAttributes/Block/Adminhtml/Order/View/AdditionalInfo.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;
use Ninja\AddressAttributes\Model\ResourceModel\AttributeValue\CollectionFactory as ValueCollectionFactory;

class AdditionalInfo extends Template
{
    public function __construct(
        Context $context,
        private Registry $registry,
        private AttributeCollectionFactory $attributeCollectionFactory,
        private ValueCollectionFactory $valueCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?\Magento\Sales\Model\Order
    {
        return $this->registry->registry('current_order');
    }

    public function getAttributeValues(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) return [];

        $addressId = (int)$shippingAddress->getId();

        // ✅ Only attributes with show_in_additional_info = 1
        $attributes = $this->attributeCollectionFactory->create()
            ->addFieldToFilter('show_in_additional_info', 1);

        $values = $this->valueCollectionFactory->create()
            ->addFieldToFilter('parent_id', $addressId);

        $valueMap = [];
        foreach ($values as $value) {
            $valueMap[(int)$value->getAttributeId()] = $value->getValue();
        }

        $result = [];
        foreach ($attributes as $attribute) {
            $attributeId = (int)$attribute->getId();
            $rawValue    = $valueMap[$attributeId] ?? null;

            $result[] = [
                'label' => $attribute->getLabel(),
                'value' => $this->resolveDisplayValue($attribute, $rawValue),
                'code'  => $attribute->getAttributeCode(),
            ];
        }

        return $result;
    }

    private function resolveDisplayValue($attribute, $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '';
        }

        $type = (string)$attribute->getData('type');

        if ($type === 'yesno') {
            return ($rawValue === '1' || $rawValue === 1)
                ? (string)__('Yes')
                : (string)__('No');
        }

        if (in_array($type, ['select', 'radio'])) {
            return $this->resolveOptionLabel($attribute, (string)$rawValue);
        }

        if ($type === 'checkbox') {
            $values = explode(',', (string)$rawValue);
            $labels = array_filter(array_map(
                fn($v) => $this->resolveOptionLabel($attribute, trim($v)),
                $values
            ));
            return implode(', ', $labels);
        }

        return (string)$rawValue;
    }

    private function resolveOptionLabel($attribute, string $value): string
    {
        if ($value === '') return '';

        $rawOptions = $attribute->getData('options');
        if (is_string($rawOptions) && $rawOptions !== '') {
            $options = json_decode($rawOptions, true);
        } elseif (is_array($rawOptions)) {
            $options = $rawOptions;
        } else {
            return $value;
        }

        if (!is_array($options)) return $value;

        foreach ($options as $option) {
            if (!is_array($option)) continue;
            $optionValue = trim((string)($option['value'] ?? $option['label'] ?? ''));
            if ($optionValue === $value) {
                return (string)($option['label'] ?? $value);
            }
        }

        return $value;
    }

    public function getEditUrl(): string
    {
        $order = $this->getOrder();
        return $this->getUrl(
            'addressattributes/order/edit',
            ['order_id' => $order ? $order->getId() : 0]
        );
    }
}