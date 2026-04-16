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

        $map = $this->dynamicFieldStorage->getAttributeMap();
        $separator = $type === 'html' ? '<br/>' : PHP_EOL;
        $lines = [];
        foreach ($map as $code => $meta) {
            if (!isset($values[$code]) || $values[$code] === '') {
                continue;
            }
            $display = $this->dynamicFieldStorage->resolveDisplayValue($code, (string)$values[$code]);
            $lines[] = (string)$meta['label'] . ': ' . $display;
        }

        if (!$lines) {
            return $result;
        }

        return $result . $separator . implode($separator, $lines);
    }
}
