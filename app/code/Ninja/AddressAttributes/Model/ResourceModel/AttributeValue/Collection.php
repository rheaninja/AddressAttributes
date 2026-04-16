<?php
namespace Ninja\AddressAttributes\Model\ResourceModel\AttributeValue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ninja\AddressAttributes\Model\AttributeValue as Model;
use Ninja\AddressAttributes\Model\ResourceModel\AttributeValue as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
