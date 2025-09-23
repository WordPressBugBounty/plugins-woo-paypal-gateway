jQuery(document).ready(function ($) {
    $('.wpg-action-button').on('click', function (e) {
        $('.wpg-review-notice').fadeOut();
        e.preventDefault();
        const action = $(this).data('action'); // Get the action (reviewed, later, or never)
        const reviewUrl = "https://wordpress.org/support/plugin/woo-paypal-gateway/reviews/#new-post";
        if (action === 'reviewed') {
            window.open(reviewUrl, '_blank');
        }
        $.post(wpgAjax.ajax_url, {
            action: 'wpg_handle_review_action',
            review_action: action,
            nonce: wpgAjax.nonce
        }, function (response) {
            if (response.success) {
                $('.wpg-review-notice').fadeOut();
            } else {
                console.error(response.data);
            }
        });
    });
});
