<?php
// app/code/Ninja/AddressAttributes/Controller/Adminhtml/Order/Edit.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Registry;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    public function __construct(
        Context $context,
        private PageFactory $pageFactory,
        private OrderRepositoryInterface $orderRepository,
        private Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');

        try {
            $order = $this->orderRepository->get($orderId);
            $this->registry->register('current_order', $order);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->resultRedirectFactory->create()->setPath('sales/order/index');
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(
            __('Edit Attributes For The Order #%1', $order->getIncrementId())
        );

        return $resultPage;
    }
}