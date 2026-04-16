<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;

class SaveAdminOrderAddressDynamicValues implements ObserverInterface
{
    private RequestInterface $request;
    private DynamicFieldStorage $dynamicFieldStorage;

    public function __construct(
        RequestInterface $request,
        DynamicFieldStorage $dynamicFieldStorage
    ) {
        $this->request = $request;
        $this->dynamicFieldStorage = $dynamicFieldStorage;
    }

    public function execute(Observer $observer): void
    {
        $addressId = (int)$this->request->getParam('address_id');
        if ($addressId <= 0) {
            return;
        }

        $post = $this->request->getPostValue();
        if (!is_array($post) || !$post) {
            return;
        }

        $this->dynamicFieldStorage->saveOrderValuesByCode($addressId, $post);
    }
}
