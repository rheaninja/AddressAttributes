<?php
namespace Ninja\AddressAttributes\Ui\Component\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute\CollectionFactory;
use Magento\Framework\App\RequestInterface;

class AttributeFormDataProvider extends AbstractDataProvider
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var array
     */
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Load data for form
     *
     * @return array
     */
    public function getData()
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
                if (isset($data['options']) && is_string($data['options']) && $data['options'] !== '') {
                    $decoded = json_decode($data['options'], true);
                    if (is_array($decoded)) {
                        $data['options'] = $decoded;
                    }
                }
                $this->loadedData[$item->getId()] = $data;
            }
        }

        return $this->loadedData;
    }
}
