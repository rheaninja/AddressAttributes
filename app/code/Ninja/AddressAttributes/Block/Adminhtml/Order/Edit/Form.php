<?php
// app/code/Ninja/AddressAttributes/Block/Adminhtml/Order/Edit/Form.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Block\Adminhtml\Order\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;
use Ninja\AddressAttributes\Model\ResourceModel\AttributeValue\CollectionFactory as ValueCollectionFactory;

class Form extends Template
{
    public function __construct(
        Context $context,
        private Registry $registry,
        private AttributeCollectionFactory $attributeCollectionFactory,
        private ValueCollectionFactory $valueCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?\Magento\Sales\Model\Order
    {
        return $this->registry->registry('current_order');
    }

    public function getAttributesWithValues(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) return [];

        $addressId = (int)$shippingAddress->getId();

        $values = $this->valueCollectionFactory->create()
            ->addFieldToFilter('parent_id', $addressId);

        $valueMap = [];
        foreach ($values as $value) {
            $valueMap[(int)$value->getAttributeId()] = $value->getValue();
        }

        $result = [];
        foreach ($this->attributeCollectionFactory->create() as $attribute) {
            $result[] = [
                'attribute'                => $attribute,
                'value'                    => $valueMap[(int)$attribute->getId()] ?? '',
                'show_in_shipping_address' => (bool)$attribute->getData('show_in_shipping_address'),
                'show_in_additional_info'  => (bool)$attribute->getData('show_in_additional_info'),
            ];
        }

        return $result;
    }

    public function getSaveUrl(): string
    {
        $order = $this->getOrder();
        return $this->getUrl(
            'addressattributes/order/save',
            ['order_id' => $order ? $order->getId() : 0]
        );
    }

    public function getBackUrl(): string
    {
        $order = $this->getOrder();
        return $this->getUrl(
            'sales/order/view',
            ['order_id' => $order ? $order->getId() : 0]
        );
    }
}