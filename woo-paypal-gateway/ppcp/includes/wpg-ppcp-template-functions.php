<?php

defined( 'ABSPATH' ) || exit;

function wpg_ppcp_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = 'woo-paypal-gateway/';
	}
	if ( ! $default_path ) {
		$default_path = WPG_PLUGIN_DIR . '/ppcp/templates/';
	}

	$args = apply_filters( 'wpg_ppcp_template_args_' . sanitize_file_name( $template_name ), $args );

	$template = locate_template( array(
		trailingslashit( $template_path ) . $template_name,
		$template_name,
	) );

	if ( ! $template ) {
		$template = trailingslashit( $default_path ) . $template_name;
	}

	$template = apply_filters( 'wpg_ppcp_locate_template', $template, $template_name, $template_path );

	if ( ! file_exists( $template ) ) {
		return;
	}

	do_action( 'wpg_ppcp_before_template_' . sanitize_file_name( $template_name ), $args );

	if ( $args && is_array( $args ) ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract
	}

	include $template;

	do_action( 'wpg_ppcp_after_template_' . sanitize_file_name( $template_name ), $args );
}

function wpg_ppcp_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	ob_start();
	wpg_ppcp_get_template( $template_name, $args, $template_path, $default_path );
	return ob_get_clean();
}
