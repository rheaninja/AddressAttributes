<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Api;

use Ninja\AddressAttributes\Model\Attribute;
use Magento\Framework\Exception\LocalizedException;

interface AttributeRepositoryInterface
{
    /**
     * Get attribute by id
     *
     * @param int $id
     * @return Attribute
     * @throws LocalizedException
     */
    public function getById($id);

    /**
     * Save attribute
     *
     * @param Attribute $attribute
     * @return Attribute
     * @throws LocalizedException
     */
    public function save(Attribute $attribute);

    /**
     * Delete attribute
     *
     * @param Attribute $attribute
     * @return bool
     * @throws LocalizedException
     */
    public function delete(Attribute $attribute);

    /**
     * Delete attribute by id
     *
     * @param int $id
     * @return bool
     * @throws LocalizedException
     */
    public function deleteById($id);
}
