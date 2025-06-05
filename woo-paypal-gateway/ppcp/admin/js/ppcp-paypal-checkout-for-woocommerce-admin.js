let onboardingInProgress = false;
window.onboardingCallback = function (authCode, sharedId) {
    if (onboardingInProgress) {
        return;
    }
    onboardingInProgress = true;
    const is_sandbox = document.querySelector('#woocommerce_wpg_paypal_checkout_sandbox');
    window.onbeforeunload = '';
    jQuery('#wpbody').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
    fetch(ppcp_param.wpg_onboarding_endpoint, {
        method: 'POST',
        headers: {
            'content-type': 'application/json'
        },
        body: JSON.stringify({
            authCode: authCode,
            sharedId: sharedId,
            nonce: ppcp_param.wpg_onboarding_endpoint_nonce,
            env: is_sandbox && is_sandbox.value === 'yes' ? 'sandbox' : 'production'
        })
    }).finally(() => {
        onboardingInProgress = false;
    });
};

(function ($) {
    'use strict';
    $(function () {
        var ppcp_production_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_live, #woocommerce_wpg_paypal_checkout_rest_secret_id_live').closest('tr');
        var ppcp_sandbox_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox, #woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').closest('tr');
        $('#woocommerce_wpg_paypal_checkout_sandbox').change(function () {
            ppcp_production_fields.hide();
            ppcp_sandbox_fields.hide();
            $('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').hide();
            $('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').hide();
            $('#wpg_guide').hide();
            if ($(this).val() === 'yes') {
                $('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').hide();
                if (ppcp_param.is_sandbox_connected === 'yes') {
                    $('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').hide();
                    $('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').show();
                } else {
                    $('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').show();
                    $('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').hide();
                }
            } else {
                $('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').hide();
                if (ppcp_param.is_live_connected === 'yes') {
                    $('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').show();
                    $('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').hide();
                } else {
                    $('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').show();
                    $('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').hide();
                }
            }
        }).change();
        $(".wpg_paypal_checkout_gateway_manual_credential_input").on('click', function (e) {
            e.preventDefault();
            if ($('#woocommerce_wpg_paypal_checkout_sandbox').val() === 'yes') {
                ppcp_sandbox_fields.toggle();
                $('#wpg_guide').toggle();
                $('#woocommerce_paypal_smart_checkout_sandbox_api_credentials, #woocommerce_paypal_smart_checkout_sandbox_api_credentials + p').toggle();
            } else {
                ppcp_production_fields.toggle();
                $('#wpg_guide').toggle();
                $('#woocommerce_paypal_smart_checkout_api_credentials, #woocommerce_paypal_smart_checkout_api_credentials + p').toggle();
            }
        });
        $(".button.wpg-ppcp-disconnect").click(function () {
            $(".woocommerce-save-button").prop("disabled", false);
            if ($('#woocommerce_wpg_paypal_checkout_sandbox').val() === 'yes') {
                $('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox').val('');
                $('#woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').val('');
            } else {
                $('#woocommerce_wpg_paypal_checkout_rest_client_id_live').val('');
                $('#woocommerce_wpg_paypal_checkout_rest_secret_id_live').val('');
            }
            $('.woocommerce-save-button').click();
        });
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
        $('#woocommerce_wpg_paypal_checkout_enable_checkout_button_top').change(function () {
            if ($(this).is(':checked')) {
                $('.wpg_paypal_checkout_checkout_button_settings, .ppcp_express_checkout_button_settings').closest('tr').show();
            } else {
                $('.wpg_paypal_checkout_checkout_button_settings, .ppcp_express_checkout_button_settings').closest('tr').hide();
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
                jQuery('#woocommerce_wpg_paypal_checkout_3d_secure_contingency, #woocommerce_wpg_paypal_checkout_disable_cards, #woocommerce_wpg_paypal_checkout_advanced_card_payments_title, #woocommerce_wpg_paypal_checkout_enable_save_card').closest('tr').show();
            } else {
                jQuery('#woocommerce_wpg_paypal_checkout_3d_secure_contingency, #woocommerce_wpg_paypal_checkout_disable_cards, #woocommerce_wpg_paypal_checkout_advanced_card_payments_title, #woocommerce_wpg_paypal_checkout_enable_save_card').closest('tr').hide();
            }
        }).change();
        $('#woocommerce_wpg_paypal_checkout_enabled_google_pay').change(function () {
            if ($(this).is(':checked')) {
                $('#woocommerce_wpg_paypal_checkout_google_pay_pages').closest('tr').show();
            } else {
                $('#woocommerce_wpg_paypal_checkout_google_pay_pages').closest('tr').hide();
            }
        }).change();

        $('#woocommerce_wpg_paypal_checkout_enabled_apple_pay').change(function () {
            if ($(this).is(':checked')) {
                $('#woocommerce_wpg_paypal_checkout_apple_pay_pages').closest('tr').show();
            } else {
                $('#woocommerce_wpg_paypal_checkout_apple_pay_pages').closest('tr').hide();
            }
        }).change();
        const pageTypes = ['home', 'category', 'product', 'cart', 'payment'];
        const toggleVisibility = (selector, condition) => {
            const element = $(selector);
            if (condition) {
                element.closest('tr').show();
                element.closest('tr').closet('table').show();
                element.show();
            } else {
                element.closest('tr').closet('table').hide();
                element.hide();
            }
        };
        const isMessagingEnabled = () => $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked');
        const isPageEnabled = (pageType) => {
            const selectedPages = $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val() || [];
            return isMessagingEnabled() && selectedPages.includes(pageType);
        };
        const hideAllPayLaterFields = () => {
            $('.pay_later_messaging_field').closest('tr').hide(); // Hide all generic fields
            pageTypes.forEach((type) => {
                $(`.pay_later_messaging_${type}_field`).closest('tr').hide();
                $(`.pay_later_messaging_${type}_field`).closest('tr').closest('table').hide();
                $(`#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`).hide(); // Hide headers
            });
        };
        const updatePageTypeVisibility = () => {
            const selectedPages = $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val() || [];
            pageTypes.forEach((type) => {
                const pageFieldSelector = `.pay_later_messaging_${type}_field`;
                const pageSettingSelector = `#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`;

                if (selectedPages.includes(type) && isMessagingEnabled()) {
                    $(pageFieldSelector).closest('tr').show(); // Show the row containing the field
                    $(pageFieldSelector).closest('tr').closest('table').show();
                    $(pageSettingSelector).show(); // Show the header
                } else {
                    $(pageFieldSelector).closest('tr').hide(); // Hide the row containing the field
                    $(pageFieldSelector).closest('tr').closest('table').hide();
                    $(pageSettingSelector).hide(); // Hide the header
                }
            });
        };
        const initializePayLaterMessaging = () => {
            $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').change(function () {
                if ($(this).is(':checked')) {
                    $('.pay_later_messaging_field').closest('tr').show(); // Show "Page Type" field
                    $('.pay_later_messaging_field').closest('tr').closest('table').show();
                    updatePageTypeVisibility();
                } else {
                    hideAllPayLaterFields();
                }
            });
            $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').change(function () {
                updatePageTypeVisibility();
            });
            if ($('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked')) {
                $('.pay_later_messaging_field').closest('tr').show(); // Show "Page Type" field
                $('.pay_later_messaging_field').closest('tr').closest('table').show();
                updatePageTypeVisibility();
            } else {
                hideAllPayLaterFields();
            }
        };
        const initializeCollapsibleSections = () => {
            $('h3.ppcp-collapsible-section').each(function () {
                $(this).nextUntil('h3.ppcp-collapsible-section').hide();
            });
            const firstSection = $('h3.ppcp-collapsible-section').first();
            firstSection.addClass('active');
            firstSection.nextUntil('h3.ppcp-collapsible-section').show();
            $('h3.ppcp-collapsible-section').on('click', function () {
                if (!$(this).hasClass('active')) {
                    $('h3.ppcp-collapsible-section')
                            .removeClass('active')
                            .nextUntil('h3.ppcp-collapsible-section')
                            .slideUp(1);
                }
                $(this).toggleClass('active').next('table').slideToggle(1).find('tr').css('display', 'block');
            });
        };
        function toggleGooglePayPageSettings() {
            const isGooglePayEnabled = $('#woocommerce_wpg_paypal_checkout_enabled_google_pay').is(':checked');
            const selectedPages = $('#woocommerce_wpg_paypal_checkout_google_pay_pages').val() || [];
            const allPages = ['product', 'cart', 'mini_cart', 'express_checkout', 'checkout'];
            allPages.forEach(function (page) {
                const heading = $('#woocommerce_wpg_paypal_checkout_google_pay_' + page + '_page_settings');
                const table = heading.next('table.form-table');
                if (isGooglePayEnabled && selectedPages.includes(page)) {
                    heading.show();
                    table.show();
                } else {
                    heading.hide();
                    table.hide();
                }
            });
            $('#woocommerce_wpg_paypal_checkout_google_pay_pages').closest('tr').toggle(isGooglePayEnabled);
        }
        toggleGooglePayPageSettings();
        $('#woocommerce_wpg_paypal_checkout_google_pay_pages, #woocommerce_wpg_paypal_checkout_enabled_google_pay').on('change', toggleGooglePayPageSettings);
        function toggleApplePayPageSettings() {
            const isApplePayEnabled = $('#woocommerce_wpg_paypal_checkout_enabled_apple_pay').is(':checked');
            const selectedPages = $('#woocommerce_wpg_paypal_checkout_apple_pay_pages').val() || [];
            const allPages = ['product', 'cart', 'mini_cart', 'express_checkout', 'checkout'];
            allPages.forEach(function (page) {
                const heading = $('#woocommerce_wpg_paypal_checkout_apple_pay_' + page + '_page_settings');
                const table = heading.next('table.form-table');
                if (isApplePayEnabled && selectedPages.includes(page)) {
                    heading.show();
                    table.show();
                } else {
                    heading.hide();
                    table.hide();
                }
            });
            $('#woocommerce_wpg_paypal_checkout_apple_pay_pages').closest('tr').toggle(isApplePayEnabled);
        }
        toggleApplePayPageSettings();
        $('#woocommerce_wpg_paypal_checkout_enabled_apple_pay, #woocommerce_wpg_paypal_checkout_apple_pay_pages').on('change', toggleApplePayPageSettings);
        $('table').each(function () {
            if ($(this).find('tbody').length === 0) {
                $(this).hide();
            }
        });
        $(document).ready(function () {
            initializePayLaterMessaging();
            initializeCollapsibleSections();
        });
    });
})(jQuery);
