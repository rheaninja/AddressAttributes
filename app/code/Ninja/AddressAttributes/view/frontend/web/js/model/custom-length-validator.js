/**
 * Custom KnockoutJS validator for text length validation
 */
define([
    'ko',
    'jquery',
    'Magento_Ui/js/lib/validation/validator'
], function (ko, $, validator) {
    'use strict';

    // Register the validator rule
    validator.addRule('validate-custom-length', function (value, params) {
        if (!value || value === '') {
            return true; // Let required validation handle empty values
        }

        var minLength = parseInt(params.minLength, 10) || 0;
        var maxLength = parseInt(params.maxLength, 10) || 99999;
        var trimmedValue = $.trim(value);

        if (minLength > 0 && trimmedValue.length < minLength) {
            return false;
        }

        if (maxLength < 99999 && trimmedValue.length > maxLength) {
            return false;
        }

        return true;
    });

    return function () {
        // This is loaded as a mixin for the validator component
    };
});