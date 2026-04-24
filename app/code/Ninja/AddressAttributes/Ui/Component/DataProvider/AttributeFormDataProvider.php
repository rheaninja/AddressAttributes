<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;

class AttributeFormDataProvider extends AbstractDataProvider
{
    protected $request;
    protected $loadedData;
    private ResourceConnection $resourceConnection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = []
    ) {
        $this->collection        = $collectionFactory->create();
        $this->request           = $request;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        $id = $this->request->getParam($this->getRequestFieldName());

        if ($id) {
            $this->collection->addFieldToFilter('attribute_id', $id);
            $items = $this->collection->getItems();

            foreach ($items as $item) {
                $data = $item->getData();

                // Decode options JSON
                if (isset($data['options']) && is_string($data['options']) && $data['options'] !== '') {
                    $decoded = json_decode($data['options'], true);
                    if (is_array($decoded)) {
                        $data['options'] = $decoded;
                    }
                }

                // ✅ Load store IDs for this attribute
                $data['store_ids'] = $this->getStoreIds((int)$item->getId());

                $this->loadedData[$item->getId()] = $data;
            }
        }

        return $this->loadedData;
    }

    private function getStoreIds(int $attributeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('ninja_address_attribute_store');

        return $connection->fetchCol(
            $connection->select()
                ->from($table, ['store_id'])
                ->where('attribute_id = ?', $attributeId)
        );
    }
}