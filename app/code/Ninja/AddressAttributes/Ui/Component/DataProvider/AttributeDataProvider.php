<?php
namespace Ninja\AddressAttributes\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;

class AttributeDataProvider extends AbstractDataProvider
{
    /**
     * @var \Ninja\AddressAttributes\Model\ResourceModel\Attribute\Collection
     */
    protected $collection;

    /**
     * Constructor
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data for UI grid
     *
     * @return array
     */
    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        // Debug log to verify data provider is executed when UI requests data
        try {
            $logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
            $logger->debug('AttributeDataProvider::getData called, collection size: ' . $this->getCollection()->getSize());
        } catch (\Exception $e) {
            // ignore logging errors in production
        }

        $items = [];
        foreach ($this->getCollection()->getItems() as $item) {
            $items[] = $item->getData();
        }

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => $items,
        ];
    }

    /**
     * Get collection
     *
     * @return \Ninja\AddressAttributes\Model\ResourceModel\Attribute\Collection
     */
    public function getCollection()
    {
        return $this->collection;
    }
}
