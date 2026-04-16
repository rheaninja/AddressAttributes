<?php
namespace Ninja\AddressAttributes\Model;

use Magento\Framework\Model\AbstractModel;

class AttributeValue extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Ninja\AddressAttributes\Model\ResourceModel\AttributeValue::class);
    }
}
