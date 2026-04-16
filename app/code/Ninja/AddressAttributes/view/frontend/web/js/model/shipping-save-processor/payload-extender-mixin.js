define([], function () {
    'use strict';

    function extractDynamicFieldsFromCustomAttributes(address) {
        var result = {};
        var customAttributes = address.customAttributes || address.custom_attributes || {};

        if (Array.isArray(customAttributes)) {
            customAttributes.forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                var code = item.attribute_code || item.attributeCode || item.code;
                if (!code) {
                    return;
                }

                result[code] = item.value;
            });

            return result;
        }

        if (customAttributes && typeof customAttributes === 'object') {
            Object.keys(customAttributes).forEach(function (code) {
                var value = customAttributes[code];

                if (value && typeof value === 'object' && 'value' in value) {
                    value = value.value;
                }

                result[code] = value;
            });
        }

        return result;
    }

    return function (target) {
        return function (payload) {
            payload = target(payload) || payload;

            if (!payload || !payload.addressInformation || !payload.addressInformation.shipping_address) {
                return payload;
            }

            var shippingAddress = payload.addressInformation.shipping_address;
            var dynamicFields = extractDynamicFieldsFromCustomAttributes(shippingAddress);

            if (!dynamicFields || Object.keys(dynamicFields).length === 0) {
                return payload;
            }

            shippingAddress.extensionAttributes = shippingAddress.extensionAttributes || {};
            shippingAddress.extensionAttributes.dynamic_fields = dynamicFields;

            // Keep underscore variant for compatibility with various converters.
            shippingAddress.extension_attributes = shippingAddress.extension_attributes || {};
            shippingAddress.extension_attributes.dynamic_fields = dynamicFields;

            return payload;
        };
    };
});

