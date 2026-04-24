<?php
// app/code/Ninja/AddressAttributes/Block/Adminhtml/Invoice/View/AdditionalInfo.php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Block\Adminhtml\Invoice\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;

class AdditionalInfo extends Template
{
    public function __construct(
        Context $context,
        private Registry $registry,
        private DynamicFieldStorage $dynamicFieldStorage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getInvoice(): ?\Magento\Sales\Model\Order\Invoice
    {
        return $this->registry->registry('current_invoice');
    }

    public function getAttributeValues(): array
    {
        $invoice = $this->getInvoice();
        if (!$invoice) {
            return [];
        }

        $order           = $invoice->getOrder();
        $shippingAddress = $order->getShippingAddress();
        if (!$shippingAddress) {
            return [];
        }

        $addressId = (int)$shippingAddress->getId();

        // ✅ Only show_in_invoice = 1
        $map    = $this->dynamicFieldStorage->getAttributeMap(['show_in_invoice' => 1]);
        $values = $this->dynamicFieldStorage->getOrderValuesByCode($addressId);

        $result = [];
        foreach ($map as $code => $meta) {
            $rawValue = $values[$code] ?? null;
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $type = (string)($meta['type'] ?? 'text');

            if ($type === 'yesno') {
                $display = $rawValue === '1' ? (string)__('Yes') : (string)__('No');
            } else {
                $display = $this->dynamicFieldStorage->resolveDisplayValue($code, $rawValue);
            }

            $result[] = [
                'label' => $meta['label'],
                'value' => $display,
            ];
        }

        return $result;
    }
}