<?php
// app/code/Ninja/AddressAttributes/Model/Attribute/StoreOptions.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Model\Attribute;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store as SystemStore;

class StoreOptions implements OptionSourceInterface
{
    public function __construct(
        private SystemStore $systemStore
    ) {}

    public function toOptionArray(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, true);
    }
}