<?php

namespace Ninja\AddressAttributes\Plugin\Checkout;

use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;

class LayoutProcessor
{
    protected $collectionFactory;

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array $jsLayout
    ) {
        $collection = $this->collectionFactory->create();

        $fieldset =& $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        foreach ($collection as $attribute) {

            $attributeCode = trim($attribute->getAttributeCode());

            if (!$attributeCode) {
                continue;
            }

            // Ensure unique key
            $attributeCode = preg_replace('/[^a-z0-9_]/', '_', strtolower($attributeCode));
            $type = (string)$attribute->getData('type');
            $options = $this->buildUiOptions($attribute->getData('options'));
            $defaultValue = $this->extractDefaultValue($attribute->getData('options'), $type);

            $component = 'Magento_Ui/js/form/element/abstract';
            $elementTmpl = 'ui/form/element/input';
            $configOptions = [];

            if ($type === 'date') {
                $component = 'Magento_Ui/js/form/element/date';
                $elementTmpl = 'ui/form/element/date';
                $configOptions = [
                    'options' => [
                        'dateFormat' => 'yyyy-MM-dd',
                        'showsTime' => false,
                    ],
                ];
            } elseif ($type === 'select') {
                $component = 'Magento_Ui/js/form/element/select';
                $elementTmpl = 'ui/form/element/select';
            } elseif ($type === 'radio') {
                $component = 'Magento_Ui/js/form/element/checkbox-set';
                $elementTmpl = 'ui/form/element/checkbox-set';
            } elseif ($type === 'checkbox') {
                $component = 'Magento_Ui/js/form/element/multiselect';
                $elementTmpl = 'ui/form/element/multiselect';
            }

            $fieldset[$attributeCode] = [
                'component' => $component,
                'config' => [
                    'customScope' => 'shippingAddress.custom_attributes',
                    'template' => 'ui/form/field',
                    'elementTmpl' => $elementTmpl,
                ] + $configOptions,
                'options' => $options ?: [],
                'value' => $defaultValue,
                'dataScope' => 'shippingAddress.custom_attributes.' . $attributeCode,
                'label' => $attribute->getLabel(),
                'provider' => 'checkoutProvider',
                'visible' => true,
                'validation' => [
                    'required-entry' => (bool)$attribute->getIsRequired()
                ],
                'sortOrder' => 200 + (int)$attribute->getId()
            ];
        }

        return $jsLayout;
    }

    /**
     * @param mixed $rawOptions
     * @return array<int, array{value:string,label:string}>
     */
    private function buildUiOptions($rawOptions): array
    {
        if (is_string($rawOptions) && $rawOptions !== '') {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $rawOptions = $decoded;
            } else {
                $rawOptions = $this->parsePlainOptions($rawOptions);
            }
        }

        if (!is_array($rawOptions)) {
            return [];
        }

        $result = [];
        
        foreach ($rawOptions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string)($row['label'] ?? ''));
            $value = trim((string)($row['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            if ($value === '') {
                $value = $label;
            }
            if ($label === '') {
                $label = $value;
            }
            $result[] = ['value' => '', 'label' => '-- Please Select --'];
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param mixed $rawOptions
     * @return string|array<int, string>|null
     */
    private function extractDefaultValue($rawOptions, string $type)
    {
        if (is_string($rawOptions) && $rawOptions !== '') {
            $decoded = json_decode($rawOptions, true);
            if (is_array($decoded)) {
                $rawOptions = $decoded;
            } else {
                $rawOptions = $this->parsePlainOptions($rawOptions);
            }
        }

        if (!is_array($rawOptions)) {
            return null;
        }

        $defaults = [];
        foreach ($rawOptions as $row) {
            if (!is_array($row) || empty($row['is_default'])) {
                continue;
            }
            $value = trim((string)($row['value'] ?? $row['label'] ?? ''));
            if ($value === '') {
                continue;
            }
            $defaults[] = $value;
        }

        if (!$defaults) {
            return $type === 'checkbox' ? [] : null;
        }

        if ($type === 'checkbox') {
            return $defaults;
        }

        return $defaults[0];
    }

    /**
     * @return array<int, array{label:string,value:string,is_default?:int}>
     */
    private function parsePlainOptions(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }
            $result[] = ['label' => $part, 'value' => $part];
        }

        return $result;
    }
}
