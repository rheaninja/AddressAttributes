<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Controller\Adminhtml\Attribute;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Ninja\AddressAttributes\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Delete Attribute action.
 */
class Delete extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @param Context $context
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Context $context,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->attributeRepository = $attributeRepository;
        parent::__construct($context);
    }

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        
        $id = $this->getRequest()->getParam('attribute_id');
        if ($id) {
            try {
                $this->attributeRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the attribute.'));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the attribute.'));
            }
        }
        
        return $resultRedirect->setPath('addressattributes/attribute/index');
    }

    /**
     * ACL check
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Ninja_AddressAttributes::attributes');
    }
}
