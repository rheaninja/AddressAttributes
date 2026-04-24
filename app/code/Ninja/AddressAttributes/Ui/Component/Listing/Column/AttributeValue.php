<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;

class AttributeValue extends Column
{
    private array $attributeMap = [];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        AttributeCollectionFactory $attributeCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->buildAttributeMap($attributeCollectionFactory);
    }

    private function buildAttributeMap(AttributeCollectionFactory $factory): void
    {
        $collection = $factory->create();
        foreach ($collection as $attribute) {
            $code = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$attribute->getAttributeCode()));
            $this->attributeMap['ninja_' . $code] = $attribute;
        }
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            foreach ($this->attributeMap as $columnName => $attribute) {
                if (!array_key_exists($columnName, $item)) {
                    continue;
                }

                $item[$columnName] = $this->resolveDisplayValue(
                    $attribute,
                    $item[$columnName]
                );
            }
        }

        return $dataSource;
    }

    private function resolveDisplayValue($attribute, $rawValue): string
    {
        if ($rawValue === null || $rawValue === '') {
            return '';
        }

        $type = (string)$attribute->getData('type');

        // ── Yes/No ────────────────────────────────────────────────────────
        if ($type === 'yesno') {
            return $rawValue === '1' || $rawValue === 1 ? __('Yes') : __('No');
        }

        // ── Select / Radio — resolve label from options ───────────────────
        if (in_array($type, ['select', 'radio'])) {
            return $this->resolveOptionLabel($attribute, $rawValue);
        }

        // ── Checkbox — may be comma separated values ──────────────────────
        if ($type === 'checkbox') {
            $values = explode(',', (string)$rawValue);
            $labels = [];
            foreach ($values as $val) {
                $label = $this->resolveOptionLabel($attribute, trim($val));
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
            return implode(', ', $labels);
        }

        return (string)$rawValue;
    }

    private function resolveOptionLabel($attribute, string $value): string
    {
        $rawOptions = $attribute->getData('options');

        if (is_string($rawOptions) && $rawOptions !== '') {
            $options = json_decode($rawOptions, true);
            if (!is_array($options)) {
                $options = [];
            }
        } elseif (is_array($rawOptions)) {
            $options = $rawOptions;
        } else {
            return $value;
        }

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }
            $optionValue = trim((string)($option['value'] ?? $option['label'] ?? ''));
            if ($optionValue === $value) {
                return (string)($option['label'] ?? $value);
            }
        }

        return $value;
    }
}