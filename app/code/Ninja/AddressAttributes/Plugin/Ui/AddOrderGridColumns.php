<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Ui;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;

class AddOrderGridColumns
{
    private array $attributeMap = [];

    public function __construct(
        private AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->buildAttributeMap();
    }

    private function buildAttributeMap(): void
    {
        $collection = $this->attributeCollectionFactory->create();
        // ✅ Only show_in_grid attributes
        $collection->addFieldToFilter('show_in_grid', 1);

        foreach ($collection as $attribute) {
            $code = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$attribute->getAttributeCode()));
            $this->attributeMap['ninja_' . $code] = $attribute;
        }
    }

    public function afterGetMeta(DataProvider $subject, array $meta): array
    {
        if ($subject->getName() !== 'sales_order_grid_data_source') {
            return $meta;
        }

        $columns = [];
        foreach ($this->attributeMap as $columnName => $attribute) {
            $columns[$columnName] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'label'         => __($attribute->getLabel()),
                            'filter'        => 'text',
                            'visible'       => true,
                            'sortable'      => true,
                            'component'     => 'Magento_Ui/js/grid/columns/column',
                            'componentType' => 'column',
                        ],
                    ],
                ],
            ];
        }

        if ($columns) {
            $meta['sales_order_columns']['children'] = array_merge(
                $meta['sales_order_columns']['children'] ?? [],
                $columns
            );
        }

        return $meta;
    }

    public function afterGetData(DataProvider $subject, array $data): array
    {
        if ($subject->getName() !== 'sales_order_grid_data_source') {
            return $data;
        }

        if (empty($data['items'])) {
            return $data;
        }

        foreach ($data['items'] as &$item) {
            foreach ($this->attributeMap as $columnName => $attribute) {
                if (!array_key_exists($columnName, $item)) {
                    continue;
                }
                $item[$columnName] = $this->resolveDisplayValue($attribute, $item[$columnName]);
            }
        }

        return $data;
    }

    private function resolveDisplayValue($attribute, $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '';
        }

        $type = (string)$attribute->getData('type');

        if ($type === 'yesno') {
            return ($rawValue === '1' || $rawValue === 1) ? (string)__('Yes') : (string)__('No');
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
}