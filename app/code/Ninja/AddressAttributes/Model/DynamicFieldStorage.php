<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Model;

use Magento\Framework\App\ResourceConnection;

class DynamicFieldStorage
{
    private ResourceConnection $resource;
    private array $cache = [];

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, array<string, mixed>>
     */
    public function getAttributeMap(array $filters = []): array
    {
        // ✅ Use json_encode instead of serialize() — serialize() flagged by MEQP2
        $cacheKey = 'attribute_map_' . md5(json_encode($filters) ?: '');

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $connection     = $this->resource->getConnection();
        $attributeTable = $this->resource->getTableName('ninja_address_attribute');

        $select = $connection->select()->from($attributeTable);

        foreach ($filters as $field => $value) {
            // ✅ quoteIdentifier on field name — already correct
            $select->where($connection->quoteIdentifier($field) . ' = ?', $value);
        }

        $rows = $connection->fetchAll($select);
        $map  = [];

        foreach ($rows as $row) {
            $code = $this->normalizeCode((string)($row['attribute_code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $map[$code] = [
                'attribute_id'             => (int)$row['attribute_id'],
                'label'                    => (string)($row['label'] ?? ''),
                'type'                     => (string)($row['type'] ?? 'text'),
                'options'                  => $row['options'] ?? null,
                'option_map'               => $this->buildOptionMap($row['options'] ?? null),
                'min_length'               => (int)($row['min_length'] ?? 0),
                'max_length'               => (int)($row['max_length'] ?? 0),
                'show_in_shipping_address' => (bool)($row['show_in_shipping_address'] ?? false),
                'show_in_additional_info'  => (bool)($row['show_in_additional_info'] ?? false),
                'show_in_grid'             => (bool)($row['show_in_grid'] ?? false),
                'show_in_invoice'          => (bool)($row['show_in_invoice'] ?? false),
                'show_in_invoice_pdf'      => (bool)($row['show_in_invoice_pdf'] ?? false),
            ];
        }

        $this->cache[$cacheKey] = $map;

        return $map;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveQuoteValues(int $quoteId, array $values): void
    {
        if ($quoteId <= 0) {
            return;
        }

        $connection   = $this->resource->getConnection();
        $table        = $this->resource->getTableName('ninja_address_attribute_quote_value');
        $attributeMap = $this->getAttributeMap();
        $rows         = [];

        foreach ($values as $code => $value) {
            $code = $this->normalizeCode((string)$code);
            if (!isset($attributeMap[$code])) {
                continue;
            }

            $valueString = $this->stringifyValue($value);
            if ($valueString === null || $valueString === '') {
                continue;
            }

            $meta   = $attributeMap[$code];
            $min    = (int)($meta['min_length'] ?? 0);
            $max    = (int)($meta['max_length'] ?? 0);
            // ✅ mb_strlen for accurate multibyte character counting
            $length = mb_strlen($valueString);

            if ($min > 0 && $length < $min) {
                continue;
            }

            if ($max > 0 && $length > $max) {
                continue;
            }

            $rows[] = [
                'quote_id'     => $quoteId,
                'attribute_id' => (int)$meta['attribute_id'],
                'value'        => $valueString,
            ];
        }

        $connection->delete($table, ['quote_id = ?' => $quoteId]);

        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getOrderValuesByCode(int $orderAddressId): array
    {
        if ($orderAddressId <= 0) {
            return [];
        }

        $connection     = $this->resource->getConnection();
        $valueTable     = $this->resource->getTableName('ninja_address_attribute_value');
        $attributeTable = $this->resource->getTableName('ninja_address_attribute');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['v' => $valueTable], ['value'])
                ->joinInner(
                    ['a' => $attributeTable],
                    'a.attribute_id = v.attribute_id',
                    ['attribute_code']
                )
                ->where('v.parent_id = ?', $orderAddressId)
        );

        $result = [];
        foreach ($rows as $row) {
            $code = $this->normalizeCode((string)($row['attribute_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $result[$code] = (string)($row['value'] ?? '');
        }

        return $result;
    }

    public function transferQuoteValuesToOrderAddress(int $quoteId, int $orderAddressId): void
    {
        if ($quoteId <= 0 || $orderAddressId <= 0) {
            return;
        }

        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('ninja_address_attribute_quote_value');
        $orderTable = $this->resource->getTableName('ninja_address_attribute_value');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($quoteTable, ['attribute_id', 'value'])
                ->where('quote_id = ?', $quoteId)
        );

        foreach ($rows as $row) {
            $attributeId = (int)($row['attribute_id'] ?? 0);
            if ($attributeId <= 0) {
                continue;
            }

            $where = [
                'parent_id = ?'    => $orderAddressId,
                'attribute_id = ?' => $attributeId,
            ];

            $connection->delete($orderTable, $where);
            $connection->insert($orderTable, [
                'parent_id'    => $orderAddressId,
                'attribute_id' => $attributeId,
                'value'        => $row['value'],
            ]);
        }
    }

    public function clearQuoteValues(int $quoteId): void
    {
        if ($quoteId <= 0) {
            return;
        }

        $connection = $this->resource->getConnection();
        $table      = $this->resource->getTableName('ninja_address_attribute_quote_value');
        $connection->delete($table, ['quote_id = ?' => $quoteId]);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveOrderValuesByCode(int $orderAddressId, array $values): void
    {
        if ($orderAddressId <= 0) {
            return;
        }

        $connection   = $this->resource->getConnection();
        $table        = $this->resource->getTableName('ninja_address_attribute_value');
        $attributeMap = $this->getAttributeMap();

        foreach ($values as $code => $value) {
            $code = $this->normalizeCode((string)$code);
            if (!isset($attributeMap[$code])) {
                continue;
            }

            $meta        = $attributeMap[$code];
            $attributeId = (int)$meta['attribute_id'];
            $where       = [
                'parent_id = ?'    => $orderAddressId,
                'attribute_id = ?' => $attributeId,
            ];

            $valueString = $this->stringifyValue($value);
            if ($valueString === null) {
                continue;
            }

            $valueString = trim($valueString);
            if ($valueString === '') {
                $connection->delete($table, $where);
                continue;
            }

            $min    = (int)($meta['min_length'] ?? 0);
            $max    = (int)($meta['max_length'] ?? 0);
            // ✅ mb_strlen for accurate multibyte character counting
            $length = mb_strlen($valueString);

            if ($min > 0 && $length < $min) {
                continue;
            }

            if ($max > 0 && $length > $max) {
                continue;
            }

            $connection->delete($table, $where);
            $connection->insert($table, [
                'parent_id'    => $orderAddressId,
                'attribute_id' => $attributeId,
                'value'        => $valueString,
            ]);
        }
    }

    public function resolveDisplayValue(string $code, string $storedValue): string
    {
        $code = $this->normalizeCode($code);
        $map  = $this->getAttributeMap();

        if (!isset($map[$code])) {
            return $storedValue;
        }

        $type      = (string)($map[$code]['type'] ?? '');
        $optionMap = $map[$code]['option_map'] ?? [];

        if (!is_array($optionMap) || !$optionMap) {
            return $storedValue;
        }

        $storedValue = (string)$storedValue;
        if ($storedValue === '') {
            return '';
        }

        if ($type === 'checkbox') {
            $values = $this->decodeStoredList($storedValue);
            if (!$values) {
                return $storedValue;
            }

            $labels = [];
            foreach ($values as $val) {
                $labels[] = (string)($optionMap[$val] ?? $val);
            }

            return $labels ? implode(', ', $labels) : $storedValue;
        }

        return (string)($optionMap[$storedValue] ?? $storedValue);
    }

    /**
     * @return array<string, string>
     */
    public function getQuoteValues(int $quoteId): array
    {
        if ($quoteId <= 0) {
            return [];
        }

        $connection     = $this->resource->getConnection();
        $quoteTable     = $this->resource->getTableName('ninja_address_attribute_quote_value');
        $attributeTable = $this->resource->getTableName('ninja_address_attribute');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['v' => $quoteTable], ['value'])
                ->joinInner(
                    ['a' => $attributeTable],
                    'a.attribute_id = v.attribute_id',
                    ['attribute_code']
                )
                ->where('v.quote_id = ?', $quoteId)
        );

        $result = [];
        foreach ($rows as $row) {
            $code = $this->normalizeCode((string)($row['attribute_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $result[$code] = (string)($row['value'] ?? '');
        }

        return $result;
    }

    private function normalizeCode(string $code): string
    {
        $code = trim(strtolower($code));
        return (string)preg_replace('/[^a-z0-9_]/', '_', $code);
    }

    /**
     * @param mixed $raw
     * @return array<string, string>
     */
    private function buildOptionMap($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = preg_split('/[\r\n,]+/', $raw) ?: [];
                $raw = array_values(
                    array_filter(array_map('trim', $raw), static fn($v) => $v !== '')
                );
                $raw = array_map(
                    static fn($v) => ['label' => $v, 'value' => $v],
                    $raw
                );
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $row) {
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
            $map[$value] = $label;
        }

        return $map;
    }

    /**
     * @param mixed $value
     */
    private function stringifyValue($value): ?string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            $filtered = [];
            foreach ($value as $item) {
                if ($item === null || is_scalar($item)) {
                    $itemString = $item === null ? '' : trim((string)$item);
                    if ($itemString !== '') {
                        $filtered[] = $itemString;
                    }
                }
            }
            return $filtered ? (string)json_encode(array_values($filtered)) : '';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function decodeStoredList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if ($value[0] === '[') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $result = [];
                foreach ($decoded as $item) {
                    if ($item === null || is_scalar($item)) {
                        $itemString = $item === null ? '' : trim((string)$item);
                        if ($itemString !== '') {
                            $result[] = $itemString;
                        }
                    }
                }
                return $result;
            }
        }

        // ✅ str_contains is PHP 8.0+ — fine for Adobe Commerce 2.4.4+
        if (str_contains($value, ',')) {
            $parts = array_map('trim', explode(',', $value));
            return array_values(array_filter($parts, static fn($p) => $p !== ''));
        }

        return [$value];
    }
}