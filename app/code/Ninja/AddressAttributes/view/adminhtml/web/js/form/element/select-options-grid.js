/**
 * Copyright © Ninja. All rights reserved.
 */

define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/element/abstract',
    'uiRegistry',
    'mage/translate'
], function ($, ko, Abstract, registry, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Ninja_AddressAttributes/form/element/select-options-grid',
            optionCounter: 0,
            typeField: 'type',
            optionsData: []
        },

        initObservable: function () {
            this._super();
            this.observe(['optionsData']);
            return this;
        },

        /**
         * Initialize the component
         */
        initialize: function () {
            this._super();
            
            // Create observable for attribute type
            this.attributeType = ko.observable('');

            // Normalize initial value (string -> array)
            this.normalizeInitialValue();
            
            // Watch the type field changes
            this.watchTypeField();
            
            return this;
        },

        normalizeInitialValue: function () {
            var current = this.value();
            var options = [];

            if (Array.isArray(current)) {
                options = current;
            } else if (typeof current === 'string') {
                var trimmed = current.trim();
                if (trimmed) {
                    try {
                        var decoded = JSON.parse(trimmed);
                        if (Array.isArray(decoded)) {
                            options = decoded;
                        }
                    } catch (e) {
                        // ignore
                    }

                    if (!options.length) {
                        // Fallback for plain comma/newline separated list
                        var parts = trimmed.split(/[\r\n,]+/);
                        parts.forEach(function (part) {
                            part = (part || '').trim();
                            if (!part) {
                                return;
                            }
                            options.push({ label: part, value: part });
                        });
                    }
                }
            }

            options = this.normalizeOptionsArray(options);
            this.optionsData(options);
            this.updateOptionCounter(options);
            this.syncValueFromOptions();
        },

        normalizeOptionsArray: function (options) {
            if (!Array.isArray(options)) {
                return [];
            }

            var normalized = [];
            options.forEach(function (row, idx) {
                if (!row || typeof row !== 'object') {
                    return;
                }

                var label = (row.label || '').toString().trim();
                var value = (row.value || '').toString().trim();
                var isDefault = row.is_default ? 1 : 0;
                var sortOrder = parseInt(row.sort_order, 10);

                if (!sortOrder || sortOrder < 0) {
                    sortOrder = idx + 1;
                }

                if (!label && !value) {
                    return;
                }
                if (!value) {
                    value = label;
                }
                if (!label) {
                    label = value;
                }

                normalized.push({
                    option_id: row.option_id !== undefined ? row.option_id : idx,
                    label: label,
                    value: value,
                    is_default: isDefault,
                    sort_order: sortOrder
                });
            });

            normalized.sort(function (a, b) {
                return (parseInt(a.sort_order, 10) || 0) - (parseInt(b.sort_order, 10) || 0);
            });

            return normalized;
        },

        syncValueFromOptions: function () {
            var options = this.optionsData() || [];
            try {
                this.value(JSON.stringify(options));
            } catch (e) {
                // ignore
            }
        },

        /**
         * Watch for type field changes
         */
        watchTypeField: function () {
            var self = this;
            var parentName = this.parentName;
            
            // Get the parent form/fieldset
            setTimeout(function () {
                var typeFieldPath = parentName + '.' + self.typeField;
                registry.get(typeFieldPath, function (typeField) {
                    if (typeField) {
                        typeField.value.subscribe(function (newVal) {
                            self.onTypeChange(newVal);
                        });
                        // Initialize with current value
                        self.onTypeChange(typeField.value());
                    }
                });
            }, 500);
        },

        /**
         * Handle type change event
         * @param {string} type - The selected attribute type
         */
        onTypeChange: function (type) {
            this.attributeType(type);
            if (type === 'select' || type === 'radio' || type === 'checkbox') {
                this.visible(true);
                this.disabled(false);
            } else {
                this.visible(false);
                this.disabled(true);
            }
        },

        /**
         * Update the option counter based on existing options
         * @param {array} options - The options array
         */
        updateOptionCounter: function (options) {
            if (options && options.length > 0) {
                var maxId = Math.max.apply(Math, options.map(function (opt) {
                    return parseInt(opt.option_id) || 0;
                }));
                this.optionCounter = maxId + 1;
            } else {
                this.optionCounter = 0;
            }
        },

        /**
         * Add a new option
         */
        addOption: function () {
            var newOption = {
                option_id: this.optionCounter++,
                label: '',
                value: '',
                is_default: 0,
                sort_order: 0
            };
            var currentOptions = this.optionsData() || [];
            if (!Array.isArray(currentOptions)) {
                currentOptions = [];
            }
            newOption.sort_order = currentOptions.length + 1;
            currentOptions = currentOptions.slice(0);
            currentOptions.push(newOption);
            this.updateOptionCounter(currentOptions);
            this.optionsData(currentOptions);
            this.syncValueFromOptions();
        },

        /**
         * Remove an option by index
         * @param {number} index - The index of the option to remove
         */
        removeOption: function (index) {
            var currentOptions = this.optionsData() || [];
            if (Array.isArray(currentOptions) && currentOptions[index]) {
                currentOptions = currentOptions.slice(0);
                currentOptions.splice(index, 1);
                this.updateOptionCounter(currentOptions);
                this.optionsData(currentOptions);
                this.syncValueFromOptions();
            }
        },

        /**
         * Update an option field
         * @param {number} index - The index of the option
         * @param {string} field - The field name to update
         * @param {*} value - The new value
         */
        updateOption: function (index, field, value) {
            var currentOptions = this.optionsData() || [];
            if (Array.isArray(currentOptions) && currentOptions[index]) {
                currentOptions = currentOptions.slice(0);
                currentOptions[index] = Object.assign({}, currentOptions[index]);
                currentOptions[index][field] = value;
                this.optionsData(currentOptions);
                this.syncValueFromOptions();
            }
        },

        /**
         * Get all options
         * @return {array} - The options array
         */
        getOptions: function () {
            return this.optionsData() || [];
        },

        /**
         * Check if options grid is visible
         * @return {boolean}
         */
        isOptionsVisible: function () {
            return this.visible() && this.attributeType() && 
                   (this.attributeType() === 'select' || this.attributeType() === 'radio' || this.attributeType() === 'checkbox');
        }
    });
});
