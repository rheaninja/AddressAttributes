<?php
namespace Ninja\AddressAttributes\Model;

use Ninja\AddressAttributes\Api\AttributeRepositoryInterface;
use Ninja\AddressAttributes\Model\Attribute;
use Ninja\AddressAttributes\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AttributeRepository implements AttributeRepositoryInterface
{
    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var AttributeResource
     */
    protected $attributeResource;

    /**
     * @param AttributeFactory $attributeFactory
     * @param AttributeResource $attributeResource
     */
    public function __construct(
        AttributeFactory $attributeFactory,
        AttributeResource $attributeResource
    ) {
        $this->attributeFactory = $attributeFactory;
        $this->attributeResource = $attributeResource;
    }

    /**
     * Get attribute by id
     *
     * @param int $id
     * @return Attribute
     * @throws NoSuchEntityException
     */
    public function getById($id)
    {
        $attribute = $this->attributeFactory->create();
        $this->attributeResource->load($attribute, $id);
        
        if (!$attribute->getId()) {
            throw new NoSuchEntityException(__('Attribute with id "%1" does not exist.', $id));
        }
        
        return $attribute;
    }

    /**
     * Save attribute
     *
     * @param Attribute $attribute
     * @return Attribute
     * @throws LocalizedException
     */
    public function save(Attribute $attribute)
    {
        try {
            $this->attributeResource->save($attribute);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to save attribute: %1', $e->getMessage()));
        }
        
        return $attribute;
    }

    /**
     * Delete attribute
     *
     * @param Attribute $attribute
     * @return bool
     * @throws LocalizedException
     */
    public function delete(Attribute $attribute)
    {
        try {
            $this->attributeResource->delete($attribute);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Unable to delete attribute: %1', $e->getMessage()));
        }
        
        return true;
    }

    /**
     * Delete attribute by id
     *
     * @param int $id
     * @return bool
     * @throws LocalizedException
     */
    public function deleteById($id)
    {
        $attribute = $this->getById($id);
        return $this->delete($attribute);
    }
}
