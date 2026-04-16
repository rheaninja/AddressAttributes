<?php
namespace Ninja\AddressAttributes\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\AbstractModel;

class Attribute extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ninja_address_attribute', 'attribute_id');
    }

    protected function _beforeSave(AbstractModel $object)
    {
        $type = (string)$object->getData('type');
        $options = $object->getData('options');

        if (!in_array($type, ['select', 'radio', 'checkbox'], true)) {
            $object->setData('options', null);
            return parent::_beforeSave($object);
        }

        if (is_array($options)) {
            $normalized = [];
            foreach ($options as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = trim((string)($row['label'] ?? ''));
                $value = trim((string)($row['value'] ?? ''));
                if ($label === '' && $value === '') {
                    continue;
                }
                if ($value === '') {
                    // Fallback: if admin only filled label.
                    $value = $label;
                }
                $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : ($index + 1);
                $normalized[] = [
                    'label' => $label,
                    'value' => $value,
                    'is_default' => !empty($row['is_default']) ? 1 : 0,
                    'sort_order' => $sortOrder,
                ];
            }
            usort($normalized, static function (array $a, array $b): int {
                return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
            });
            $object->setData('options', $normalized ? json_encode($normalized) : null);
        } elseif (is_string($options)) {
            $options = trim($options);
            if ($options === '') {
                $object->setData('options', null);
                return parent::_beforeSave($object);
            }

            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                // Normalize any JSON payload we received.
                $normalized = [];
                foreach ($decoded as $row) {
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
                    $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : (count($normalized) + 1);
                    $normalized[] = [
                        'label' => $label === '' ? $value : $label,
                        'value' => $value,
                        'is_default' => !empty($row['is_default']) ? 1 : 0,
                        'sort_order' => $sortOrder,
                    ];
                }
                usort($normalized, static function (array $a, array $b): int {
                    return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
                });
                $object->setData('options', $normalized ? json_encode($normalized) : null);
                return parent::_beforeSave($object);
            }

            // Fallback: allow simple newline/comma-separated options typed into the box.
            $parts = preg_split('/[\r\n,]+/', $options) ?: [];
            $normalized = [];
            foreach ($parts as $part) {
                $part = trim((string)$part);
                if ($part === '') {
                    continue;
                }
                $normalized[] = ['label' => $part, 'value' => $part, 'is_default' => 0];
            }
            $object->setData('options', $normalized ? json_encode($normalized) : null);
        } else {
            $object->setData('options', null);
        }

        return parent::_beforeSave($object);
    }

    protected function _afterLoad(AbstractModel $object)
    {
        $options = $object->getData('options');
        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                $object->setData('options', $decoded);
            }
        }

        return parent::_afterLoad($object);
    }
}
