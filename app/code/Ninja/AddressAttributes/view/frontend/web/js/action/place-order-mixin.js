define([
    'mage/utils/wrapper',
    'jquery',
    'Magento_Ui/js/model/messageList'
], function (wrapper, $, globalMessageList) {
    'use strict';

    var PAYMENT_POSITIONS = [
        'before_payment_method',
        'after_payment_method'
    ];

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

    function getProvider() {
        try {
            var registry = window.require('uiRegistry');
            return registry ? registry.get('checkoutProvider') : null;
        } catch (e) {
            console.error('[NinjaAttrs] getProvider error:', e);
            return null;
        }
    }

    function getFieldValue(provider, code, position) {
        var isPayment = PAYMENT_POSITIONS.indexOf(position) !== -1;
        var scope     = isPayment
            ? 'customAttributes'
            : 'shippingAddress.custom_attributes';

        try {
            var scopeData = provider.get(scope) || {};
            var raw       = scopeData[code];

            console.log('[NinjaAttrs] getFieldValue code=' + code + ' scope=' + scope + ' raw=', raw);

            if (raw === undefined || raw === null) {
                return '';
            }
            if (typeof raw === 'object' && 'value' in raw) {
                return raw.value != null ? String(raw.value) : '';
            }
            return String(raw);
        } catch (e) {
            console.error('[NinjaAttrs] getFieldValue error:', e);
            return '';
        }
    }

    function validateCustomLengths() {
        var errors           = [];
        var validationConfig = (window.checkoutConfig || {}).dynamicFieldValidation || {};

        // ── DEBUG ─────────────────────────────────────────────────────────
        console.log('[NinjaAttrs] validateCustomLengths FIRED');
        console.log('[NinjaAttrs] dynamicFieldValidation:', JSON.stringify(validationConfig));
        console.log('[NinjaAttrs] dynamicFieldTypes:', JSON.stringify((window.checkoutConfig || {}).dynamicFieldTypes));

        if (!Object.keys(validationConfig).length) {
            console.warn('[NinjaAttrs] No validation config found — skipping validation');
            return true;
        }

        var provider = getProvider();
        console.log('[NinjaAttrs] provider found:', !!provider);

        if (!provider) {
            return true;
        }

        // ── Log full provider state ───────────────────────────────────────
        try {
            console.log('[NinjaAttrs] provider customAttributes:', JSON.stringify(provider.get('customAttributes')));
            console.log('[NinjaAttrs] provider shippingAddress.custom_attributes:', JSON.stringify(provider.get('shippingAddress.custom_attributes')));
        } catch(e) {}

        Object.keys(validationConfig).forEach(function (code) {
            var config   = validationConfig[code];
            var min      = parseInt(config.min, 10) || 0;
            var max      = parseInt(config.max, 10) || 0;
            var label    = config.label || code;
            var position = config.position || 'after_shipping_address';

            console.log('[NinjaAttrs] checking field: code=' + code + ' min=' + min + ' max=' + max + ' position=' + position);

            if (!min && !max) {
                console.warn('[NinjaAttrs] skipping ' + code + ' — no min/max');
                return;
            }

            var value   = getFieldValue(provider, code, position);
            var trimmed = $.trim(value);

            console.log('[NinjaAttrs] field=' + code + ' value="' + trimmed + '" length=' + trimmed.length + ' min=' + min + ' max=' + max);

            if (min > 0 && trimmed.length < min) {
                console.log('[NinjaAttrs] FAIL min for ' + code);
                errors.push(
                    $.mage.__('Please enter at least %1 characters for "%2".')
                        .replace('%1', min)
                        .replace('%2', label)
                );
            }

            if (max > 0 && trimmed.length > max) {
                console.log('[NinjaAttrs] FAIL max for ' + code);
                errors.push(
                    $.mage.__('Please enter no more than %1 characters for "%2".')
                        .replace('%1', max)
                        .replace('%2', label)
                );
            }
        });

        console.log('[NinjaAttrs] validation errors:', errors);

        if (errors.length) {
            errors.forEach(function (msg) {
                globalMessageList.addErrorMessage({ message: msg });
            });
            $('html, body').animate({ scrollTop: 0 }, 300);
            return false;
        }

        return true;
    }

    function getPaymentAreaFields() {
        var result   = {};
        var provider = getProvider();
        if (!provider) return result;

        try {
            var scopeData = provider.get('customAttributes') || {};
            Object.keys(scopeData).forEach(function (code) {
                var value = scopeData[code];
                if (value && typeof value === 'object' && 'value' in value) {
                    value = value.value;
                }
                result[code] = normalizeValue(value, code);
            });
        } catch (e) {
            console.error('[NinjaAttrs] getPaymentAreaFields error:', e);
        }

        return result;
    }

    return function (placeOrderAction) {
        console.log('[NinjaAttrs] place-order-mixin LOADED — wrapping placeOrderAction');

        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            console.log('[NinjaAttrs] place-order-mixin TRIGGERED');

            if (!validateCustomLengths()) {
                console.log('[NinjaAttrs] validation FAILED — blocking place order');
                var deferred = $.Deferred();
                deferred.reject('Ninja validation failed');
                return deferred.promise();
            }

            console.log('[NinjaAttrs] validation PASSED — proceeding');

            try {
                var paymentFields = getPaymentAreaFields();
                if (Object.keys(paymentFields).length && paymentData) {
                    if (!paymentData.additional_data) {
                        paymentData.additional_data = {};
                    }
                    Object.keys(paymentFields).forEach(function (code) {
                        paymentData.additional_data['ninja_df_' + code] = String(
                            paymentFields[code] != null ? paymentFields[code] : ''
                        );
                    });
                }
            } catch (e) {
                console.error('[NinjaAttrs] place-order-mixin error:', e);
            }

            return originalAction(paymentData, messageContainer);
        });
    };
});