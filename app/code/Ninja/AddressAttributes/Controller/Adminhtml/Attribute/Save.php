<?php
namespace Ninja\AddressAttributes\Controller\Adminhtml\Attribute;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Ninja\AddressAttributes\Api\AttributeRepositoryInterface;
use Ninja\AddressAttributes\Model\AttributeFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

/**
 * Save Attribute action.
 */
class Save extends Action implements HttpPostActionInterface
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param DataPersistorInterface $dataPersistor
     * @param AttributeFactory $attributeFactory
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        DataPersistorInterface $dataPersistor,
        AttributeFactory $attributeFactory,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->attributeFactory = $attributeFactory;
        $this->attributeRepository = $attributeRepository;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        
        if ($data) {
            if (empty($data['attribute_id'])) {
                $data['attribute_id'] = null;
            }

            /** @var \Ninja\AddressAttributes\Model\Attribute $model */
            $model = $this->attributeFactory->create();

            $id = $this->getRequest()->getParam('attribute_id');
            if ($id) {
                try {
                    $model = $this->attributeRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This attribute no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            try {
                $this->attributeRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the attribute.'));
                $this->dataPersistor->clear('address_attribute');
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the attribute.'));
            }

            $this->dataPersistor->set('address_attribute', $data);
            return $resultRedirect->setPath('*/*/edit', ['attribute_id' => $id]);
        }
        
        return $resultRedirect->setPath('*/*/');
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
