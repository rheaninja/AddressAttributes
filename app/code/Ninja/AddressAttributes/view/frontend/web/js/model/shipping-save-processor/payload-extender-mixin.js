define(['jquery'], function ($) {
    'use strict';

    function normalizeValue(value, code) {
        if (Array.isArray(value)) {
            return value.join(',');
        }

        if (window.checkoutConfig &&
            window.checkoutConfig.dynamicFieldTypes &&
            window.checkoutConfig.dynamicFieldTypes[code] === 'yesno'
        ) {
            if (value === undefined || value === null || value === '') {
                return '0';
            }
        }
        return value;
    }

    /**
     * Validate custom length for address attributes
     */
    function validateCustomLengths(address) {
        var isValid = true;
        var errorMessages = [];

        try {
            var customAttributes = address.customAttributes || address.custom_attributes || {};

            if (Array.isArray(customAttributes)) {
                customAttributes.forEach(function (item) {
                    if (!item || typeof item !== 'object') {
                        return;
                    }

                    var code = item.attribute_code || item.attributeCode || item.code;
                    var value = item.value;
                    var validation = item.validation || {};

                    if (typeof value === 'string' && value.length > 0) {
                        var minLength = parseInt(validation.min_text_length, 10) || 0;
                        var maxLength = parseInt(validation.max_text_length, 10) || 99999;
                        var trimmedValue = $.trim(value);

                        if (minLength > 0 && trimmedValue.length < minLength) {
                            isValid = false;
                            errorMessages.push($.mage.__('Please enter at least %1 characters for "%2".')
                                .replace('%1', minLength)
                                .replace('%2', code));
                        }

                        if (maxLength < 99999 && trimmedValue.length > maxLength) {
                            isValid = false;
                            errorMessages.push($.mage.__('Please enter no more than %1 characters for "%2".')
                                .replace('%1', maxLength)
                                .replace('%2', code));
                        }
                    }
                });
            } else if (customAttributes && typeof customAttributes === 'object') {
                Object.keys(customAttributes).forEach(function (code) {
                    var fieldData = customAttributes[code];
                    var value = fieldData && typeof fieldData === 'object' ? fieldData.value : fieldData;
                    var validation = fieldData && fieldData.validation ? fieldData.validation : {};

                    if (typeof value === 'string' && value.length > 0) {
                        var minLength = parseInt(validation.min_text_length, 10) || 0;
                        var maxLength = parseInt(validation.max_text_length, 10) || 99999;
                        var trimmedValue = $.trim(value);

                        if (minLength > 0 && trimmedValue.length < minLength) {
                            isValid = false;
                            errorMessages.push($.mage.__('Please enter at least %1 characters for "%2".')
                                .replace('%1', minLength)
                                .replace('%2', code));
                        }

                        if (maxLength < 99999 && trimmedValue.length > maxLength) {
                            isValid = false;
                            errorMessages.push($.mage.__('Please enter no more than %1 characters for "%2".')
                                .replace('%1', maxLength)
                                .replace('%2', code));
                        }
                    }
                });
            }
        } catch (e) {
            console.error('[NinjaAttrs] validateCustomLengths error:', e);
        }

        if (!isValid && errorMessages.length) {
            // Show error messages
            alert(errorMessages.join('\n'));
        }

        return isValid;
    }

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

                var value = item.value;
                result[code] = normalizeValue(value, code);
            });

            return result;
        }

        if (customAttributes && typeof customAttributes === 'object') {
            Object.keys(customAttributes).forEach(function (code) {
                var value = customAttributes[code];

                if (value && typeof value === 'object' && 'value' in value) {
                    value = value.value;
                }

                result[code] = normalizeValue(value, code);
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

            // Validate custom length before proceeding
            if (!validateCustomLengths(shippingAddress)) {
                throw new Error($.mage.__('Please fix the validation errors before proceeding.'));
            }

            var dynamicFields = extractDynamicFieldsFromCustomAttributes(shippingAddress);

            if (shippingAddress.customAttributes || shippingAddress.custom_attributes) {
                var source = shippingAddress.customAttributes || shippingAddress.custom_attributes;

                Object.keys(source).forEach(function (code) {
                    if (dynamicFields[code] === undefined) {
                        dynamicFields[code] = '0';
                    }
                });
            }

            if (!dynamicFields || Object.keys(dynamicFields).length === 0) {
                return payload;
            }

            shippingAddress.extensionAttributes = shippingAddress.extensionAttributes || {};
            shippingAddress.extensionAttributes.dynamic_fields = dynamicFields;

            shippingAddress.extension_attributes = shippingAddress.extension_attributes || {};
            shippingAddress.extension_attributes.dynamic_fields = dynamicFields;

            return payload;
        };
    };
});