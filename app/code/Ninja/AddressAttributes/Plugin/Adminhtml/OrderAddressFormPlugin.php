<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Adminhtml;

use Magento\Framework\Data\Form;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;

class OrderAddressFormPlugin
{
    private DynamicFieldStorage $dynamicFieldStorage;

    public function __construct(DynamicFieldStorage $dynamicFieldStorage)
    {
        $this->dynamicFieldStorage = $dynamicFieldStorage;
    }

    public function afterGetForm(\Magento\Sales\Block\Adminhtml\Order\Address\Form $subject, Form $form): Form
    {
        $fieldset = $form->getElement('main');
        if (!$fieldset) {
            return $form;
        }

        $formValues = $subject->getFormValues();
        $orderAddressId = (int)($formValues['entity_id'] ?? 0);
        $savedValues = $this->dynamicFieldStorage->getOrderValuesByCode($orderAddressId);
        $attributeMap = $this->dynamicFieldStorage->getAttributeMap();

        foreach ($attributeMap as $code => $meta) {
            $value = $savedValues[$code] ?? null;

            if ($form->getElement($code)) {
                if ($value !== null) {
                    $form->getElement($code)->setValue($value);
                }
                continue;
            }

            $type = (string)($meta['type'] ?? 'text');
            $optionMap = $meta['option_map'] ?? [];
            $elementType = 'text';

            if ($type === 'date') {
                $elementType = 'date';
            } elseif ($type === 'select') {
                $elementType = 'select';
            } elseif ($type === 'radio') {
                $elementType = 'radios';
            } elseif ($type === 'checkbox') {
                $elementType = 'checkboxes';
            }

            if ($elementType === 'checkboxes' && is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = array_values(array_filter(array_map('strval', $decoded), static fn($v) => $v !== ''));
                } elseif (str_contains($value, ',')) {
                    $parts = array_map('trim', explode(',', $value));
                    $value = array_values(array_filter($parts, static fn($v) => $v !== ''));
                }
            }

            $field = $fieldset->addField(
                $code,
                $elementType,
                [
                    'name' => $code,
                    'label' => (string)$meta['label'],
                    'title' => (string)$meta['label'],
                    'required' => (bool)$meta['is_required'],
                    'value' => $value,
                ]
            );

            if (in_array($elementType, ['select', 'radios', 'checkboxes'], true) && is_array($optionMap) && $optionMap) {
                $values = [];
                $values[] = ['value' => '', 'label' => '-- Please Select --'];
                foreach ($optionMap as $optValue => $optLabel) {
                    $values[] = ['value' => (string)$optValue, 'label' => (string)$optLabel];
                }
                $field->setValues($values);
            }
        }

        return $form;
    }
}
