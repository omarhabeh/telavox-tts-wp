<?php
/**
 * Customer role: portal-only access (no WP dashboard / admin bar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Customer role (read-only; enough to log in + use the portal).
 */
function tts_portal_register_customer_role() {
	if ( get_role( 'customer' ) ) {
		return;
	}

	add_role(
		'customer',
		'Customer',
		array(
			'read' => true,
		)
	);
}
add_action( 'after_switch_theme', 'tts_portal_register_customer_role' );
add_action( 'init', 'tts_portal_register_customer_role' );

/**
 * Whether a user (or the current user) is a Customer.
 *
 * @param WP_User|null $user Optional user. Defaults to current user.
 */
function tts_portal_is_customer( $user = null ) {
	if ( ! $user ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
	}

	if ( ! $user instanceof WP_User || empty( $user->ID ) ) {
		return false;
	}

	return in_array( 'customer', (array) $user->roles, true );
}

/**
 * Hide the admin bar for customers (front and back).
 */
function tts_portal_hide_admin_bar_for_customers( $show ) {
	if ( tts_portal_is_customer() ) {
		return false;
	}
	return $show;
}
add_filter( 'show_admin_bar', 'tts_portal_hide_admin_bar_for_customers' );

/**
 * Block customers from wp-admin entirely.
 */
function tts_portal_block_customer_admin() {
	if ( ! tts_portal_is_customer() ) {
		return;
	}

	// Allow nothing in admin — send them to the portal.
	if ( wp_doing_ajax() ) {
		return;
	}

	wp_safe_redirect( home_url( '/' ) );
	exit;
}
add_action( 'admin_init', 'tts_portal_block_customer_admin' );

/**
 * After login, customers always land on the TTS portal (never dashboard).
 */
function tts_portal_customer_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
		return $redirect_to;
	}

	if ( tts_portal_is_customer( $user ) ) {
		return home_url( '/' );
	}

	return $redirect_to;
}
add_filter( 'login_redirect', 'tts_portal_customer_login_redirect', 20, 3 );
