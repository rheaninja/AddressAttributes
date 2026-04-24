<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Order;

use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Order;

class SyncOrderGrid
{
    // ninja_address_attribute_value is written by SaveDynamicAddressAttribute
    // which fires on shipping save and stores parent_id = sales_order_address.entity_id
    // Grid collection joins at query time so no sync needed
    public function afterSave(
        OrderResource $subject,
        OrderResource $result,
        Order $order
    ): OrderResource {
        return $result;
    }
}