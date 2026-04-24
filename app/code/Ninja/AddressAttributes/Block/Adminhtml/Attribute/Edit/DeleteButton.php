<?php
declare(strict_types=1);
namespace Ninja\AddressAttributes\Block\Adminhtml\Attribute\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class DeleteButton implements ButtonProviderInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    public function __construct(RequestInterface $request, UrlInterface $urlBuilder)
    {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
    }

    public function getButtonData()
    {
        $data = [];
        $attributeId = $this->request->getParam('attribute_id');
        if ($attributeId) {
            $data = [
                'label' => __('Delete Attribute'),
                'class' => 'delete',
                'on_click' => 'deleteConfirm(\'' . __('Are you sure you want to delete this attribute?') . '\', \'' . $this->getDeleteUrl() . '\')',
                'sort_order' => 20,
            ];
        }
        return $data;
    }

    public function getDeleteUrl()
    {
        return $this->urlBuilder->getUrl('addressattributes/attribute/delete', ['attribute_id' => $this->request->getParam('attribute_id')]);
    }
}
