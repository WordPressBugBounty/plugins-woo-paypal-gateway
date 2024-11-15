(function ($) {
    'use strict';
    $(function () {
        $('#woocommerce_wpg_paypal_checkout_sandbox').change(function () {
            var ppcp_production_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_live, #woocommerce_wpg_paypal_checkout_rest_secret_id_live').closest('tr');
            var ppcp_sandbox_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox, #woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').closest('tr');

            if ($(this).val() === 'yes') {
                // Show sandbox fields and hide production fields
                ppcp_sandbox_fields.show();
                ppcp_production_fields.hide();
                $('#woocommerce_wpg_paypal_checkout_sandbox_api_details').show();
                $('#woocommerce_wpg_paypal_checkout_live_api_details').hide();
            } else {
                // Show production fields and hide sandbox fields
                ppcp_sandbox_fields.hide();
                ppcp_production_fields.show();
                $('#woocommerce_wpg_paypal_checkout_sandbox_api_details').hide();
                $('#woocommerce_wpg_paypal_checkout_live_api_details').show();
            }
        }).change();

        $('#woocommerce_wpg_paypal_checkout_show_on_product_page').change(function () {
            if ($(this).is(':checked')) {
                $('.wpg_paypal_checkout_product_button_settings, .ppcp_product_button_settings').closest('tr').show();
            } else {
                $('.wpg_paypal_checkout_product_button_settings, .ppcp_product_button_settings').closest('tr').hide();
            }
        }).change();
        $('#woocommerce_wpg_paypal_checkout_show_on_cart').change(function () {
            if ($(this).is(':checked')) {
                $('.wpg_paypal_checkout_cart_button_settings, .ppcp_cart_button_settings').closest('tr').show();
            } else {
                $('.wpg_paypal_checkout_cart_button_settings, .ppcp_cart_button_settings').closest('tr').hide();
            }
        }).change();
        $('#woocommerce_wpg_paypal_checkout_show_on_checkout_page').change(function () {
            if ($(this).is(':checked')) {
                $('.wpg_paypal_checkout_checkout_button_settings, .ppcp_checkout_button_settings').closest('tr').show();
            } else {
                $('.wpg_paypal_checkout_checkout_button_settings, .ppcp_checkout_button_settings').closest('tr').hide();
            }
        }).change();
        $('#woocommerce_wpg_paypal_checkout_show_on_mini_cart').change(function () {
            if ($(this).is(':checked')) {
                $('.wpg_paypal_checkout_mini_cart_button_settings, .ppcp_mini_cart_button_settings').closest('tr').show();
            } else {
                $('.wpg_paypal_checkout_mini_cart_button_settings, .ppcp_mini_cart_button_settings').closest('tr').hide();
            }
        }).change();
        jQuery('#woocommerce_wpg_paypal_checkout_enable_advanced_card_payments').change(function () {
            if (jQuery(this).is(':checked')) {
                jQuery('#woocommerce_wpg_paypal_checkout_3d_secure_contingency, #woocommerce_wpg_paypal_checkout_disable_cards, #woocommerce_wpg_paypal_checkout_advanced_card_payments_title').closest('tr').show();
            } else {
                jQuery('#woocommerce_wpg_paypal_checkout_3d_secure_contingency, #woocommerce_wpg_paypal_checkout_disable_cards, #woocommerce_wpg_paypal_checkout_advanced_card_payments_title').closest('tr').hide();
            }
        }).change();
     
        
       // Define page types
    const pageTypes = ['home', 'category', 'product', 'cart', 'payment'];

    // Helper function to toggle visibility
    const toggleVisibility = (selector, condition) => {
        const element = $(selector);
        if (condition) {
            element.closest('tr').show(); // Show the closest table row
            element.show(); // Ensure the element itself is visible
        } else {
            element.closest('tr').hide(); // Hide the closest table row
            element.hide(); // Ensure the element itself is hidden
        }
    };

    // Check if messaging is enabled
    const isMessagingEnabled = () => $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked');

    // Check if the current page type is enabled
    const isPageEnabled = (pageType) => {
        const selectedPages = $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val() || [];
        return isMessagingEnabled() && selectedPages.includes(pageType);
    };

    // Hide all Pay Later fields
    const hideAllPayLaterFields = () => {
        $('.pay_later_messaging_field').closest('tr').hide(); // Hide all generic fields
        pageTypes.forEach((type) => {
            $(`.pay_later_messaging_${type}_field`).closest('tr').hide();
            $(`#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`).hide(); // Hide headers
        });
    };

    // Update visibility of page-specific fields and headers
    const updatePageTypeVisibility = () => {
        const selectedPages = $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val() || [];
        pageTypes.forEach((type) => {
            const pageFieldSelector = `.pay_later_messaging_${type}_field`;
            const pageSettingSelector = `#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`;

            if (selectedPages.includes(type) && isMessagingEnabled()) {
                $(pageFieldSelector).closest('tr').show(); // Show the row containing the field
                $(pageSettingSelector).show(); // Show the header
            } else {
                $(pageFieldSelector).closest('tr').hide(); // Hide the row containing the field
                $(pageSettingSelector).hide(); // Hide the header
            }
        });
    };

    // Event listeners for Pay Later Messaging
    const initializePayLaterMessaging = () => {
        $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').change(function () {
            if ($(this).is(':checked')) {
                $('.pay_later_messaging_field').closest('tr').show(); // Show "Page Type" field
                updatePageTypeVisibility();
            } else {
                hideAllPayLaterFields();
            }
        });

        $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').change(function () {
            updatePageTypeVisibility();
        });

        // Initial visibility setup
        if ($('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked')) {
            $('.pay_later_messaging_field').closest('tr').show(); // Show "Page Type" field
            updatePageTypeVisibility();
        } else {
            hideAllPayLaterFields();
        }
    };

    // Collapsible sections functionality
    const initializeCollapsibleSections = () => {
        // Collapse all sections initially
        $('h3.ppcp-collapsible-section').each(function () {
            $(this).nextUntil('h3.ppcp-collapsible-section').hide();
        });

        // Toggle sections on header click
        $('h3.ppcp-collapsible-section').on('click', function () {
            if (!$(this).hasClass('active')) {
                // Collapse other sections
                $('h3.ppcp-collapsible-section')
                    .removeClass('active')
                    .nextUntil('h3.ppcp-collapsible-section')
                    .slideUp(200);
            }
            // Toggle the clicked section
            $(this).toggleClass('active').nextUntil('h3.ppcp-collapsible-section').slideToggle(200);
        });
    };

    // Initialize all functionality on document ready
    $(document).ready(function () {
        initializePayLaterMessaging();
        initializeCollapsibleSections();
    });

       
    });
})(jQuery);
