<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Checkout;

use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;
use Psr\Log\LoggerInterface;

class SaveDynamicAttributeOnPlaceOrderGuest
{
    private const FIELD_PREFIX = 'ninja_df_';

    public function __construct(
        private DynamicFieldStorage $dynamicFieldStorage,
        private LoggerInterface $logger,
        private CartRepositoryInterface $cartRepository,
        private QuoteIdMaskFactory $quoteIdMaskFactory
    ) {}

    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagement $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): void {
        $this->log('=== GUEST place order fired, cartId=' . $cartId . ' ===');
        $quoteId = $this->resolveQuoteId($cartId);
        $this->log('resolved quoteId=' . $quoteId);
        $this->process($quoteId, $paymentMethod);
    }

    private function process(int $quoteId, ?PaymentInterface $paymentMethod): void
    {
        if ($quoteId <= 0) {
            $this->log('ERROR: invalid quoteId');
            return;
        }

        try {
            $paymentFields  = $this->extractFromAdditionalData($paymentMethod);
            $this->log('paymentFields: ' . print_r($paymentFields, true));

            $existingFields = $this->dynamicFieldStorage->getQuoteValues($quoteId) ?: [];
            $this->log('existingFields: ' . print_r($existingFields, true));

            $merged = array_merge($existingFields, $paymentFields);
            $this->log('merged: ' . print_r($merged, true));

            if (empty($merged)) {
                $this->log('WARNING: nothing to save');
                return;
            }

            $this->dynamicFieldStorage->saveQuoteValues($quoteId, $merged);
            $this->log('Saved to DynamicFieldStorage OK');

            $quote           = $this->cartRepository->get($quoteId);
            $shippingAddress = $quote->getShippingAddress();

            if (!$shippingAddress) {
                $this->log('ERROR: no shipping address');
                return;
            }

            foreach ($merged as $code => $value) {
                $shippingAddress->setData((string)$code, $value);
            }

            $shippingAddress->save();
            $this->log('shippingAddress->save() OK');

        } catch (\Throwable $e) {
            $this->log('EXCEPTION: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    private function extractFromAdditionalData(?PaymentInterface $paymentMethod): array
    {
        $fields = [];
        if (!$paymentMethod) return $fields;

        $additionalData = $paymentMethod->getAdditionalData();
        $this->log('raw additionalData: ' . print_r($additionalData, true));

        if (!is_array($additionalData)) return $fields;

        foreach ($additionalData as $key => $value) {
            if (strpos((string)$key, self::FIELD_PREFIX) === 0) {
                $code          = substr((string)$key, strlen(self::FIELD_PREFIX));
                $fields[$code] = $value;
            }
        }

        return $fields;
    }

    private function resolveQuoteId($cartId): int
    {
        if (is_int($cartId) || (is_string($cartId) && ctype_digit($cartId))) {
            return (int)$cartId;
        }
        if (!is_string($cartId) || $cartId === '') return 0;

        $mask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return (int)$mask->getQuoteId();
    }

    private function log(string $message): void
    {
        $this->logger->info('[NinjaAddressAttributes] ' . $message);
    }
}