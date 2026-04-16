/**
 * Copyright © Ninja. All rights reserved.
 */

define([
    'jquery',
    'ko',
    'uiRegistry',
    'Magento_Ui/js/form/components/button'
], function ($, ko, registry, Button) {
    'use strict';

    return Button.extend({
        defaults: {
            modalTarget: 'attribute_form.attribute_form.options_modal',
            title: 'Manage Options',
            disabled: false,
            buttonClasses: 'action-primary',
            actions: [{
                targetName: '${ $.modalTarget }',
                actionName: 'openModal'
            }]
        },

        

        /**
         * Open the options modal
         */
        openModal: function () {
            var self = this;
            registry.get(this.modalTarget, function (modal) {
                if (modal) {
                    modal.openModal();
                }
            });
        }
    });
});
