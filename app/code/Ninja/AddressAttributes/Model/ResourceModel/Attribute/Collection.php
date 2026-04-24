<?php
// app/code/Ninja/AddressAttributes/Model/ResourceModel/Attribute/Collection.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Model\ResourceModel\Attribute;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\StoreManagerInterface;

class Collection extends AbstractCollection
{
    private ?int $storeId = null;

    protected function _construct(): void
    {
        $this->_init(
            \Ninja\AddressAttributes\Model\Attribute::class,
            \Ninja\AddressAttributes\Model\ResourceModel\Attribute::class
        );
    }

    /**
     * Filter collection by store ID
     * If no store IDs assigned to attribute — show on all stores
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->storeId = $storeId;
        return $this;
    }

    protected function _renderFiltersBefore(): void
    {
        if ($this->storeId !== null) {
            $storeTable = $this->getTable('ninja_address_attribute_store');

            // Join store table
            // Show attribute if:
            // 1. No store rows exist for this attribute (all stores) OR
            // 2. Store row exists matching current store_id
            $this->getSelect()->joinLeft(
                ['attr_store' => $storeTable],
                'attr_store.attribute_id = main_table.attribute_id',
                []
            );

            $this->getSelect()->where(
                'attr_store.store_id IS NULL OR attr_store.store_id = ?',
                $this->storeId
            );

            $this->getSelect()->group('main_table.attribute_id');
        }

        parent::_renderFiltersBefore();
    }
}