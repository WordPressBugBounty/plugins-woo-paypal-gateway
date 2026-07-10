<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_Conversion_Controller {

	private static $instance = null;
	private $converters = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		$this->register_converters();

		if ( get_option( 'wpg_conversion_migration_done', false ) ) {
			$this->register_runtime_hooks();
		}

		if ( is_admin() ) {
			add_action( 'wp_ajax_wpg_conversion_preview', array( $this, 'ajax_preview' ) );
			add_action( 'wp_ajax_wpg_conversion_migrate_settings', array( $this, 'ajax_migrate_settings' ) );
			add_action( 'wp_ajax_wpg_conversion_migrate_orders', array( $this, 'ajax_migrate_orders' ) );
			add_action( 'wp_ajax_wpg_conversion_migrate_subscriptions', array( $this, 'ajax_migrate_subscriptions' ) );
			add_action( 'wp_ajax_wpg_conversion_rollback', array( $this, 'ajax_rollback' ) );
		}
	}

	private function register_converters() {
		$this->converters = array(
			'pymntpl'          => new WPG_Pymntpl_Converter(),
			'wc-paypal'        => new WPG_WC_PayPal_Payments_Converter(),
			'angelleye'        => new WPG_AngellEYE_Converter(),
			'checkout-plugins' => new WPG_Checkout_Plugins_Converter(),
		);

		$this->converters = apply_filters( 'wpg_ppcp_conversion_converters', $this->converters );
	}

	private function register_runtime_hooks() {
		add_filter( 'woocommerce_order_get_payment_method', array( $this, 'runtime_get_payment_method' ), 10, 2 );
		add_filter( 'woocommerce_subscription_get_payment_method', array( $this, 'runtime_get_payment_method' ), 10, 2 );
	}

	public function runtime_get_payment_method( $payment_method, $order ) {
		foreach ( $this->converters as $converter ) {
			$source_ids = $converter->get_source_gateway_ids();
			if ( in_array( $payment_method, $source_ids, true ) ) {
				$cc_ids = $converter->get_source_cc_gateway_ids();
				if ( in_array( $payment_method, $cc_ids, true ) ) {
					return 'wpg_paypal_checkout_cc';
				}
				return 'wpg_paypal_checkout';
			}
		}
		return $payment_method;
	}

	public function get_converters() {
		return $this->converters;
	}

	public function get_detected_plugins() {
		$detected = array();
		foreach ( $this->converters as $key => $converter ) {
			if ( $converter->is_source_installed() ) {
				$detected[ $key ] = $converter;
			}
		}
		return $detected;
	}

	public function get_converter( $key ) {
		return isset( $this->converters[ $key ] ) ? $this->converters[ $key ] : null;
	}

	private function create_backup() {
		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$backup   = array(
			'timestamp' => time(),
			'settings'  => $settings,
		);
		update_option( 'wpg_conversion_backup', $backup, false );
		return true;
	}

	public function ajax_preview() {
		check_ajax_referer( 'wpg_conversion_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-paypal-gateway' ) ) );
		}

		$converter_key = isset( $_POST['converter'] ) ? sanitize_text_field( wp_unslash( $_POST['converter'] ) ) : '';
		$converter     = $this->get_converter( $converter_key );

		if ( ! $converter ) {
			wp_send_json_error( array( 'message' => __( 'Invalid converter.', 'woo-paypal-gateway' ) ) );
		}

		$preview = array(
			'settings'      => $converter->preview_settings_migration(),
			'order_count'   => $converter->count_orders_to_migrate(),
			'source_name'   => $converter->get_source_name(),
		);

		wp_send_json_success( $preview );
	}

	public function ajax_migrate_settings() {
		check_ajax_referer( 'wpg_conversion_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-paypal-gateway' ) ) );
		}

		$converter_key = isset( $_POST['converter'] ) ? sanitize_text_field( wp_unslash( $_POST['converter'] ) ) : '';
		$mode          = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';
		$converter     = $this->get_converter( $converter_key );

		if ( ! $converter ) {
			wp_send_json_error( array( 'message' => __( 'Invalid converter.', 'woo-paypal-gateway' ) ) );
		}

		$this->create_backup();

		$count = $converter->migrate_settings( $mode );

		wp_send_json_success( array(
			'migrated' => $count,
			'message'  => sprintf(
				/* translators: %d: number of settings migrated */
				__( '%d settings migrated successfully.', 'woo-paypal-gateway' ),
				$count
			),
		) );
	}

	public function ajax_migrate_orders() {
		check_ajax_referer( 'wpg_conversion_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-paypal-gateway' ) ) );
		}

		$converter_key = isset( $_POST['converter'] ) ? sanitize_text_field( wp_unslash( $_POST['converter'] ) ) : '';
		$batch_size    = isset( $_POST['batch_size'] ) ? min( absint( $_POST['batch_size'] ), 100 ) : 50;
		$is_first      = isset( $_POST['is_first'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['is_first'] ) );
		$converter     = $this->get_converter( $converter_key );

		if ( ! $converter ) {
			wp_send_json_error( array( 'message' => __( 'Invalid converter.', 'woo-paypal-gateway' ) ) );
		}

		if ( $is_first ) {
			$this->create_backup();
		}

		$result = $converter->migrate_order_batch( 0, $batch_size );

		wp_send_json_success( array(
			'processed'  => $result['processed'],
			'done'       => $result['done'],
		) );
	}

	public function ajax_migrate_subscriptions() {
		check_ajax_referer( 'wpg_conversion_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-paypal-gateway' ) ) );
		}

		$converter_key = isset( $_POST['converter'] ) ? sanitize_text_field( wp_unslash( $_POST['converter'] ) ) : '';
		$converter     = $this->get_converter( $converter_key );

		if ( ! $converter ) {
			wp_send_json_error( array( 'message' => __( 'Invalid converter.', 'woo-paypal-gateway' ) ) );
		}

		$count = $converter->migrate_subscriptions();

		update_option( 'wpg_conversion_migration_done', true, false );

		wp_send_json_success( array(
			'migrated' => $count,
			'message'  => sprintf(
				/* translators: %d: number of subscriptions migrated */
				__( '%d subscriptions migrated.', 'woo-paypal-gateway' ),
				$count
			),
		) );
	}

	public function ajax_rollback() {
		check_ajax_referer( 'wpg_conversion_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-paypal-gateway' ) ) );
		}

		$backup = get_option( 'wpg_conversion_backup', false );

		if ( ! $backup || ! isset( $backup['settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No backup found.', 'woo-paypal-gateway' ) ) );
		}

		update_option( 'woocommerce_wpg_paypal_checkout_settings', $backup['settings'] );

		$this->rollback_migrated_orders();

		delete_option( 'wpg_conversion_migration_done' );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %s: backup date */
				__( 'Settings and orders restored from backup created at %s.', 'woo-paypal-gateway' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $backup['timestamp'] )
			),
		) );
	}

	private function rollback_migrated_orders() {
		global $wpdb;

		$payment_methods = "'wpg_paypal_checkout','wpg_paypal_checkout_cc'";

		do {
		if ( $this->is_hpos_enabled() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_ids = $wpdb->get_col(
				"SELECT id FROM {$wpdb->prefix}wc_orders WHERE payment_method IN ({$payment_methods}) AND id IN (
					SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_wpg_original_payment_method'
				) LIMIT 500"
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order_ids = $wpdb->get_col(
				"SELECT p.post_id FROM {$wpdb->postmeta} p
				INNER JOIN {$wpdb->postmeta} m ON p.post_id = m.post_id AND m.meta_key = '_wpg_original_payment_method'
				WHERE p.meta_key = '_payment_method' AND p.meta_value IN ({$payment_methods})
				LIMIT 500"
			);
		}

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$original_method = $order->get_meta( '_wpg_original_payment_method', true );
			$original_title  = $order->get_meta( '_wpg_original_payment_method_title', true );

			if ( ! empty( $original_method ) ) {
				$order->set_payment_method( $original_method );
				if ( ! empty( $original_title ) ) {
					$order->set_payment_method_title( $original_title );
				}
				$order->delete_meta_data( '_wpg_original_payment_method' );
				$order->delete_meta_data( '_wpg_original_payment_method_title' );
				$order->save();
			}
		}
		} while ( ! empty( $order_ids ) );
	}

	private function is_hpos_enabled() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	public function render_admin_page() {
		$detected = $this->get_detected_plugins();
		$backup   = get_option( 'wpg_conversion_backup', false );
		?>
		<div class="wpg-conversion-tool">
			<h3><?php esc_html_e( 'Migration Tool', 'woo-paypal-gateway' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Migrate from another PayPal plugin to WooPayPal. Your existing settings are backed up before any changes.', 'woo-paypal-gateway' ); ?></p>

			<?php if ( $backup ) : ?>
				<div class="wpg-conversion-backup-notice" style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 15px;margin:15px 0;">
					<strong><?php esc_html_e( 'Backup available', 'woo-paypal-gateway' ); ?></strong>
					<?php
					printf(
						/* translators: %s: backup date */
						esc_html__( ' from %s.', 'woo-paypal-gateway' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $backup['timestamp'] ) )
					);
					?>
					<button type="button" class="button wpg-conversion-rollback" style="margin-left:10px;"><?php esc_html_e( 'Restore Backup', 'woo-paypal-gateway' ); ?></button>
				</div>
			<?php endif; ?>

			<div class="wpg-conversion-plugins" style="margin-top:20px;">
				<h4><?php esc_html_e( 'Select Source Plugin', 'woo-paypal-gateway' ); ?></h4>

				<?php if ( empty( $detected ) ) : ?>
					<p><?php esc_html_e( 'No other PayPal plugins detected. The migration tool works when another PayPal plugin\'s settings are found in the database (the plugin does not need to be active).', 'woo-paypal-gateway' ); ?></p>
					<p class="description"><?php esc_html_e( 'Supported plugins: Payment Plugins for PayPal, WooCommerce PayPal Payments (Official), AngellEYE PayPal, Checkout Plugins PayPal.', 'woo-paypal-gateway' ); ?></p>
				<?php else : ?>
					<div class="wpg-converter-cards" style="display:flex;flex-wrap:wrap;gap:15px;">
						<?php foreach ( $detected as $key => $converter ) : ?>
							<div class="wpg-converter-card" data-converter="<?php echo esc_attr( $key ); ?>" style="border:1px solid #ccd0d4;background:#fff;padding:20px;border-radius:4px;cursor:pointer;min-width:250px;max-width:350px;">
								<h4 style="margin:0 0 8px;"><?php echo esc_html( $converter->get_source_name() ); ?></h4>
								<p style="margin:0;color:#666;font-size:13px;">
									<?php
									$order_count = $converter->count_orders_to_migrate();
									printf(
										/* translators: %d: number of orders */
										esc_html__( '%d orders found', 'woo-paypal-gateway' ),
										esc_html( $order_count )
									);
									?>
								</p>
								<button type="button" class="button button-primary wpg-conversion-start" style="margin-top:12px;" data-converter="<?php echo esc_attr( $key ); ?>">
									<?php esc_html_e( 'Migrate', 'woo-paypal-gateway' ); ?>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="wpg-conversion-wizard" style="display:none;margin-top:20px;border:1px solid #ccd0d4;background:#fff;padding:25px;border-radius:4px;">
				<h4 class="wpg-wizard-title" style="margin:0 0 15px;"></h4>

				<div class="wpg-wizard-step wpg-wizard-preview" style="display:none;">
					<h5><?php esc_html_e( 'Step 1: Preview Changes', 'woo-paypal-gateway' ); ?></h5>
					<div class="wpg-preview-loading">
						<span class="spinner is-active" style="float:none;"></span>
						<?php esc_html_e( 'Scanning...', 'woo-paypal-gateway' ); ?>
					</div>
					<div class="wpg-preview-results" style="display:none;">
						<table class="widefat striped" style="margin:10px 0;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Setting', 'woo-paypal-gateway' ); ?></th>
									<th><?php esc_html_e( 'Source Value', 'woo-paypal-gateway' ); ?></th>
									<th><?php esc_html_e( 'Current Value', 'woo-paypal-gateway' ); ?></th>
									<th><?php esc_html_e( 'Action', 'woo-paypal-gateway' ); ?></th>
								</tr>
							</thead>
							<tbody class="wpg-preview-settings-body"></tbody>
						</table>
						<p class="wpg-preview-order-count" style="font-size:14px;"></p>
						<div style="margin-top:15px;">
							<label style="margin-right:15px;">
								<input type="radio" name="wpg_migration_mode" value="merge" checked>
								<?php esc_html_e( 'Merge (keep existing, fill gaps)', 'woo-paypal-gateway' ); ?>
							</label>
							<label>
								<input type="radio" name="wpg_migration_mode" value="replace">
								<?php esc_html_e( 'Replace (overwrite with source)', 'woo-paypal-gateway' ); ?>
							</label>
						</div>
						<div style="margin-top:15px;">
							<button type="button" class="button button-primary wpg-wizard-run-migration"><?php esc_html_e( 'Start Migration', 'woo-paypal-gateway' ); ?></button>
							<button type="button" class="button wpg-wizard-cancel" style="margin-left:8px;"><?php esc_html_e( 'Cancel', 'woo-paypal-gateway' ); ?></button>
						</div>
					</div>
				</div>

				<div class="wpg-wizard-step wpg-wizard-progress" style="display:none;">
					<h5><?php esc_html_e( 'Step 2: Migrating...', 'woo-paypal-gateway' ); ?></h5>
					<div class="wpg-progress-bar-wrap" style="background:#f0f0f0;border-radius:4px;height:24px;margin:10px 0;overflow:hidden;">
						<div class="wpg-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s;border-radius:4px;"></div>
					</div>
					<p class="wpg-progress-status" style="font-size:13px;color:#666;"></p>
				</div>

				<div class="wpg-wizard-step wpg-wizard-complete" style="display:none;">
					<h5 style="color:#00a32a;"><?php esc_html_e( 'Migration Complete', 'woo-paypal-gateway' ); ?></h5>
					<div class="wpg-complete-summary" style="font-size:14px;"></div>
					<div style="margin-top:15px;">
						<button type="button" class="button wpg-wizard-close"><?php esc_html_e( 'Close', 'woo-paypal-gateway' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		(function($) {
			var currentConverter = '';
			var totalOrders = 0;
			var processedOrders = 0;
			var settingsMigrated = 0;
			var subscriptionsMigrated = 0;

			$('.wpg-conversion-start').on('click', function() {
				currentConverter = $(this).data('converter');
				var title = $(this).closest('.wpg-converter-card').find('h4').text();
				$('.wpg-wizard-title').text(title);
				$('.wpg-conversion-wizard').show();
				$('.wpg-wizard-preview').show();
				$('.wpg-wizard-progress, .wpg-wizard-complete').hide();
				$('.wpg-preview-loading').show();
				$('.wpg-preview-results').hide();
				loadPreview();
			});

			$('.wpg-wizard-cancel, .wpg-wizard-close').on('click', function() {
				$('.wpg-conversion-wizard').hide();
			});

			function loadPreview() {
				$.post(ajaxurl, {
					action: 'wpg_conversion_preview',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpg_conversion_nonce' ) ); ?>',
					converter: currentConverter
				}, function(response) {
					if (!response.success) {
						alert(response.data.message);
						return;
					}
					var data = response.data;
					totalOrders = data.order_count;

					var tbody = $('.wpg-preview-settings-body').empty();
					if (data.settings.length === 0) {
						tbody.append('<tr><td colspan="4"><?php echo esc_js( __( 'No settings to migrate.', 'woo-paypal-gateway' ) ); ?></td></tr>');
					} else {
						$.each(data.settings, function(i, s) {
							var action = s.will_change ? '<?php echo esc_js( __( 'Will update', 'woo-paypal-gateway' ) ); ?>' : '<?php echo esc_js( __( 'No change', 'woo-paypal-gateway' ) ); ?>';
							tbody.append('<tr><td>' + escHtml(s.setting) + '</td><td>' + escHtml(String(s.source_value || '')) + '</td><td>' + escHtml(String(s.current_value || '(empty)')) + '</td><td>' + escHtml(action) + '</td></tr>');
						});
					}

					$('.wpg-preview-order-count').html(
						'<strong>' + totalOrders + '</strong> <?php echo esc_js( __( 'orders will be updated to use this gateway.', 'woo-paypal-gateway' ) ); ?>'
					);

					$('.wpg-preview-loading').hide();
					$('.wpg-preview-results').show();
				});
			}

			$('.wpg-wizard-run-migration').on('click', function() {
				processedOrders = 0;
				settingsMigrated = 0;
				subscriptionsMigrated = 0;
				$('.wpg-wizard-preview').hide();
				$('.wpg-wizard-progress').show();
				updateProgress(0, '<?php echo esc_js( __( 'Migrating settings...', 'woo-paypal-gateway' ) ); ?>');

				var mode = $('input[name="wpg_migration_mode"]:checked').val();
				migrateSettings(mode);
			});

			function migrateSettings(mode) {
				$.post(ajaxurl, {
					action: 'wpg_conversion_migrate_settings',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpg_conversion_nonce' ) ); ?>',
					converter: currentConverter,
					mode: mode
				}, function(response) {
					if (!response.success) {
						alert(response.data.message);
						return;
					}
					settingsMigrated = response.data.migrated;
					if (totalOrders > 0) {
						updateProgress(10, '<?php echo esc_js( __( 'Migrating orders...', 'woo-paypal-gateway' ) ); ?>');
						migrateOrderBatch(true);
					} else {
						migrateSubscriptions();
					}
				});
			}

			function migrateOrderBatch(isFirst) {
				$.post(ajaxurl, {
					action: 'wpg_conversion_migrate_orders',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpg_conversion_nonce' ) ); ?>',
					converter: currentConverter,
					is_first: isFirst ? 'yes' : 'no',
					batch_size: 50
				}, function(response) {
					if (!response.success) {
						alert(response.data.message);
						return;
					}
					processedOrders += response.data.processed;
					var pct = totalOrders > 0 ? Math.round(10 + (processedOrders / totalOrders * 70)) : 80;
					updateProgress(pct, processedOrders + ' / ' + totalOrders + ' <?php echo esc_js( __( 'orders migrated', 'woo-paypal-gateway' ) ); ?>');

					if (!response.data.done) {
						migrateOrderBatch(false);
					} else {
						migrateSubscriptions();
					}
				});
			}

			function migrateSubscriptions() {
				updateProgress(85, '<?php echo esc_js( __( 'Migrating subscriptions...', 'woo-paypal-gateway' ) ); ?>');
				$.post(ajaxurl, {
					action: 'wpg_conversion_migrate_subscriptions',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpg_conversion_nonce' ) ); ?>',
					converter: currentConverter
				}, function(response) {
					if (response.success) {
						subscriptionsMigrated = response.data.migrated;
					}
					showComplete();
				});
			}

			function showComplete() {
				updateProgress(100, '<?php echo esc_js( __( 'Done!', 'woo-paypal-gateway' ) ); ?>');
				$('.wpg-wizard-progress').hide();
				$('.wpg-wizard-complete').show();
				var summary = '<ul style="list-style:disc;padding-left:20px;">';
				summary += '<li>' + settingsMigrated + ' <?php echo esc_js( __( 'settings migrated', 'woo-paypal-gateway' ) ); ?></li>';
				summary += '<li>' + processedOrders + ' <?php echo esc_js( __( 'orders updated', 'woo-paypal-gateway' ) ); ?></li>';
				summary += '<li>' + subscriptionsMigrated + ' <?php echo esc_js( __( 'subscriptions migrated', 'woo-paypal-gateway' ) ); ?></li>';
				summary += '</ul>';
				$('.wpg-complete-summary').html(summary);
			}

			function updateProgress(pct, text) {
				$('.wpg-progress-bar').css('width', pct + '%');
				$('.wpg-progress-status').text(text);
			}

			$('.wpg-conversion-rollback').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Are you sure? This will restore your settings from before the migration.', 'woo-paypal-gateway' ) ); ?>')) {
					return;
				}
				$.post(ajaxurl, {
					action: 'wpg_conversion_rollback',
					nonce: '<?php echo esc_js( wp_create_nonce( 'wpg_conversion_nonce' ) ); ?>'
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message);
					}
				});
			});

			function escHtml(str) {
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(str));
				return div.innerHTML;
			}
		})(jQuery);
		</script>
		<?php
	}
}
