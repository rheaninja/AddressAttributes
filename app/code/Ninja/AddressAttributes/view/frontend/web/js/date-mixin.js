define([], function () {
    'use strict';

    return function (Date) {
        return Date.extend({

            initConfig: function () {
                this._super();

                // ✅ Inject proper JS function here
                this.options.beforeShow = function (input, inst) {
                    setTimeout(function () {
                        var rect = input.getBoundingClientRect();
                        var dp = jQuery('#ui-datepicker-div');

                        dp.css({
                            top: rect.bottom + window.scrollY + 'px',
                            left: rect.left + window.scrollX + 'px'
                        });
                    }, 0);
                };

                return this;
            }

        });
    };
});