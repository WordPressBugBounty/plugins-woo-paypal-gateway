(function ($) {
    'use strict';
    $(function () {
        $('#woocommerce_wpg_paypal_checkout_sandbox').change(function () {
            var ppcp_production_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_live, #woocommerce_wpg_paypal_checkout_rest_secret_id_live').closest('tr');
            var ppcp_sandbox_fields = $('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox, #woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').closest('tr');
            if ($(this).is(':checked')) {
                ppcp_sandbox_fields.show();
                ppcp_production_fields.hide();
                $('#woocommerce_wpg_paypal_checkout_sandbox_api_details').show();
                $('#woocommerce_wpg_paypal_checkout_live_api_details').hide();
            } else {
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
        const toggleVisibility = (selector, condition) => {
            $('p.submit').show();
            const element = $(selector).closest('tr');
            if (condition) {
                element.show();
            } else {
                element.hide();
            }
        };
        const isMessagingEnabled = () => $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked');
        const isPageEnabled = (pageType) => isMessagingEnabled() && $.inArray(pageType, $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val()) !== -1;
        const hideShowShortcode = (checkboxSelector, previewSelector, isPageEnabledCallback) => {
            $(checkboxSelector).change(function () {
                toggleVisibility(previewSelector, $(this).is(':checked') && isPageEnabledCallback());
            });
        };
        const handleLayoutTypeChange = (layoutSelector, textFields, flexFields, isPageEnabledCallback, sectionId) => {
            $(layoutSelector).change(function () {
                const showTextFields = this.value === 'text' && isPageEnabledCallback();
                const showFlexFields = this.value !== 'text' && isPageEnabledCallback();
                toggleVisibility(textFields, showTextFields);
                toggleVisibility(flexFields, showFlexFields);
                if (sectionId) {
                    const section = $(`#${sectionId}`);
                    if (showTextFields || showFlexFields) {
                        section.show();
                    } else {
                        section.hide();
                    }
                }
            });
        };
        const handleLogoTypeChange = (logoTypeSelector, positionSelector, layoutTypeSelector, isPageEnabledCallback) => {
            $(logoTypeSelector).change(function () {
                const condition = $(layoutTypeSelector).val() === 'text' && (this.value === 'primary' || this.value === 'alternative');
                toggleVisibility(positionSelector, condition && isPageEnabledCallback());
            });
        };
        const setupCopyText = (cssClass) => {
            $(document.body)
                    .on('click', cssClass, function (evt) {
                        evt.preventDefault();
                        wcClearClipboard();
                        wcSetClipboard($.trim($(this).prev('input').val()), $(cssClass));
                    })
                    .on('aftercopy', cssClass, function () {
                        $(cssClass).tipTip({
                            'attribute': 'data-tip',
                            'activation': 'focus',
                            'fadeIn': 50,
                            'fadeOut': 50,
                            'delay': 0
                        }).focus();
                    });
        };
        const pageTypes = ['home', 'category', 'product', 'cart', 'payment'];
        const hideAllPayLaterFields = () => {
            $('p.submit').show();
            $('.pay_later_messaging_field').closest('tr').hide();
            pageTypes.forEach((type) => {
                $(`.pay_later_messaging_${type}_field`).closest('tr').hide();
                $(`#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`).hide();
            });
        };
        if ($('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked')) {
            $('.pay_later_messaging_field').closest('tr').show(); // Show Page Type field
            $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').trigger('change');
        } else {
            hideAllPayLaterFields();
        }
        $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').change(function () {
            if ($(this).is(':checked')) {
                $('.pay_later_messaging_field').closest('tr').show(); // Show Page Type field
                $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').trigger('change');
            } else {
                hideAllPayLaterFields();
            }
        });
        $(document.body).on('ppcp_cc_paylater_changed', function () {
            function toggleCheckbox() {
                var $checkbox = $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging');
                if ($checkbox.is(':checked')) {
                    $checkbox.prop('checked', false).trigger('change');
                    setTimeout(function () {
                        $checkbox.prop('checked', true).trigger('change');
                    }, 300);
                } else {
                    $checkbox.prop('checked', true).trigger('change');
                    setTimeout(function () {
                        $checkbox.prop('checked', false).trigger('change');
                    }, 300);
                }
            }
            toggleCheckbox();
        });
        $('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').change(function () {
            pageTypes.forEach((type) => {
                const pageSettingSelector = `#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`;
                if ($.inArray(type, $(this).val()) !== -1 && $('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked')) {
                    $(`.pay_later_messaging_${type}_field`).closest('tr').show();
                    $(pageSettingSelector).show();
                } else {
                    $(`.pay_later_messaging_${type}_field`).closest('tr').hide();
                    $(pageSettingSelector).hide();
                }
            });
        });
        hideShowShortcode('#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_shortcode', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_preview_shortcode', () => isPageEnabled('home'));
        hideShowShortcode('#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_shortcode', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_preview_shortcode', () => isPageEnabled('category'));
        hideShowShortcode('#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_shortcode', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_preview_shortcode', () => isPageEnabled('product'));
        hideShowShortcode('#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_shortcode', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_preview_shortcode', () => isPageEnabled('cart'));
        hideShowShortcode('#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_shortcode', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_preview_shortcode', () => isPageEnabled('payment'));
        handleLayoutTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_layout_type', '.pay_later_messaging_home_text_layout_field', '.pay_later_messaging_home_flex_layout_field', () => isPageEnabled('home'), 'woocommerce_wpg_paypal_checkout_pay_later_messaging_home_page_settings');
        handleLayoutTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_layout_type', '.pay_later_messaging_category_text_layout_field', '.pay_later_messaging_category_flex_layout_field', () => isPageEnabled('category'), 'woocommerce_wpg_paypal_checkout_pay_later_messaging_category_page_settings');
        handleLayoutTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_layout_type', '.pay_later_messaging_product_text_layout_field', '.pay_later_messaging_product_flex_layout_field', () => isPageEnabled('product'), 'woocommerce_wpg_paypal_checkout_pay_later_messaging_product_page_settings');
        handleLayoutTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_layout_type', '.pay_later_messaging_cart_text_layout_field', '.pay_later_messaging_cart_flex_layout_field', () => isPageEnabled('cart'), 'woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_page_settings');
        handleLayoutTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_layout_type', '.pay_later_messaging_payment_text_layout_field', '.pay_later_messaging_payment_flex_layout_field', () => isPageEnabled('payment'), 'woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_page_settings');
        handleLogoTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_text_layout_logo_type', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_text_layout_logo_position', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_home_layout_type', () => isPageEnabled('home'));
        handleLogoTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_text_layout_logo_type', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_text_layout_logo_position', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_category_layout_type', () => isPageEnabled('category'));
        handleLogoTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_text_layout_logo_type', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_text_layout_logo_position', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_product_layout_type', () => isPageEnabled('product'));
        handleLogoTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_text_layout_logo_type', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_text_layout_logo_position', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_cart_layout_type', () => isPageEnabled('cart'));
        handleLogoTypeChange('#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_text_layout_logo_type', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_text_layout_logo_position', '#woocommerce_wpg_paypal_checkout_pay_later_messaging_payment_layout_type', () => isPageEnabled('payment'));
        ['.home_copy_text', '.category_copy_text', '.product_copy_text', '.cart_copy_text', '.payment_copy_text'].forEach(setupCopyText);
        jQuery(document).ready(function ($) {
            $('h3.ppcp-collapsible-section').nextUntil('h3.ppcp-collapsible-section').hide();
            $('h3.ppcp-collapsible-section').on('click', function () {
                if (!$(this).hasClass('active')) {
                    $('h3.ppcp-collapsible-section').removeClass('active').nextUntil('h3.ppcp-collapsible-section').slideUp(1);
                }
                $(this).toggleClass('active').nextUntil('h3.ppcp-collapsible-section').slideToggle(1);
                jQuery(document.body).trigger('ppcp_cc_paylater_changed');

            });
            jQuery(document.body).trigger('ppcp_cc_paylater_changed');
        });
    });
})(jQuery);
