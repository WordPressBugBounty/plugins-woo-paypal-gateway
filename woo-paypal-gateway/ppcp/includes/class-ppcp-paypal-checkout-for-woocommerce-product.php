<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Request
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Request/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Product extends WC_Form_Handler {

    public static function ppcp_add_to_cart_action($url = null) {
        try {
            if (!isset($_REQUEST['ppcp-add-to-cart']) || !is_numeric(wp_unslash($_REQUEST['ppcp-add-to-cart']))) {
                return;
            }
            wc_nocache_headers();
            $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint(wp_unslash($_REQUEST['ppcp-add-to-cart'])));
            $was_added_to_cart = false;
            $adding_to_cart = wc_get_product($product_id);
            if (!$adding_to_cart) {
                return;
            }
            foreach (WC()->cart->get_cart() as $cart_item) {
                if ((int) $cart_item['product_id'] === $product_id && (empty($_REQUEST['variation_id']) || (!empty($cart_item['variation_id']) && (int) $cart_item['variation_id'] === (int) $_REQUEST['variation_id']))) {
                    return;
                }
            }
            $add_to_cart_handler = apply_filters('woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart);
            if ('variable' === $add_to_cart_handler || 'variation' === $add_to_cart_handler) {
                $was_added_to_cart = self::add_to_cart_handler_variable($product_id);
            } elseif ('grouped' === $add_to_cart_handler) {
                $was_added_to_cart = self::add_to_cart_handler_grouped($product_id);
            } elseif (has_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler)) {
                do_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler, $url);
            } else {
                $was_added_to_cart = self::add_to_cart_handler_simple($product_id);
            }
            wc_clear_notices();
        } catch (Exception $ex) {
            
        }
    }

    private static function add_to_cart_handler_simple($product_id) {
        try {
            $quantity = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount(wp_unslash($_REQUEST['quantity']));
            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

            if ($passed_validation && false !== WC()->cart->add_to_cart($product_id, $quantity)) {
                wc_add_to_cart_message(array($product_id => $quantity), true);
                return true;
            }
            return false;
        } catch (Exception $ex) {
            
        }
    }

    private static function add_to_cart_handler_grouped($product_id) {
        try {
            $was_added_to_cart = false;
            $added_to_cart = array();
            $items = isset($_REQUEST['quantity']) && is_array($_REQUEST['quantity']) ? wp_unslash($_REQUEST['quantity']) : array();
            if (!empty($items)) {
                $quantity_set = false;
                foreach ($items as $item => $quantity) {
                    if ($quantity <= 0) {
                        continue;
                    }
                    $quantity_set = true;
                    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $item, $quantity);
                    remove_action('woocommerce_add_to_cart', array(WC()->cart, 'calculate_totals'), 20, 0);
                    if ($passed_validation && false !== WC()->cart->add_to_cart($item, $quantity)) {
                        $was_added_to_cart = true;
                        $added_to_cart[$item] = $quantity;
                    }
                    add_action('woocommerce_add_to_cart', array(WC()->cart, 'calculate_totals'), 20, 0);
                }
                if (!$was_added_to_cart && !$quantity_set) {
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('Please choose the quantity of items you wish to add to your cart&hellip;', 'woo-paypal-gateway'), 'error');
                    }
                } elseif ($was_added_to_cart) {
                    wc_add_to_cart_message($added_to_cart);
                    WC()->cart->calculate_totals();
                    return true;
                }
            } elseif ($product_id) {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Please choose a product to add to your cart&hellip;', 'woo-paypal-gateway'), 'error');
                }
            }
            return false;
        } catch (Exception $ex) {
            
        }
    }

    private static function add_to_cart_handler_variable($product_id) {
        try {
            $variation_id = empty($_REQUEST['variation_id']) ? '' : absint(wp_unslash($_REQUEST['variation_id']));
            $quantity = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount(wp_unslash($_REQUEST['quantity']));
            $missing_attributes = array();
            $variations = array();
            $adding_to_cart = wc_get_product($product_id);
            if (!$adding_to_cart) {
                return false;
            }
            if ($adding_to_cart->is_type('variation')) {
                $variation_id = $product_id;
                $product_id = $adding_to_cart->get_parent_id();
                $adding_to_cart = wc_get_product($product_id);
                if (!$adding_to_cart) {
                    return false;
                }
            }
            $posted_attributes = array();
            foreach ($adding_to_cart->get_attributes() as $attribute) {
                if (!$attribute['is_variation']) {
                    continue;
                }
                $attribute_key = 'attribute_' . sanitize_title($attribute['name']);
                if (isset($_REQUEST[$attribute_key])) {
                    if ($attribute['is_taxonomy']) {
                        $value = sanitize_title(wp_unslash($_REQUEST[$attribute_key]));
                    } else {
                        $value = html_entity_decode(wc_clean(wp_unslash($_REQUEST[$attribute_key])), ENT_QUOTES, get_bloginfo('charset'));
                    }
                    $posted_attributes[$attribute_key] = $value;
                }
            }
            if (empty($variation_id)) {
                $data_store = WC_Data_Store::load('product');
                $variation_id = $data_store->find_matching_product_variation($adding_to_cart, $posted_attributes);
            }
            if (empty($variation_id)) {
                throw new Exception(__('Please choose product options&hellip;', 'woo-paypal-gateway'));
            }
            $variation_data = wc_get_product_variation_attributes($variation_id);
            foreach ($adding_to_cart->get_attributes() as $attribute) {
                if (!$attribute['is_variation']) {
                    continue;
                }
                $attribute_key = 'attribute_' . sanitize_title($attribute['name']);
                $valid_value = isset($variation_data[$attribute_key]) ? $variation_data[$attribute_key] : '';
                if (isset($posted_attributes[$attribute_key])) {
                    $value = $posted_attributes[$attribute_key];
                    if ($valid_value === $value) {
                        $variations[$attribute_key] = $value;
                    } elseif ('' === $valid_value && in_array($value, $attribute->get_slugs(), true)) {
                        $variations[$attribute_key] = $value;
                    } else {
                        // translators: %s: Product attribute name.
                        throw new Exception(sprintf(__('Invalid value posted for %s', 'woo-paypal-gateway'), wc_attribute_label($attribute['name'])));
                    }
                } elseif ('' === $valid_value) {
                    $missing_attributes[] = wc_attribute_label($attribute['name']);
                }
            }
            if (!empty($missing_attributes)) {
                // translators: %s: Comma-separated list of missing required product attributes.
                throw new Exception(sprintf(_n('%s is a required field', '%s are required fields', count($missing_attributes), 'woo-paypal-gateway'), wc_format_list_of_items($missing_attributes)));
            }
        } catch (Exception $e) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice($e->getMessage(), 'error');
            }
            return false;
        }
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);
        if ($passed_validation && false !== WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations)) {
            wc_add_to_cart_message(array($product_id => $quantity), true);
            return true;
        }
        return false;
    }
}
