define([
    'jquery',
    'jquery-ui-modules/datepicker'
], function ($) {
    'use strict';

    return function () {
        $(document).on('focus', '.hasDatepicker', function () {
            var input = $(this);

            setTimeout(function () {
                var offset = input.offset();
                var height = input.outerHeight();
                var dp = $('#ui-datepicker-div');

                dp.css({
                    top: offset.top + height,
                    left: offset.left
                });
            }, 0);
        });
    };
});