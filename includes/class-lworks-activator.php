<?php
/**
 * Activation and schema setup.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Activator {
	/**
	 * Create tables, roles, and default options.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::create_roles();
		self::create_options();
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$groups          = LWorks_Repository::table( 'groups' );
		$members         = LWorks_Repository::table( 'group_members' );
		$requests        = LWorks_Repository::table( 'requests' );
		$responses       = LWorks_Repository::table( 'responses' );
		$audit           = LWorks_Repository::table( 'audit_log' );

		$sql = array();

		$sql[] = "CREATE TABLE {$groups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			invite_code varchar(64) NOT NULL DEFAULT '',
			active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY active (active),
			KEY invite_code (invite_code)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$members} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			member_role varchar(20) NOT NULL DEFAULT 'member',
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			approved_at datetime NULL,
			approved_by bigint(20) unsigned NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY group_user (group_id,user_id),
			KEY group_status (group_id,status),
			KEY user_status (user_id,status),
			KEY member_role (member_role)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$requests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			request_type varchar(30) NOT NULL DEFAULT 'prayer',
			title varchar(191) NOT NULL,
			content longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY group_status (group_id,status),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$responses} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			response_type varchar(30) NOT NULL DEFAULT 'message',
			content longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) unsigned NULL,
			object_type varchar(40) NOT NULL,
			object_id bigint(20) unsigned NULL,
			event varchar(60) NOT NULL,
			message text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY object_event (object_type,object_id,event),
			KEY actor_user_id (actor_user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'lworks_db_version', LWORKS_VERSION, false );
	}

	/**
	 * Create roles and capabilities.
	 *
	 * @return void
	 */
	private static function create_roles() {
		if ( ! get_role( 'lworks_member' ) ) {
			add_role(
				'lworks_member',
				__( 'littleWORKS Member', 'littleworks-of-mercy' ),
				array( 'read' => true )
			);
		}

		if ( ! get_role( 'lworks_coordinator' ) ) {
			add_role(
				'lworks_coordinator',
				__( 'littleWORKS Coordinator', 'littleworks-of-mercy' ),
				array( 'read' => true )
			);
		}

		$member_role = get_role( 'lworks_member' );
		if ( $member_role ) {
			$member_role->add_cap( 'read' );
			$member_role->add_cap( LWORKS_CAP_VIEW );
			$member_role->add_cap( LWORKS_CAP_POST );
			$member_role->add_cap( LWORKS_CAP_RESPOND );
		}

		$coordinator_role = get_role( 'lworks_coordinator' );
		if ( $coordinator_role ) {
			$coordinator_role->add_cap( 'read' );
			$coordinator_role->add_cap( LWORKS_CAP_VIEW );
			$coordinator_role->add_cap( LWORKS_CAP_POST );
			$coordinator_role->add_cap( LWORKS_CAP_RESPOND );
			$coordinator_role->add_cap( LWORKS_CAP_MANAGE_ASSIGNED );
		}

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( LWORKS_CAP_VIEW );
			$admin_role->add_cap( LWORKS_CAP_POST );
			$admin_role->add_cap( LWORKS_CAP_RESPOND );
			$admin_role->add_cap( LWORKS_CAP_MANAGE_ASSIGNED );
			$admin_role->add_cap( LWORKS_CAP_MANAGE_ALL );
		}
	}

	/**
	 * Seed default options.
	 *
	 * @return void
	 */
	private static function create_options() {
		$defaults = LWorks_Repository::default_settings();
		$current  = get_option( 'lworks_settings' );

		if ( ! is_array( $current ) ) {
			add_option( 'lworks_settings', $defaults, '', false );
			return;
		}

		update_option( 'lworks_settings', wp_parse_args( $current, $defaults ), false );
	}
}
