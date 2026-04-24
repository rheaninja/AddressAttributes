<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Adminhtml;

use Ninja\AddressAttributes\Model\DynamicFieldStorage;

class OrderAddressRendererPlugin
{
    private DynamicFieldStorage $dynamicFieldStorage;

    public function __construct(DynamicFieldStorage $dynamicFieldStorage)
    {
        $this->dynamicFieldStorage = $dynamicFieldStorage;
    }

    public function afterFormat(
        \Magento\Sales\Model\Order\Address\Renderer $subject,
        ?string $result,
        \Magento\Sales\Model\Order\Address $address,
        $type
    ): ?string {
        if ($result === null || !$address->getEntityId()) {
            return $result;
        }

        $values = $this->dynamicFieldStorage->getOrderValuesByCode((int)$address->getEntityId());
        if (!$values) {
            return $result;
        }

        // ✅ Only attributes with show_in_shipping_address = 1
        $map = $this->dynamicFieldStorage->getAttributeMap(['show_in_shipping_address' => 1]);
        if (!$map) {
            return $result;
        }

        $separator = $type === 'html' ? '<br/>' : PHP_EOL;
        $lines     = [];

        foreach ($map as $code => $meta) {
            if (!array_key_exists($code, $values)) {
                continue;
            }

            $value = $values[$code];

            if ($value === null || $value === '') {
                continue;
            }

            $fieldType = (string)($meta['type'] ?? 'text');

            if ($fieldType === 'yesno') {
                $display = $value === '1' ? (string)__('Yes') : (string)__('No');
            } else {
                $display = $this->dynamicFieldStorage->resolveDisplayValue($code, $value);
            }

            $lines[] = (string)$meta['label'] . ': ' . $display;
        }

        if (!$lines) {
            return $result;
        }

        return $result . $separator . implode($separator, $lines);
    }
}