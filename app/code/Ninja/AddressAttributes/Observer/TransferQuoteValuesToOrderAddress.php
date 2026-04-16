<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;
use Psr\Log\LoggerInterface;

class TransferQuoteValuesToOrderAddress implements ObserverInterface
{
    private DynamicFieldStorage $dynamicFieldStorage;
    private LoggerInterface $logger;

    public function __construct(
        DynamicFieldStorage $dynamicFieldStorage,
        LoggerInterface $logger
    ) {
        $this->dynamicFieldStorage = $dynamicFieldStorage;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $quoteId = (int)$order->getQuoteId();
        if ($quoteId <= 0) {
            $quote = $observer->getEvent()->getQuote();
            if ($quote && method_exists($quote, 'getId')) {
                $quoteId = (int)$quote->getId();
            }
        }
        if ($quoteId <= 0) {
            return;
        }

        $orderAddressId = 0;
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getEntityId()) {
            $orderAddressId = (int)$shippingAddress->getEntityId();
        } elseif ($order->getBillingAddress() && $order->getBillingAddress()->getEntityId()) {
            // Virtual orders do not have shipping address.
            $orderAddressId = (int)$order->getBillingAddress()->getEntityId();
        }

        if ($orderAddressId <= 0) {
            // Important: do NOT clear quote values if we couldn't locate order address id yet.
            // This can happen depending on which event fired first / order persistence timing.
            $this->logger->warning('Dynamic address attributes not transferred: missing order address id.', [
                'order_id' => (int)$order->getEntityId(),
                'quote_id' => $quoteId,
            ]);
            return;
        }

        $this->dynamicFieldStorage->transferQuoteValuesToOrderAddress($quoteId, $orderAddressId);
        $this->dynamicFieldStorage->clearQuoteValues($quoteId);
    }
}
