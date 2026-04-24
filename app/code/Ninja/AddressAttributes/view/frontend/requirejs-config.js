var config = {
    map: {
        '*': {
            'Ninja_AddressAttributes/js/form/element/radio-set':
                'Ninja_AddressAttributes/js/form/element/radio-set',

            'Ninja_AddressAttributes/js/form/element/checkbox-set':
                'Ninja_AddressAttributes/js/form/element/checkbox-set'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'Ninja_AddressAttributes/js/model/shipping-save-processor/payload-extender-mixin': true
            },
            'Magento_Ui/js/form/element/date': {
                'Ninja_AddressAttributes/js/date-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Ninja_AddressAttributes/js/action/place-order-mixin': true
            }
        }
    }
};

