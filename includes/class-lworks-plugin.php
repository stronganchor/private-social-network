<?php
/**
 * Main plugin bootstrap.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Plugin {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'auth_cookie_expiration', array( __CLASS__, 'auth_cookie_expiration' ), 10, 3 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );

		LWorks_Shortcodes::init();
		LWorks_Admin::init();
		LWorks_Update_Checker::init();
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'littleworks-of-mercy', false, dirname( plugin_basename( LWORKS_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Run activation routines after plugin updates that add schema.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'lworks_db_version' ) === LWORKS_VERSION ) {
			return;
		}

		LWorks_Activator::activate();
	}

	/**
	 * Enqueue frontend CSS.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		wp_register_style(
			'lworks-frontend',
			LWORKS_PLUGIN_URL . 'assets/lworks.css',
			array(),
			LWORKS_VERSION
		);

		if ( self::page_has_frontend_shortcode() ) {
			self::use_frontend_assets();
		}
	}

	/**
	 * Extend remember-me sessions with stricter values for coordinators/admins.
	 *
	 * @param int  $expiration Expiration in seconds.
	 * @param int  $user_id User ID.
	 * @param bool $remember Whether remember-me is enabled.
	 * @return int
	 */
	public static function auth_cookie_expiration( $expiration, $user_id, $remember ) {
		if ( ! $remember || ! $user_id ) {
			return $expiration;
		}

		$settings = LWorks_Repository::settings();
		$user     = get_user_by( 'id', $user_id );
		$is_staff = false;

		if ( $user instanceof WP_User ) {
			$is_staff = user_can( $user, LWORKS_CAP_MANAGE_ASSIGNED ) || user_can( $user, LWORKS_CAP_MANAGE_ALL ) || user_can( $user, 'manage_options' );
		}

		$days = $is_staff ? absint( $settings['staff_remember_days'] ) : absint( $settings['member_remember_days'] );
		$days = max( 1, min( 3650, $days ) );

		return DAY_IN_SECONDS * $days;
	}

	/**
	 * Send littleWORKS users to the configured dashboard after login.
	 *
	 * @param string           $redirect_to Redirect URL.
	 * @param string           $requested Requested redirect.
	 * @param WP_User|WP_Error $user User or error.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $requested || is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( user_can( $user, LWORKS_CAP_VIEW ) ) {
			return LWorks_Repository::get_page_url( 'dashboard_page_id' );
		}

		return $redirect_to;
	}

	/**
	 * Enqueue frontend CSS when a shortcode renders.
	 *
	 * @return void
	 */
	public static function use_frontend_assets() {
		wp_enqueue_style( 'lworks-frontend' );
	}

	/**
	 * Check whether the queried page contains a littleWORKS frontend shortcode.
	 *
	 * @return bool
	 */
	private static function page_has_frontend_shortcode() {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || empty( $post->post_content ) ) {
			return false;
		}

		$shortcodes = array(
			'lworks_registration',
			'lworks_member_links',
			'lworks_login',
			'lworks_dashboard',
			'lworks_request_board',
			'lworks_coordinator',
			'lworks_profile',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}
}
