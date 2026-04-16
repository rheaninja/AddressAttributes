<?php
namespace Ninja\AddressAttributes\Controller\Adminhtml\Attribute;

use Magento\Framework\App\Action\HttpGetActionInterface;

class NewAction extends Edit implements HttpGetActionInterface
{
    /**
     * Create new attribute
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Forward $resultForward */
        $resultForward = $this->resultForwardFactory->create();
        return $resultForward->forward('edit');
    }
}
