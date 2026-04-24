<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Controller\Adminhtml\Attribute;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Ninja\AddressAttributes\Api\AttributeRepositoryInterface;
use Ninja\AddressAttributes\Model\AttributeFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\App\ResourceConnection;

class Save extends Action implements HttpPostActionInterface
{
    protected $dataPersistor;
    protected $attributeFactory;
    protected $attributeRepository;
    private ResourceConnection $resourceConnection;

    public function __construct(
        Context $context,
        Registry $coreRegistry,
        DataPersistorInterface $dataPersistor,
        AttributeFactory $attributeFactory,
        AttributeRepositoryInterface $attributeRepository,
        ResourceConnection $resourceConnection
    ) {
        $this->dataPersistor       = $dataPersistor;
        $this->attributeFactory    = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        $this->resourceConnection  = $resourceConnection;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data           = $this->getRequest()->getPostValue();

        if ($data) {
            if (empty($data['attribute_id'])) {
                $data['attribute_id'] = null;
            }

            $model = $this->attributeFactory->create();
            $id    = $this->getRequest()->getParam('attribute_id');

            if ($id) {
                try {
                    $model = $this->attributeRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This attribute no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            // ✅ Extract store_ids before setData to handle separately
            $storeIds = $data['store_ids'] ?? [];
            unset($data['store_ids']); // don't save to main attribute table

            $model->setData($data);

            try {
                $this->attributeRepository->save($model);

                // ✅ Save store associations
                $this->saveAttributeStores((int)$model->getId(), $storeIds);

                $this->messageManager->addSuccessMessage(__('You saved the attribute.'));
                $this->dataPersistor->clear('address_attribute');
                return $resultRedirect->setPath('*/*/');

            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the attribute.')
                );
            }

            $this->dataPersistor->set('address_attribute', $data);
            return $resultRedirect->setPath('*/*/edit', ['attribute_id' => $id]);
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function saveAttributeStores(int $attributeId, mixed $storeIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('ninja_address_attribute_store');

        // Delete existing store associations
        $connection->delete($table, ['attribute_id = ?' => $attributeId]);

        // Normalize store_ids — could be array or comma string
        if (is_string($storeIds)) {
            $storeIds = explode(',', $storeIds);
        }

        $storeIds = array_values(array_filter(
            array_map('intval', (array)$storeIds)
        ));

        // ✅ If empty or contains store_id 0 = all stores — save nothing
        // (no rows = show on all stores)
        if (empty($storeIds) || in_array(0, $storeIds)) {
            return;
        }

        $rows = array_map(fn($storeId) => [
            'attribute_id' => $attributeId,
            'store_id'     => $storeId,
        ], $storeIds);

        $connection->insertMultiple($table, $rows);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Ninja_AddressAttributes::attributes');
    }
}