<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Model\ResourceModel\Order\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;
use Psr\Log\LoggerInterface as Logger;

class Collection extends OriginalCollection
{
    private AttributeCollectionFactory $attributeCollectionFactory;
    private bool $attributesJoined = false;

    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        AttributeCollectionFactory $attributeCollectionFactory,
        $mainTable = 'sales_order_grid',
        $resourceModel = \Magento\Sales\Model\ResourceModel\Order::class
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }

    protected function _renderFiltersBefore(): void
    {
        if (!$this->attributesJoined) {
            $this->joinAttributeValues();
            $this->attributesJoined = true;
        }
        parent::_renderFiltersBefore();
    }

    private function joinAttributeValues(): void
    {
        $select       = $this->getSelect();
        $valueTable   = $this->getTable('ninja_address_attribute_value');
        $addressTable = $this->getTable('sales_order_address');
        $fromPart     = $select->getPart(\Magento\Framework\DB\Select::FROM);

        if (!isset($fromPart['ninja_shipping_address'])) {
            $select->joinLeft(
                ['ninja_shipping_address' => $addressTable],
                "ninja_shipping_address.parent_id = main_table.entity_id
                AND ninja_shipping_address.address_type = 'shipping'",
                []
            );
        }

        // ✅ Only fetch attributes marked as show_in_grid = 1
        $attributeCollection = $this->attributeCollectionFactory->create();
        $attributeCollection->addFieldToFilter('show_in_grid', 1);

        foreach ($attributeCollection as $attribute) {
            $code        = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$attribute->getAttributeCode()));
            $attributeId = (int)$attribute->getId();
            $alias       = 'ninja_attr_' . $code;

            if (isset($fromPart[$alias])) {
                continue;
            }

            $select->joinLeft(
                [$alias => $valueTable],
                sprintf(
                    '%s.parent_id = ninja_shipping_address.entity_id AND %s.attribute_id = %d',
                    $alias,
                    $alias,
                    $attributeId
                ),
                ['ninja_' . $code => $alias . '.value']
            );
        }
    }
}