<?php
/**
 * Copyright © Mucan All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ninja\AddressAttributes\Plugin;

use Magento\Quote\Model\ShippingAddressManagement;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Ninja\AddressAttributes\Model\DynamicFieldStorage;
use Psr\Log\LoggerInterface as Logger;

/**
 * Class SaveDynamicAttribute
 */
class SaveDynamicAttribute
{
    /**
     * @var Logger
     */
    protected Logger $logger;
    private DynamicFieldStorage $dynamicFieldStorage;
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger,
        DynamicFieldStorage $dynamicFieldStorage,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->logger = $logger;
        $this->dynamicFieldStorage = $dynamicFieldStorage;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param ShippingAddressManagement $subject
     * @param $cartId
     * @param AddressInterface $address
     * @return void
     */
    public function beforeAssign(
        ShippingAddressManagement $subject,
                                  $cartId,
        AddressInterface          $address
    )
    {
        $quoteId = $this->resolveQuoteId($cartId);
        if ($quoteId <= 0) {
            $this->logger->warning('Unable to resolve quote id for dynamic address attributes.', [
                'cart_id' => $cartId,
            ]);
            return;
        }

        $dynamicFields = [];
        $ext = $address->getExtensionAttributes();
        if ($ext && $ext->getDynamicFields()) {
            $dynamicFields = $ext->getDynamicFields();
        }

        if (!$dynamicFields) {
            $customAttributes = $address->getCustomAttributes();
            if (is_array($customAttributes) && $customAttributes) {
                foreach ($customAttributes as $code => $attribute) {
                    if (is_object($attribute) && method_exists($attribute, 'getAttributeCode')) {
                        $dynamicFields[(string)$attribute->getAttributeCode()] = $attribute->getValue();
                    } else {
                        $dynamicFields[(string)$code] = is_array($attribute) && isset($attribute['value'])
                            ? $attribute['value']
                            : $attribute;
                    }
                }
            }
        }

        if ($dynamicFields) {

            $dynamicFields = $this->normalizeDynamicFields($dynamicFields);
            if (!$dynamicFields) {
                $this->logger->warning('Invalid dynamic_fields payload received for shipping address.');
                return;
            }

            foreach ($dynamicFields as $code => $value) {
                if (!is_scalar($value) && $value !== null) {
                    continue;
                }

                $address->setData((string)$code, $value);
            }

            $this->dynamicFieldStorage->saveQuoteValues($quoteId, $dynamicFields);
        }

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
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $result[(string)$code] = $value;
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
}
