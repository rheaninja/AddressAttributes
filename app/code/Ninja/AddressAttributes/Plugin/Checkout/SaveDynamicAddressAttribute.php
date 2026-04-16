<?php
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;
use Psr\Log\LoggerInterface;

class SaveDynamicAddressAttribute
{
    private DynamicFieldStorage $dynamicFieldStorage;
    private LoggerInterface $logger;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    public function __construct(
        DynamicFieldStorage $dynamicFieldStorage,
        LoggerInterface $logger,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->dynamicFieldStorage = $dynamicFieldStorage;
        $this->logger = $logger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): void {
        $quoteId = $this->resolveQuoteId($cartId);
        if ($quoteId <= 0) {
            $this->logger->warning('Unable to resolve quote id for dynamic address attributes.', [
                'cart_id' => $cartId,
            ]);
            return;
        }

        $shippingAddress = $addressInformation->getShippingAddress();
        if (!$shippingAddress) {
            return;
        }

        $dynamicFields = [];

        $ext = $shippingAddress->getExtensionAttributes();
        if ($ext && $ext->getDynamicFields()) {
            $dynamicFields = $ext->getDynamicFields();
        }

        if (!$dynamicFields) {
            $rawDynamic = $shippingAddress->getDataByPath('extension_attributes/dynamic_fields');
            if (is_array($rawDynamic) || is_string($rawDynamic)) {
                $dynamicFields = $rawDynamic;
            }
        }

        if (!$dynamicFields) {
            $customAttributes = $shippingAddress->getCustomAttributes();
            if (is_array($customAttributes) && $customAttributes) {
                $dynamicFields = [];
                foreach ($customAttributes as $code => $attribute) {
                    if (is_object($attribute) && method_exists($attribute, 'getAttributeCode')) {
                        $attrCode = (string)$attribute->getAttributeCode();
                        $dynamicFields[$attrCode] = $attribute->getValue();
                        continue;
                    }

                    $dynamicFields[(string)$code] = is_array($attribute) && isset($attribute['value'])
                        ? $attribute['value']
                        : $attribute;
                }
            }
        }

        if (!$dynamicFields) {
            $this->logger->debug('No dynamic address attributes found on shipping address.', [
                'quote_id' => $quoteId,
                'cart_id' => $cartId,
            ]);
            return;
        }

        $dynamicFields = $this->normalizeDynamicFields($dynamicFields);
        if (!$dynamicFields) {
            $this->logger->warning('Invalid dynamic_fields payload received in saveAddressInformation.');
            return;
        }

        foreach ($dynamicFields as $code => $value) {
            if ($value !== null && !is_scalar($value) && !is_array($value)) {
                unset($dynamicFields[$code]);
                continue;
            }

            // Quote address entity is not guaranteed to accept arrays; keep it scalar.
            if (is_array($value)) {
                $shippingAddress->setData((string)$code, json_encode(array_values($value)));
                continue;
            }

            $shippingAddress->setData((string)$code, $value);
        }

        $this->debugToFile($quoteId, $dynamicFields);
        $this->dynamicFieldStorage->saveQuoteValues($quoteId, $dynamicFields);
    }

    /**
     * @param mixed $payload
     * @return array<string, scalar|null>
     */
    private function normalizeDynamicFields($payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($payload)) {
            return [];
        }

        // If we received a list of {attribute_code,value} items, convert to a map.
        $isList = array_is_list($payload);
        if ($isList) {
            $map = [];
            foreach ($payload as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = (string)($row['attribute_code'] ?? $row['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $map[$code] = $row['value'] ?? null;
            }
            $payload = $map;
        }

        $result = [];
        foreach ($payload as $code => $value) {
            if ($code === '') {
                continue;
            }
            if ($value === null || is_scalar($value)) {
                $result[(string)$code] = $value;
                continue;
            }

            if (is_array($value)) {
                $filtered = [];
                foreach ($value as $item) {
                    if ($item === null || is_scalar($item)) {
                        $filtered[] = $item === null ? '' : (string)$item;
                    }
                }
                // Keep empty list as empty string to prevent unexpected nulls in JS.
                $result[(string)$code] = array_values(array_filter($filtered, static fn($v) => $v !== ''));
                continue;
            }
        }

        return $result;
    }

    /**
     * @param mixed $cartId
     */
    private function resolveQuoteId($cartId): int
    {
        if (is_int($cartId) || (is_string($cartId) && ctype_digit($cartId))) {
            return (int)$cartId;
        }

        if (!is_string($cartId) || $cartId === '') {
            return 0;
        }

        $mask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return (int)$mask->getQuoteId();
    }

    /**
     * Best-effort debug log for checkout integration.
     *
     * @param array<string, scalar|null> $dynamicFields
     */
    private function debugToFile(int $quoteId, array $dynamicFields): void
    {
        try {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/addressattribute.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info('dynamic_fields captured for quote_id=' . $quoteId);
            $logger->info('dynamic_fields=' . print_r($dynamicFields, true));
        } catch (\Throwable $e) {
            // Never break checkout due to logging issues.
        }
    }
}
