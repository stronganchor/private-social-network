<?php
/**
 * Plugin Name: littleWORKS of Mercy
 * Plugin URI: https://github.com/stronganchor/private-social-network
 * Description: Private parish/community request board with approved registrations, group-scoped content, coordinator moderation, and GitHub-hosted updates.
 * Version: 0.2.0
 * Author: Strong Anchor
 * Requires at least: 6.4
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Text Domain: littleworks-of-mercy
 * Update URI: https://github.com/stronganchor/private-social-network
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LWORKS_VERSION', '0.2.0' );
define( 'LWORKS_PLUGIN_FILE', __FILE__ );
define( 'LWORKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LWORKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LWORKS_GITHUB_REPO', 'https://github.com/stronganchor/private-social-network/' );

define( 'LWORKS_CAP_VIEW', 'lworks_view_member_area' );
define( 'LWORKS_CAP_POST', 'lworks_submit_request' );
define( 'LWORKS_CAP_RESPOND', 'lworks_respond_to_request' );
define( 'LWORKS_CAP_MANAGE_ASSIGNED', 'lworks_manage_assigned_groups' );
define( 'LWORKS_CAP_MANAGE_ALL', 'lworks_manage_all_groups' );

$lworks_autoloader = LWORKS_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $lworks_autoloader ) ) {
	require_once $lworks_autoloader;
}

require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-activator.php';
require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-repository.php';
require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-shortcodes.php';
require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-admin.php';
require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-update-checker.php';
require_once LWORKS_PLUGIN_DIR . 'includes/class-lworks-plugin.php';

register_activation_hook( __FILE__, array( 'LWorks_Activator', 'activate' ) );

add_action( 'plugins_loaded', array( 'LWorks_Plugin', 'init' ) );
