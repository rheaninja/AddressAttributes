define([
    'ko',
    'Magento_Ui/js/form/element/abstract'
], function (ko, Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            template: 'ui/form/field',
            elementTmpl: 'Ninja_AddressAttributes/form/element/checkbox-set',
            options: [],
            value: []
        },

        initObservable: function () {
            this._super();
            this.observe(['options', 'value']);
            return this;
        },

        /**
         * Check if checkbox is selected
         */
        isChecked: function (optionValue) {
            return this.value().indexOf(optionValue) !== -1;
        },

        /**
         * Toggle checkbox selection
         */
        toggleOption: function (optionValue) {
            let current = this.value();

            if (current.indexOf(optionValue) === -1) {
                current.push(optionValue);
            } else {
                current.splice(current.indexOf(optionValue), 1);
            }

            this.value(current.slice());
        }
    });
});