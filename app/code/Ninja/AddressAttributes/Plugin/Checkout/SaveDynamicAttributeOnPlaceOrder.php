<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Checkout;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;
use Psr\Log\LoggerInterface;

class SaveDynamicAttributeOnPlaceOrder
{
    private const FIELD_PREFIX = 'ninja_df_';

    public function __construct(
        private DynamicFieldStorage $dynamicFieldStorage,
        private LoggerInterface $logger,
        private CartRepositoryInterface $cartRepository
    ) {}

    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): void {
        $this->log('=== LOGGED-IN place order fired, cartId=' . $cartId . ' ===');
        $this->process((int)$cartId, $paymentMethod);
    }

    private function process(int $quoteId, ?PaymentInterface $paymentMethod): void
    {
        $this->log('quoteId=' . $quoteId);

        if ($quoteId <= 0) {
            $this->log('ERROR: invalid quoteId');
            return;
        }

        try {
            // ── 1. Extract ninja_df_* fields from additional_data ─────────
            $paymentFields  = $this->extractFromAdditionalData($paymentMethod);
            $this->log('paymentFields from additional_data: ' . print_r($paymentFields, true));

            // ── 2. Merge with shipping-step fields ────────────────────────
            $existingFields = $this->dynamicFieldStorage->getQuoteValues($quoteId) ?: [];
            $this->log('existingFields from storage: ' . print_r($existingFields, true));

            $merged = array_merge($existingFields, $paymentFields);
            $this->log('merged: ' . print_r($merged, true));

            if (empty($merged)) {
                $this->log('WARNING: nothing to save');
                return;
            }

            // ── 3. Save to storage ────────────────────────────────────────
            $this->dynamicFieldStorage->saveQuoteValues($quoteId, $merged);
            $this->log('Saved to DynamicFieldStorage OK');

            // ── 4. Apply to quote shipping address ────────────────────────
            $quote           = $this->cartRepository->get($quoteId);
            $shippingAddress = $quote->getShippingAddress();

            if (!$shippingAddress) {
                $this->log('ERROR: no shipping address');
                return;
            }

            foreach ($merged as $code => $value) {
                $shippingAddress->setData((string)$code, $value);
                $this->log('setData: ' . $code . '=' . print_r($value, true));
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

    private function log(string $message): void
    {
        $this->logger->info('[NinjaAddressAttributes] ' . $message);
    }
}