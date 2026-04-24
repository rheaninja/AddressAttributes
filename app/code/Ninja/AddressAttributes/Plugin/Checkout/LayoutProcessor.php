<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Checkout;

use Magento\Store\Model\StoreManagerInterface;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;

class LayoutProcessor
{
    protected $collectionFactory;
    protected $storeManager;

    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }

    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array $jsLayout
    ) {

        $storeId    = (int)$this->storeManager->getStore()->getId();

        $collection = $this->collectionFactory->create()
            ->addStoreFilter($storeId); 
        
        // ── Shipping address fieldsets ─────────────────────────────────────────
        $shippingAddress =& $jsLayout['components']['checkout']['children']['steps']
            ['children']['shipping-step']['children']['shippingAddress'];

        $addressFieldset =& $shippingAddress['children']['shipping-address-fieldset']['children'];

        if (!isset($shippingAddress['children']['before-shipping-method-form']['children'])) {
            $shippingAddress['children']['before-shipping-method-form']['children'] = [];
        }
        $beforeMethodFieldset =& $shippingAddress['children']['before-shipping-method-form']['children'];

        if (!isset($shippingAddress['children']['after-shipping-method-form'])) {
            $shippingAddress['children']['after-shipping-method-form'] = [
                'component'   => 'uiComponent',
                'displayArea' => 'after-shipping-method-form',
                'sortOrder'   => 300,
                'children'    => [],
            ];
        }
        $afterMethodFieldset =& $shippingAddress['children']['after-shipping-method-form']['children'];

        // ── Payment fieldsets (beforeMethods / afterMethods) ───────────────────
        $payment =& $jsLayout['components']['checkout']['children']['steps']
            ['children']['billing-step']['children']['payment']['children'];

        if (!isset($payment['beforeMethods']['children'])) {
            $payment['beforeMethods']['children'] = [];
        }
        $beforePaymentFieldset =& $payment['beforeMethods']['children'];

        if (!isset($payment['afterMethods']['children'])) {
            $payment['afterMethods']['children'] = [];
        }
        $afterPaymentFieldset =& $payment['afterMethods']['children'];

        foreach ($collection as $attribute) {

            $attributeCode = trim($attribute->getAttributeCode());
            if (!$attributeCode) {
                continue;
            }

            $attributeCode = preg_replace('/[^a-z0-9_]/', '_', strtolower($attributeCode));
            $type          = (string)$attribute->getData('type');
            $options       = $this->buildUiOptions($attribute->getData('options'));
            $defaultValue  = $this->extractDefaultValue($attribute->getData('options'), $type);

            $component     = 'Magento_Ui/js/form/element/abstract';
            $elementTmpl   = 'ui/form/element/input';
            $configOptions = [];

            if ($type === 'textarea') {
                $component   = 'Magento_Ui/js/form/element/textarea';
                $elementTmpl = 'ui/form/element/textarea';
            } elseif ($type === 'yesno') {
                $component   = 'Magento_Ui/js/form/element/single-checkbox';
                $elementTmpl = 'Ninja_AddressAttributes/form/element/toggle';
            } elseif ($type === 'date') {
                $component   = 'Magento_Ui/js/form/element/date';
                $elementTmpl = 'ui/form/element/date';
                $configOptions = [
                    'dateOptions' => [
                        'dateFormat' => 'MM/dd/y',
                        'showsTime'  => false,
                        'minDate'    => 0,
                        'appendTo'   => 'body',
                    ]
                ];
            } elseif ($type === 'select') {
                $component   = 'Magento_Ui/js/form/element/select';
                $elementTmpl = 'ui/form/element/select';
            } elseif ($type === 'radio') {
                $component   = 'Ninja_AddressAttributes/js/form/element/radio-set';
                $elementTmpl = 'Ninja_AddressAttributes/form/element/radio-set';
            } elseif ($type === 'checkbox') {
                $component   = 'Ninja_AddressAttributes/js/form/element/checkbox-set';
                $elementTmpl = 'Ninja_AddressAttributes/form/element/checkbox-set';
            }

            $position  = (string)($attribute->getData('position') ?? 'after_shipping_address');
            $sortOrder = (int)($attribute->getData('sort_order') ?? 0);

            // ── Payment fields use a different dataScope ───────────────────────
            $isPaymentPosition = in_array($position, ['before_payment_method', 'after_payment_method']);

            $fieldDefinition = [
                'component' => $component,
                'config'    => [
                    // Payment fields scope differently — they sit outside shippingAddress
                    'customScope' => $isPaymentPosition
                        ? 'shippingAddress.custom_attributes'
                        : 'shippingAddress.custom_attributes',
                    'template'    => 'ui/form/field',
                    'elementTmpl' => $elementTmpl,
                ],
                'options'    => $type === 'date'
                    ? ($configOptions['dateOptions'] ?? [])
                    : ($options ?: []),
                'value'      => $type === 'yesno' ? ($defaultValue ? '1' : '0') : $defaultValue,
                'dataScope'  => 'shippingAddress.custom_attributes.' . $attributeCode,
                'label'      => $attribute->getLabel(),
                'provider'   => 'checkoutProvider',
                'visible'    => true,
                'validation' => [
                    'required-entry' => (bool)$attribute->getIsRequired(),
                ],
            ];

            if ($type === 'text') {
                $min = (int)$attribute->getData('min_length');
                $max = (int)$attribute->getData('max_length');

                if ($min > 0 || $max > 0) {
                    // Add custom validation rule for length
                    $fieldDefinition['validation']['min_text_length'] = $min ?: 0;
                    $fieldDefinition['validation']['max_text_length'] = $max ?: 99999;
                }
            }
            switch ($position) {
                case 'before_shipping_address':
                    $fieldDefinition['sortOrder'] = 1 + $sortOrder;
                    $addressFieldset[$attributeCode] = $fieldDefinition;
                    break;

                case 'after_shipping_address':
                    $fieldDefinition['sortOrder'] = 200 + $sortOrder;
                    $addressFieldset[$attributeCode] = $fieldDefinition;
                    break;

                case 'before_shipping_method':
                    $fieldDefinition['sortOrder'] = 1 + $sortOrder;
                    $beforeMethodFieldset[$attributeCode] = $fieldDefinition;
                    break;

                case 'after_shipping_method':
                    $fieldDefinition['sortOrder'] = 1 + $sortOrder;
                    $afterMethodFieldset[$attributeCode] = $fieldDefinition;
                    break;

                case 'before_payment_method':
                    $fieldDefinition['sortOrder'] = 1 + $sortOrder;
                    $fieldDefinition['config']['customScope'] = 'customAttributes';
                    $fieldDefinition['dataScope']             = 'customAttributes.' . $attributeCode;
                    $beforePaymentFieldset[$attributeCode]    = $fieldDefinition;
                    break;

                case 'after_payment_method':
                    $fieldDefinition['sortOrder'] = 1 + $sortOrder;
                    $fieldDefinition['config']['customScope'] = 'customAttributes';
                    $fieldDefinition['dataScope']             = 'customAttributes.' . $attributeCode;
                    $afterPaymentFieldset[$attributeCode]     = $fieldDefinition;
                    break;
                    
                default:
                    $fieldDefinition['sortOrder'] = 200 + $sortOrder;
                    $addressFieldset[$attributeCode] = $fieldDefinition;
            }
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

        // Add the placeholder only once, before iterating options
        $result = [['value' => '', 'label' => __('-- Please Select --')]];
        
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
            $result[] = ['value' => $value, 'label' => $label];
        }

        // If only the placeholder was added (no real options), return empty
        if (count($result) === 1) {
            return [];
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
