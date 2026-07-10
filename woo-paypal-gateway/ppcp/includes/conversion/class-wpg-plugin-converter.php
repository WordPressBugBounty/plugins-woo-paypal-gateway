<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WPG_Plugin_Converter {

	protected $id = '';
	protected $source_name = '';
	protected $source_gateway_ids = array();
	protected $settings_option_keys = array();
	protected $settings_map = array();
	protected $order_meta_map = array();
	protected $user_meta_map = array();

	abstract public function get_source_name();

	abstract public function get_source_slug();

	public function is_source_installed() {
		foreach ( $this->settings_option_keys as $key ) {
			$option = get_option( $key, false );
			if ( false !== $option ) {
				return true;
			}
		}
		return false;
	}

	public function get_source_settings() {
		$all = array();
		foreach ( $this->settings_option_keys as $key ) {
			$option = get_option( $key, array() );
			if ( ! empty( $option ) && is_array( $option ) ) {
				$all[ $key ] = $option;
			}
		}
		return $all;
	}

	public function get_settings_map() {
		return $this->settings_map;
	}

	public function get_order_meta_map() {
		return $this->order_meta_map;
	}

	public function get_source_gateway_ids() {
		return $this->source_gateway_ids;
	}

	public function get_source_cc_gateway_ids() {
		return array();
	}

	public function preview_settings_migration() {
		$source_settings = $this->get_source_settings();
		$target_settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$changes         = array();

		foreach ( $this->settings_map as $source_key => $mapping ) {
			$source_option = $mapping['source_option'];
			$target_key    = $mapping['target_key'];
			$transform     = isset( $mapping['transform'] ) ? $mapping['transform'] : null;

			if ( ! isset( $source_settings[ $source_option ] ) ) {
				continue;
			}

			$source_option_data = $source_settings[ $source_option ];

			if ( ! isset( $source_option_data[ $source_key ] ) ) {
				continue;
			}

			$source_value = $source_option_data[ $source_key ];

			if ( $transform && is_callable( $transform ) ) {
				$source_value = call_user_func( $transform, $source_value );
			}

			$current_value = isset( $target_settings[ $target_key ] ) ? $target_settings[ $target_key ] : null;

			$changes[] = array(
				'setting'       => $target_key,
				'source_key'    => $source_key,
				'source_value'  => $source_value,
				'current_value' => $current_value,
				'will_change'   => $current_value !== $source_value,
			);
		}

		return $changes;
	}

	public function migrate_settings( $mode = 'merge' ) {
		$source_settings = $this->get_source_settings();
		$target_settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$migrated_count  = 0;

		foreach ( $this->settings_map as $source_key => $mapping ) {
			$source_option = $mapping['source_option'];
			$target_key    = $mapping['target_key'];
			$transform     = isset( $mapping['transform'] ) ? $mapping['transform'] : null;

			if ( ! isset( $source_settings[ $source_option ] ) ) {
				continue;
			}

			$source_option_data = $source_settings[ $source_option ];

			if ( ! isset( $source_option_data[ $source_key ] ) ) {
				continue;
			}

			$source_value = $source_option_data[ $source_key ];

			if ( $transform && is_callable( $transform ) ) {
				$source_value = call_user_func( $transform, $source_value );
			}

			if ( 'merge' === $mode && isset( $target_settings[ $target_key ] ) && '' !== $target_settings[ $target_key ] ) {
				continue;
			}

			$target_settings[ $target_key ] = $source_value;
			$migrated_count++;
		}

		if ( $migrated_count > 0 ) {
			update_option( 'woocommerce_wpg_paypal_checkout_settings', $target_settings );
		}

		return $migrated_count;
	}

	public function count_orders_to_migrate() {
		global $wpdb;

		$gateway_ids = $this->get_source_gateway_ids();
		if ( empty( $gateway_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $gateway_ids ), '%s' ) );

		if ( $this->is_hpos_enabled() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE payment_method IN ($placeholders)",
				...$gateway_ids
			) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_payment_method' AND meta_value IN ($placeholders)",
				...$gateway_ids
			) );
		}

		return (int) $count;
	}

	public function migrate_order_batch( $offset = 0, $batch_size = 50 ) {
		global $wpdb;

		$gateway_ids = $this->get_source_gateway_ids();
		if ( empty( $gateway_ids ) ) {
			return array( 'processed' => 0, 'done' => true );
		}

		$placeholders = implode( ',', array_fill( 0, count( $gateway_ids ), '%s' ) );

		if ( $this->is_hpos_enabled() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wc_orders WHERE payment_method IN ($placeholders) ORDER BY id ASC LIMIT %d",
				...array_merge( $gateway_ids, array( $batch_size ) )
			) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_payment_method' AND meta_value IN ($placeholders) ORDER BY post_id ASC LIMIT %d",
				...array_merge( $gateway_ids, array( $batch_size ) )
			) );
		}

		$processed = 0;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$this->migrate_single_order( $order );
			$processed++;
		}

		return array(
			'processed' => $processed,
			'done'      => count( $order_ids ) < $batch_size,
		);
	}

	protected function migrate_single_order( $order ) {
		$original_method = $order->get_payment_method();
		if ( ! empty( $original_method ) && ! $order->get_meta( '_wpg_original_payment_method', true ) ) {
			$order->update_meta_data( '_wpg_original_payment_method', $original_method );
			$order->update_meta_data( '_wpg_original_payment_method_title', $order->get_payment_method_title() );
		}

		foreach ( $this->order_meta_map as $source_meta => $target_meta ) {
			$value = $order->get_meta( $source_meta, true );
			if ( '' !== $value && false !== $value && ! $order->get_meta( $target_meta, true ) ) {
				$order->update_meta_data( $target_meta, $value );
			}
		}

		$cc_ids = $this->get_source_cc_gateway_ids();
		$target_method = in_array( $original_method, $cc_ids, true ) ? 'wpg_paypal_checkout_cc' : 'wpg_paypal_checkout';
		$order->set_payment_method( $target_method );
		$original_title = $order->get_payment_method_title();
		if ( ! empty( $original_title ) ) {
			$order->set_payment_method_title( $original_title );
		} else {
			$order->set_payment_method_title( __( 'PayPal', 'woo-paypal-gateway' ) );
		}
		$order->save();
	}

	public function migrate_subscriptions() {
		if ( ! class_exists( 'WC_Subscriptions' ) || ! function_exists( 'wcs_get_subscriptions' ) ) {
			return 0;
		}

		$migrated   = 0;
		$batch_size = 50;

		foreach ( $this->source_gateway_ids as $gateway_id ) {
			$page = 1;
			do {
				$subscriptions = wcs_get_subscriptions( array(
					'payment_method'         => $gateway_id,
					'subscription_status'    => array( 'active', 'on-hold', 'pending' ),
					'subscriptions_per_page' => $batch_size,
					'paged'                  => $page,
				) );

				foreach ( $subscriptions as $subscription ) {
					$this->migrate_single_order( $subscription );
					$migrated++;
				}

				$page++;
			} while ( count( $subscriptions ) >= $batch_size );
		}

		return $migrated;
	}

	public function get_runtime_payment_token_key() {
		return '';
	}

	public function get_runtime_customer_id_keys() {
		return array();
	}

	protected function is_hpos_enabled() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}
}
