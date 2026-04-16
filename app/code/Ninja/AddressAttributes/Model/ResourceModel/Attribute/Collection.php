<?php
namespace Ninja\AddressAttributes\Model\ResourceModel\Attribute;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ninja\AddressAttributes\Model\Attribute as Model;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
