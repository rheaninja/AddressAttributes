<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AttributeValue extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ninja_address_attribute_value', 'entity_id');
    }
}
