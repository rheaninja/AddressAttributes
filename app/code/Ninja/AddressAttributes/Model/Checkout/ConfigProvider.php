<?php
// app/code/Ninja/AddressAttributes/Model/Checkout/ConfigProvider.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private StoreManagerInterface $storeManager
    ) {}

    public function getConfig(): array
    {
        $fieldTypes       = [];
        $validationConfig = [];

        $storeId    = (int)$this->storeManager->getStore()->getId();
        $collection = $this->collectionFactory->create()
            ->addStoreFilter($storeId);

        foreach ($collection as $attribute) {
            $code = preg_replace(
                '/[^a-z0-9_]/', '_',
                strtolower(trim($attribute->getAttributeCode()))
            );
            $type     = (string)$attribute->getData('type');
            $position = (string)($attribute->getData('position') ?? 'after_shipping_address');

            // ── Field types map ───────────────────────────────────────────
            $fieldTypes[$code] = $type;

            // ── Validation config for text fields ─────────────────────────
            if ($type === 'text') {
                $min = (int)$attribute->getData('min_length');
                $max = (int)$attribute->getData('max_length');

                if ($min > 0 || $max > 0) {
                    $validationConfig[$code] = [
                        'label'    => (string)$attribute->getLabel(),
                        'min'      => $min,
                        'max'      => $max,
                        'position' => $position,
                    ];
                }
            }
        }

        return [
            // ✅ These go directly into window.checkoutConfig
            'dynamicFieldTypes'      => $fieldTypes,
            'dynamicFieldValidation' => $validationConfig,
        ];
    }
}