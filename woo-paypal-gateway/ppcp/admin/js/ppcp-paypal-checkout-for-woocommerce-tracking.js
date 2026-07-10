(function ($) {
    'use strict';
    $(function () {
        $('.submit_tracking_info').on('click', function () {
            var capture_id = $('#ppcp-tracking-capture_id').val();
            var tracking_number = $('#ppcp-tracking-tracking_number').val();
            var status = $('#ppcp-tracking-status').val();
            var carrier = $('#ppcp-tracking-carrier').val();
            var order_id = $('#ppcp-tracking-order_id').val();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'submit_tracking_info',
                    nonce: ppcp_tracking_ajax.nonce,
                    capture_id: capture_id,
                    tracking_number: tracking_number,
                    status: status,
                    carrier: carrier,
                    order_id: order_id
                },
                success: function (response) {
                    window.location.reload();
                },
                error: function (error) {
                    console.log(error);
                    alert('There was an error submitting the tracking info.');
                }
            });
        });
    });
})(jQuery);
