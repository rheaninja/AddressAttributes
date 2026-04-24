<?php
// app/code/Ninja/AddressAttributes/Controller/Adminhtml/Order/Save.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory as AttributeCollectionFactory;
use Ninja\AddressAttributes\Model\AttributeValueFactory;
use Ninja\AddressAttributes\Model\ResourceModel\AttributeValue as AttributeValueResource;
use Ninja\AddressAttributes\Model\ResourceModel\AttributeValue\CollectionFactory as ValueCollectionFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    public function __construct(
        Context $context,
        private OrderRepositoryInterface $orderRepository,
        private AttributeCollectionFactory $attributeCollectionFactory,
        private AttributeValueFactory $attributeValueFactory,
        private AttributeValueResource $attributeValueResource,
        private ValueCollectionFactory $valueCollectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId    = (int)$this->getRequest()->getParam('order_id');
        $postData   = $this->getRequest()->getPost('attributes', []);
        $redirect   = $this->resultRedirectFactory->create();

        try {
            $order           = $this->orderRepository->get($orderId);
            $shippingAddress = $order->getShippingAddress();

            if (!$shippingAddress) {
                throw new \Exception('No shipping address found.');
            }

            $addressId  = (int)$shippingAddress->getId();
            $attributes = $this->attributeCollectionFactory->create();

            // Load existing values
            $existingValues = $this->valueCollectionFactory->create()
                ->addFieldToFilter('parent_id', $addressId);

            $existingMap = [];
            foreach ($existingValues as $existing) {
                $existingMap[(int)$existing->getAttributeId()] = $existing;
            }

            foreach ($attributes as $attribute) {
                $attributeId = (int)$attribute->getId();
                $code        = $attribute->getAttributeCode();
                $value       = $postData[$code] ?? null;

                // Normalize checkbox array to comma string
                if (is_array($value)) {
                    $value = implode(',', array_filter($value));
                }

                if (isset($existingMap[$attributeId])) {
                    // Update existing
                    $valueModel = $existingMap[$attributeId];
                    $valueModel->setValue($value);
                } else {
                    // Create new
                    $valueModel = $this->attributeValueFactory->create();
                    $valueModel->setParentId($addressId);
                    $valueModel->setAttributeId($attributeId);
                    $valueModel->setValue($value);
                }

                $this->attributeValueResource->save($valueModel);

                // Also update on address directly
                $shippingAddress->setData($code, $value);
            }

            $shippingAddress->save();

            $this->messageManager->addSuccessMessage(__('Order attributes saved successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}