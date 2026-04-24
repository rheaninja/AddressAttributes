/**
 * Copyright © Ninja. All rights reserved.
 */
define([
    'ko',
    'Magento_Ui/js/form/element/abstract',
    'uiRegistry'
], function (ko, Abstract, registry) {
    'use strict';

    return Abstract.extend({
        defaults: {
            template: 'ui/form/field',
            elementTmpl: 'Ninja_AddressAttributes/form/element/radio-set',
            options: [],
            value: ''
        },

        initObservable: function () {
            this._super();
            this.observe(['options', 'value']);
            return this;
        },

        /**
         * Check whether the given option value is currently selected
         * @param {string} optionValue
         * @return {boolean}
         */
        isChecked: function (optionValue) {
            return this.value() === optionValue;
        },

        /**
         * Handle radio button change
         * @param {string} optionValue
         */
        selectOption: function (optionValue) {
            this.value(optionValue);
        }
    });
});