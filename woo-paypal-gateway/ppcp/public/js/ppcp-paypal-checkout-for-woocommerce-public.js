jQuery(function ($) {
    'use strict';
    $(function () {
        if (typeof ppcp_manager === 'undefined') {
            return false;
        }
        var selector = ppcp_manager.button_selector;
        if ($('.variations_form').length) {
            $('.variations_form').on('show_variation', function () {
                $(selector).show();
            }).on('hide_variation', function () {
                $(selector).hide();
            });
        }
        $.ppcp_scroll_to_notices = function () {
            var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
            if (!scrollElement.length) {
                scrollElement = $('form.checkout');
            } 
            if(!scrollElement.length) {
                scrollElement = $('form#order_review');
            }
            if ( scrollElement.length ) {
			$( 'html, body' ).animate( {
				scrollTop: ( scrollElement.offset().top - 100 )
			}, 1000 );
		}
            
        };
        var showError = function (error_message) {
            var display_error = $('.woocommerce');
            if (!display_error.length) {
                display_error = $('form.checkout');
            } 
            display_error.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
            display_error.removeClass('processing').unblock();
            display_error.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
            $.ppcp_scroll_to_notices();
        };
        var is_from_checkout = 'checkout' === ppcp_manager.page;
        var is_from_product = 'product' === ppcp_manager.page;
        var is_sale = 'capture' === ppcp_manager.paymentaction;
        var smart_button_render = function () {
            $.each(ppcp_manager.button_selector, function (key, ppcp_button_selector) {
                if (!$(ppcp_button_selector).length || $(ppcp_button_selector).children().length) {
                    return;
                }
                if (typeof paypal === 'undefined') {
                    return false;
                }
                var ppcp_style = {
                    layout: ppcp_manager.style_layout,
                    color: ppcp_manager.style_color,
                    shape: ppcp_manager.style_shape,
                    label: ppcp_manager.style_label
                };
                if (ppcp_manager.style_layout !== 'vertical') {
                    ppcp_style['tagline'] = (ppcp_manager.style_tagline === 'yes') ? true : false;
                }
                paypal.Buttons({
                    style: ppcp_style,
                    createOrder: function (data, actions) {
                        var data;
                        let topCheckoutSelectors = ['#ppcp_checkout_top'];
                        if (is_from_checkout) {
                            data = $(ppcp_button_selector).closest('form').serialize();
                        } else if (is_from_product) {
                            var add_to_cart = $("[name='add-to-cart']").val();
                            $('<input>', {
                                type: 'hidden',
                                name: 'ppcp-add-to-cart',
                                value: add_to_cart
                            }).appendTo('form.cart');
                            data = $('form.cart').serialize();
                        } else {
                            data = $('form.woocommerce-cart-form').serialize();
                        }
                        if( topCheckoutSelectors.indexOf(ppcp_button_selector) > -1 ) {
                            console.log(ppcp_button_selector);
                            data = '';
                        }
                        return fetch(ppcp_manager.create_order_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: data
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            if (typeof data.success !== 'undefined') {
                                var messages = data.data.messages ? data.data.messages : data.data;
                                if ('string' === typeof messages) {
                                    showError('<ul class="woocommerce-error" role="alert">' + messages + '</ul>', $('form'));
                                } else {
                                    var messageItems = messages.map(function (message) {
                                        return '<li>' + message + '</li>';
                                    }).join('');
                                    showError('<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', $('form'));
                                }
                                return null;
                            } else {
                                return data.orderID;
                            }
                        });
                    },
                    onApprove: function (data, actions) {
                        ppcp_show_spinner(ppcp_button_selector);
                        if (is_from_checkout) {
                            $.post(ppcp_manager.cc_capture + "&paypal_order_id=" + data.orderID + "&woocommerce-process-checkout-nonce=" + ppcp_manager.woocommerce_process_checkout, function (data) {
                                window.location.href = data.data.redirect;
                            });
                        } else {
                            actions.redirect(ppcp_manager.checkout_url + '?paypal_order_id=' + data.orderID + '&paypal_payer_id=' + data.payerID + '&from=' + ppcp_manager.page);
                        }
                    },
                    onCancel: function (data, actions) {
                        ppcp_hide_spinner(ppcp_button_selector);
                    },
                    onError: function (err) {
                        console.log(err);
                        ppcp_hide_spinner(ppcp_button_selector);
                    }
                }).render(ppcp_button_selector);

            });
        };
        $('form.checkout').on('checkout_place_order_wpg_paypal_checkout', function (event) {
            if (is_ppcp_selected()) {
                if (is_hosted_field_eligible() === true) {
                    event.preventDefault();
                    if ($('form.checkout').is('.paypal_cc_submiting')) {
                        return false;
                    } else {
                        $('form.checkout').addClass('paypal_cc_submiting');
                        $(document.body).trigger('submit_paypal_cc_form');
                    }
                    return false;
                }
            }
            return true;
        });
        var hosted_button_render = function () {
            if ($('form.checkout').is('.HostedFields')) {
                return false;
            }
            if (typeof paypal === 'undefined') {
                return false;
            }
            $('form.checkout').addClass('HostedFields');
            paypal.HostedFields.render({
                createOrder: function () {
                    if ($('form.checkout').is('.createOrder') === false) {
                        $('form.checkout').addClass('createOrder');
                        var data;
                        if (is_from_checkout) {
                            data = $('form.checkout').serialize();
                        }
                        return fetch(ppcp_manager.create_order_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: data
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            if (typeof data.success !== 'undefined') {
                                var messages = data.data.messages ? data.data.messages : data.data;
                                if ('string' === typeof messages) {
                                    showError('<ul class="woocommerce-error" role="alert">' + messages + '</ul>', $('form'));
                                } else {
                                    var messageItems = messages.map(function (message) {
                                        return '<li>' + message + '</li>';
                                    }).join('');
                                    showError('<ul class="woocommerce-error" role="alert">' + messageItems + '</ul>', $('form'));
                                }
                                return '';
                            } else {
                                return data.orderID;
                            }
                        });
                    }
                },
                onCancel: function (data, actions) {
                    actions.redirect(ppcp_manager.cancel_url);
                },
                onError: function (err) {
                    $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
                },
                styles: {
                    'input': {
                        'font-size': '1.3em'
                    }
                },
                fields: {
                    number: {
                        selector: '#wpg_paypal_checkout-card-number',
                        placeholder: '•••• •••• •••• ••••',
                        addClass: 'input-text wc-credit-card-form-card-number'
                    },
                    cvv: {
                        selector: '#wpg_paypal_checkout-card-cvc',
                        placeholder: 'CVC'
                    },
                    expirationDate: {
                        selector: '#wpg_paypal_checkout-card-expiry',
                        placeholder: 'MM / YY'
                    }
                }
            }).then(function (hf) {
                hf.on('cardTypeChange', function (event) {
                    if (event.cards.length === 1) {
                        $('#wpg_paypal_checkout-card-number').removeClass().addClass(event.cards[0].type.replace("master-card", "mastercard").replace("american-express", "amex").replace("diners-club", "dinersclub").replace("-", ""));
                        $('#wpg_paypal_checkout-card-number').addClass("input-text wc-credit-card-form-card-number hosted-field-braintree braintree-hosted-fields-valid");
                    }
                });
                $(document.body).on('submit_paypal_cc_form', function (event) {
                    event.preventDefault();
                    var state = hf.getState();
                    var contingencies = [];
                    contingencies = [ppcp_manager.threed_secure_contingency];
                    $('form.checkout').addClass('processing').block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: .6
                        }
                    });
                    $.ppcp_scroll_to_notices($('#order_review'));
                    hf.submit({
                        contingencies: contingencies,
                        cardholderName: document.getElementById('billing_first_name').value,
                        billingAddress: {
                            streetAddress: document.getElementById('billing_address_1').value,
                            extendedAddress: document.getElementById('billing_address_2').value,
                            region: document.getElementById('billing_state').value,
                            locality: document.getElementById('billing_city').value,
                            postalCode: document.getElementById('billing_postcode').value,
                            countryCodeAlpha2: document.getElementById('billing_country').value
                        }
                    }).then(
                            function (payload) {
                                if (payload.orderId) {
                                    $.post(ppcp_manager.cc_capture + "&paypal_order_id=" + payload.orderId + "&woocommerce-process-checkout-nonce=" + ppcp_manager.woocommerce_process_checkout, function (data) {
                                        window.location.href = data.data.redirect;
                                    });
                                }
                            }, function (error) {
                        $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
                        $('form.checkout').removeClass('processing paypal_cc_submiting HostedFields createOrder').unblock();
                        var error_message = '';
                        if (Array.isArray(error.details) && error.details[0]['description']) {
                            error_message = error.details[0]['description'];
                        } else if(error.message) {
                            error_message = error.message;
                        }
                        if (Array.isArray(error.details) && error.details[0]['issue'] === 'INVALID_RESOURCE_ID') {
                            error_message = '';
                        }
                        if (error_message !== '') {
                            showError('<ul class="woocommerce-error" role="alert">' + error_message + '</ul>', $('form'));
                        }
                    }
                    );
                });
            }).catch(function (err) {
                console.log('error: ', JSON.stringify(err));
            });
        };

        if (is_from_checkout === false) {
            smart_button_render();
        }
        if (ppcp_manager.is_pay_page === 'yes') {
            smart_button_render();
        }
        var hide_show_place_order_button = function () {
            if (is_hosted_field_eligible() === true) {
                $('#place_order').show();
            } else {
                if (is_ppcp_selected()) {
                    $('#place_order').hide();
                } else {
                    $('#place_order').show();
                }
            }
        };
        $(document.body).on('updated_cart_totals updated_checkout ppcp_block_ready', function () {
            hide_show_place_order_button();
            setTimeout(function () {
                smart_button_render();
                if (is_hosted_field_eligible() === true) {
                    $('.checkout_cc_separator').show();
                    $('#wc-wpg_paypal_checkout-cc-form').show();
                    hosted_button_render();
                } else {
                    $('.checkout_cc_separator').hide();
                    $('#wc-wpg_paypal_checkout-cc-form').hide();
                }
            }, 300);
        });

        $('form.checkout').on('click', 'input[name="payment_method"]', function () {
            hide_show_place_order_button();
        });
        function is_hosted_field_eligible() {
            if (is_from_checkout) {
                if (ppcp_manager.advanced_card_payments === 'yes') {
                    if (typeof paypal === 'undefined') {
                        return false;
                    }
                    if (paypal.HostedFields.isEligible()) {
                        return true;
                    }
                }
            }
            return false;
        }
        function is_ppcp_selected() {
            if ($('#payment_method_wpg_paypal_checkout').is(':checked')) {
                return true;
            } else {
                return false;
            }
        }
        
        function ppcp_show_spinner(ppcp_button_selector) {
            if ($('.woocommerce').length) {
                $('.woocommerce').block({message: null, overlayCSS: {background: '#fff', opacity: .6}});
            } else if($('.wc-block-checkout').length) {
                $('.wc-block-checkout').block({message: null, overlayCSS: {background: '#fff', opacity: .6}});
            } else {
                $(ppcp_button_selector).block({message: null, overlayCSS: {background: '#fff', opacity: .6}});
            }
        }
        
        function ppcp_hide_spinner(ppcp_button_selector) {
            if ($('.woocommerce').length) {
                $('.woocommerce').unblock();
            } else if($('.wc-block-checkout').length) {
                $('.wc-block-checkout').unblock();
            } else {
                $(ppcp_button_selector).unblock();
            }
        }
    });
});