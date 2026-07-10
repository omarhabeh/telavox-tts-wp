<?php
/**
 * TTS Portal theme bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/includes/voices.php';
require_once get_template_directory() . '/includes/elevenlabs.php';
require_once get_template_directory() . '/includes/requests-log.php';
require_once get_template_directory() . '/includes/rest-api.php';
require_once get_template_directory() . '/includes/settings.php';
require_once get_template_directory() . '/includes/customer-role.php';

/**
 * Send guests to the WordPress login page, then back here.
 */
function tts_portal_require_login() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		return;
	}

	auth_redirect();
}
add_action( 'template_redirect', 'tts_portal_require_login' );

/**
 * After login, land on the portal home.
 */
function tts_portal_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) ) {
		return $redirect_to;
	}
	if ( ! empty( $requested_redirect_to ) ) {
		return $requested_redirect_to;
	}
	return home_url( '/' );
}
add_filter( 'login_redirect', 'tts_portal_login_redirect', 10, 3 );

/**
 * Front-end assets.
 */
function tts_portal_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'tts-portal',
		get_stylesheet_uri(),
		array(),
		'1.0.4'
	);

	wp_enqueue_script(
		'tts-portal',
		get_template_directory_uri() . '/assets/portal.js',
		array(),
		'1.0.1',
		true
	);

	$current_user = wp_get_current_user();
	$raw_name     = $current_user->first_name
		? $current_user->first_name
		: ( $current_user->display_name ? $current_user->display_name : $current_user->user_login );
	$user_name    = tts_portal_capitalize_name( $raw_name );

	wp_localize_script(
		'tts-portal',
		'ttsPortal',
		array(
			'restUrl'  => esc_url_raw( rest_url( 'tts-portal/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'userName' => $user_name,
			'userEmail'=> $current_user->user_email ? $current_user->user_email : '',
			'logoutUrl'=> esc_url_raw( wp_logout_url( home_url( '/' ) ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'tts_portal_enqueue_assets' );

/**
 * Capitalize the first letter of each word in a name.
 */
function tts_portal_capitalize_name( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '';
	}
	if ( function_exists( 'mb_convert_case' ) ) {
		return mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
	}
	return ucwords( strtolower( $name ) );
}

/**
 * Minimal theme supports.
 */
function tts_portal_setup() {
	add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'tts_portal_setup' );

/**
 * Browser tab title.
 */
function tts_portal_document_title() {
	return 'Telavox TTS Portal';
}
add_filter( 'pre_get_document_title', 'tts_portal_document_title' );

function tts_portal_login_title( $title ) {
	return 'Telavox TTS Portal';
}
add_filter( 'login_title', 'tts_portal_login_title' );

/**
 * Site / favicon URL.
 */
function tts_portal_icon_url() {
	return get_template_directory_uri() . '/assets/telavox-icon.png';
}

function tts_portal_site_icon_url( $url, $size = null ) {
	return tts_portal_icon_url();
}
add_filter( 'get_site_icon_url', 'tts_portal_site_icon_url', 10, 2 );

/**
 * Output favicon link tags (front, login, admin).
 */
function tts_portal_output_favicon() {
	$icon = esc_url( tts_portal_icon_url() );
	echo '<link rel="icon" href="' . $icon . '" type="image/png" sizes="32x32" />' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $icon . '" />' . "\n";
}
add_action( 'wp_head', 'tts_portal_output_favicon', 1 );
add_action( 'login_head', 'tts_portal_output_favicon', 1 );
add_action( 'admin_head', 'tts_portal_output_favicon', 1 );

/**
 * Replace the WordPress logo in the admin bar with Telavox.
 */
function tts_portal_add_telavox_admin_bar_logo( $wp_admin_bar ) {
	$logo = esc_url( get_template_directory_uri() . '/assets/telavox-logo.svg' );

	$wp_admin_bar->add_node(
		array(
			'id'    => 'telavox-logo',
			'title' => '<img class="tts-admin-bar-logo" src="' . $logo . '" alt="Telavox" width="110" height="20" />',
			'href'  => admin_url(),
			'meta'  => array(
				'title' => 'Telavox',
			),
		)
	);
}
add_action( 'admin_bar_menu', 'tts_portal_add_telavox_admin_bar_logo', 1 );

function tts_portal_remove_wp_admin_bar_logo( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'wp-logo' );
}
add_action( 'admin_bar_menu', 'tts_portal_remove_wp_admin_bar_logo', 999 );

/**
 * Style the Telavox admin-bar logo.
 */
function tts_portal_admin_bar_logo_css() {
	$css = '
		#wpadminbar #wp-admin-bar-telavox-logo > .ab-item {
			display: flex;
			align-items: center;
			padding: 0 12px !important;
			height: 32px !important;
		}
		#wpadminbar #wp-admin-bar-telavox-logo .tts-admin-bar-logo {
			display: block;
			height: 18px;
			width: auto;
			padding: 0;
			filter: brightness(0) invert(1);
		}
	';

	wp_register_style( 'tts-portal-admin-bar', false, array(), '1.0.1' );
	wp_enqueue_style( 'tts-portal-admin-bar' );
	wp_add_inline_style( 'tts-portal-admin-bar', $css );
}
add_action( 'admin_enqueue_scripts', 'tts_portal_admin_bar_logo_css' );
add_action( 'wp_enqueue_scripts', 'tts_portal_admin_bar_logo_css' );

/**
 * Style wp-login.php to match the portal card UI + Telavox logo.
 */
function tts_portal_login_enqueue() {
	wp_enqueue_style(
		'tts-portal-login',
		get_template_directory_uri() . '/assets/login.css',
		array(),
		'1.0.6'
	);

	wp_enqueue_script(
		'tts-portal-login',
		get_template_directory_uri() . '/assets/login.js',
		array(),
		'1.0.6',
		true
	);
}
add_action( 'login_enqueue_scripts', 'tts_portal_login_enqueue' );

function tts_portal_login_logo_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'tts_portal_login_logo_url' );

function tts_portal_login_logo_title() {
	return 'Telavox TTS Portal';
}
add_filter( 'login_headertext', 'tts_portal_login_logo_title' );

/**
 * Portal-style heading at the top of the login form card.
 */
function tts_portal_login_message( $message ) {
	global $action;

	$title = 'Telavox TTS Portal';
	$sub   = 'Log in to continue.';

	if ( 'lostpassword' === $action ) {
		$title = 'Reset password';
		$sub   = 'Enter your email or username to get a reset link.';
	} elseif ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
		$title = 'Set new password';
		$sub   = 'Choose a new password for your account.';
	}

	$heading  = '<div class="tts-login-heading">';
	$heading .= '<h2>' . esc_html( $title ) . '</h2>';
	$heading .= '<p class="sub">' . esc_html( $sub ) . '</p>';
	$heading .= '</div>';

	return $heading . $message;
}
add_filter( 'login_message', 'tts_portal_login_message' );
