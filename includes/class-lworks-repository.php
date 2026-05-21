<?php
/**
 * Data access helpers.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Repository {
	/**
	 * Return a plugin table name.
	 *
	 * @param string $name Table suffix.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'lworks_' . $name;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'require_invite_code' => 0,
			'member_remember_days' => 180,
			'staff_remember_days'  => 30,
			'notification_email'   => get_option( 'admin_email' ),
			'dashboard_page_id'    => 0,
			'coordinator_page_id'  => 0,
		);
	}

	/**
	 * Current settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = get_option( 'lworks_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	public static function save_settings( $settings ) {
		update_option( 'lworks_settings', wp_parse_args( $settings, self::default_settings() ), false );
	}

	/**
	 * Create or update a group.
	 *
	 * @param array $data Group data.
	 * @return int
	 */
	public static function save_group( $data ) {
		global $wpdb;

		$table       = self::table( 'groups' );
		$now         = current_time( 'mysql', true );
		$group_id    = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name        = sanitize_text_field( $data['name'] );
		$slug        = sanitize_title( ! empty( $data['slug'] ) ? $data['slug'] : $name );
		$description = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
		$invite_code = isset( $data['invite_code'] ) ? self::sanitize_invite_code( $data['invite_code'] ) : '';
		$active      = empty( $data['active'] ) ? 0 : 1;

		if ( '' === $invite_code ) {
			$invite_code = self::generate_invite_code();
		}

		if ( $group_id ) {
			$wpdb->update(
				$table,
				array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $description,
					'invite_code' => $invite_code,
					'active'      => $active,
					'updated_at'  => $now,
				),
				array( 'id' => $group_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			return $group_id;
		}

		$wpdb->insert(
			$table,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'invite_code' => $invite_code,
				'active'      => $active,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get groups.
	 *
	 * @param bool $active_only Only active groups.
	 * @return array
	 */
	public static function get_groups( $active_only = false ) {
		global $wpdb;

		$table = self::table( 'groups' );

		if ( $active_only ) {
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY name ASC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY active DESC, name ASC" );
	}

	/**
	 * Get one group.
	 *
	 * @param int $group_id Group ID.
	 * @return object|null
	 */
	public static function get_group( $group_id ) {
		global $wpdb;

		$table = self::table( 'groups' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $group_id ) ) );
	}

	/**
	 * Get a group by invite code.
	 *
	 * @param string $invite_code Invite code.
	 * @return object|null
	 */
	public static function get_group_by_invite_code( $invite_code ) {
		global $wpdb;

		$table       = self::table( 'groups' );
		$invite_code = self::sanitize_invite_code( $invite_code );

		if ( '' === $invite_code ) {
			return null;
		}

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE invite_code = %s AND active = 1", $invite_code ) );
	}

	/**
	 * Add or update membership.
	 *
	 * @param int    $group_id Group ID.
	 * @param int    $user_id User ID.
	 * @param string $status Membership status.
	 * @param string $role Group role.
	 * @param string $notes Notes.
	 * @return int
	 */
	public static function save_membership( $group_id, $user_id, $status = 'pending', $role = 'member', $notes = '' ) {
		global $wpdb;

		$table    = self::table( 'group_members' );
		$now      = current_time( 'mysql', true );
		$group_id = absint( $group_id );
		$user_id  = absint( $user_id );
		$status   = self::sanitize_membership_status( $status );
		$role     = self::sanitize_member_role( $role );
		$notes    = sanitize_textarea_field( $notes );
		$existing = self::get_membership_for_user_group( $user_id, $group_id );

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'status'      => $status,
					'member_role' => $role,
					'notes'       => $notes,
					'updated_at'  => $now,
				),
				array( 'id' => absint( $existing->id ) ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			array(
				'group_id'    => $group_id,
				'user_id'     => $user_id,
				'status'      => $status,
				'member_role' => $role,
				'notes'       => $notes,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get membership by ID.
	 *
	 * @param int $membership_id Membership ID.
	 * @return object|null
	 */
	public static function get_membership( $membership_id ) {
		global $wpdb;

		$table = self::table( 'group_members' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $membership_id ) ) );
	}

	/**
	 * Get one user's membership in a group.
	 *
	 * @param int $user_id User ID.
	 * @param int $group_id Group ID.
	 * @return object|null
	 */
	public static function get_membership_for_user_group( $user_id, $group_id ) {
		global $wpdb;

		$table = self::table( 'group_members' );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND group_id = %d",
				absint( $user_id ),
				absint( $group_id )
			)
		);
	}

	/**
	 * Get user memberships.
	 *
	 * @param int   $user_id User ID.
	 * @param array $statuses Statuses.
	 * @return array
	 */
	public static function get_user_memberships( $user_id, $statuses = array( 'active' ) ) {
		global $wpdb;

		$members = self::table( 'group_members' );
		$groups  = self::table( 'groups' );
		$user_id = absint( $user_id );
		$where   = 'm.user_id = %d';
		$params  = array( $user_id );

		if ( ! empty( $statuses ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$where       .= " AND m.status IN ({$placeholders})";
			$params       = array_merge( $params, array_map( array( __CLASS__, 'sanitize_membership_status' ), $statuses ) );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, g.name AS group_name, g.slug AS group_slug, g.active AS group_active
				FROM {$members} m
				INNER JOIN {$groups} g ON g.id = m.group_id
				WHERE {$where}
				ORDER BY g.name ASC",
				$params
			)
		);
	}

	/**
	 * Return group IDs a user can view.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_active_group_ids( $user_id ) {
		$memberships = self::get_user_memberships( $user_id, array( 'active' ) );
		$group_ids   = array();

		foreach ( $memberships as $membership ) {
			if ( (int) $membership->group_active ) {
				$group_ids[] = (int) $membership->group_id;
			}
		}

		return array_values( array_unique( $group_ids ) );
	}

	/**
	 * Get pending memberships visible to a manager.
	 *
	 * @param int $manager_user_id User ID.
	 * @return array
	 */
	public static function get_pending_memberships( $manager_user_id = 0 ) {
		global $wpdb;

		$members = self::table( 'group_members' );
		$groups  = self::table( 'groups' );
		$where   = "m.status = 'pending'";
		$params  = array();

		if ( $manager_user_id && ! current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			$managed_group_ids = self::get_managed_group_ids( $manager_user_id );
			if ( empty( $managed_group_ids ) ) {
				return array();
			}

			$placeholders = implode( ',', array_fill( 0, count( $managed_group_ids ), '%d' ) );
			$where       .= " AND m.group_id IN ({$placeholders})";
			$params       = array_merge( $params, $managed_group_ids );
		}

		$sql = "SELECT m.*, g.name AS group_name
			FROM {$members} m
			INNER JOIN {$groups} g ON g.id = m.group_id
			WHERE {$where}
			ORDER BY m.created_at ASC";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Approve a membership.
	 *
	 * @param int $membership_id Membership ID.
	 * @param int $approved_by Approving user ID.
	 * @return bool
	 */
	public static function approve_membership( $membership_id, $approved_by ) {
		global $wpdb;

		$table = self::table( 'group_members' );
		$now   = current_time( 'mysql', true );

		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'active',
				'updated_at'  => $now,
				'approved_at' => $now,
				'approved_by' => absint( $approved_by ),
			),
			array( 'id' => absint( $membership_id ) ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Reject a membership.
	 *
	 * @param int $membership_id Membership ID.
	 * @param int $actor_user_id Actor user ID.
	 * @return bool
	 */
	public static function reject_membership( $membership_id, $actor_user_id ) {
		global $wpdb;

		$table  = self::table( 'group_members' );
		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'rejected',
				'updated_at'  => current_time( 'mysql', true ),
				'approved_by' => absint( $actor_user_id ),
			),
			array( 'id' => absint( $membership_id ) ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get group IDs coordinated by a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_managed_group_ids( $user_id ) {
		global $wpdb;

		if ( current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			$groups = self::get_groups( true );
			return array_map( 'intval', wp_list_pluck( $groups, 'id' ) );
		}

		$table = self::table( 'group_members' );

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT group_id FROM {$table} WHERE user_id = %d AND status = 'active' AND member_role = 'coordinator'",
					absint( $user_id )
				)
			)
		);
	}

	/**
	 * Check if a user can access a group.
	 *
	 * @param int $user_id User ID.
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	public static function user_can_access_group( $user_id, $group_id ) {
		if ( current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			return true;
		}

		$membership = self::get_membership_for_user_group( $user_id, $group_id );

		return $membership && 'active' === $membership->status;
	}

	/**
	 * Check if a user can manage a group.
	 *
	 * @param int $user_id User ID.
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	public static function user_can_manage_group( $user_id, $group_id ) {
		if ( current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			return true;
		}

		$membership = self::get_membership_for_user_group( $user_id, $group_id );

		return $membership && 'active' === $membership->status && 'coordinator' === $membership->member_role;
	}

	/**
	 * Create a private request.
	 *
	 * @param array $data Request data.
	 * @return int
	 */
	public static function create_request( $data ) {
		global $wpdb;

		$table = self::table( 'requests' );
		$now   = current_time( 'mysql', true );

		$wpdb->insert(
			$table,
			array(
				'group_id'     => absint( $data['group_id'] ),
				'user_id'      => absint( $data['user_id'] ),
				'request_type' => self::sanitize_request_type( $data['request_type'] ),
				'title'        => sanitize_text_field( $data['title'] ),
				'content'      => wp_kses_post( $data['content'] ),
				'status'       => self::sanitize_request_status( isset( $data['status'] ) ? $data['status'] : 'open' ),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a request.
	 *
	 * @param int $request_id Request ID.
	 * @return object|null
	 */
	public static function get_request( $request_id ) {
		global $wpdb;

		$table = self::table( 'requests' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $request_id ) ) );
	}

	/**
	 * Get request feed visible to a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_requests_for_user( $user_id, $limit = 50 ) {
		global $wpdb;

		$requests  = self::table( 'requests' );
		$groups    = self::table( 'groups' );
		$group_ids = self::get_user_active_group_ids( $user_id );

		if ( empty( $group_ids ) ) {
			return array();
		}

		$limit        = min( 100, max( 1, absint( $limit ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
		$params       = array_merge( $group_ids, array( $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, g.name AS group_name
				FROM {$requests} r
				INNER JOIN {$groups} g ON g.id = r.group_id
				WHERE r.group_id IN ({$placeholders})
				ORDER BY r.created_at DESC
				LIMIT %d",
				$params
			)
		);
	}

	/**
	 * Get responses for a set of requests.
	 *
	 * @param array $request_ids Request IDs.
	 * @return array
	 */
	public static function get_responses_for_requests( $request_ids ) {
		global $wpdb;

		$request_ids = array_filter( array_map( 'absint', $request_ids ) );
		if ( empty( $request_ids ) ) {
			return array();
		}

		$table        = self::table( 'responses' );
		$placeholders = implode( ',', array_fill( 0, count( $request_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE request_id IN ({$placeholders}) ORDER BY created_at ASC",
				$request_ids
			)
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$request_id = (int) $row->request_id;
			if ( ! isset( $grouped[ $request_id ] ) ) {
				$grouped[ $request_id ] = array();
			}
			$grouped[ $request_id ][] = $row;
		}

		return $grouped;
	}

	/**
	 * Add a response.
	 *
	 * @param array $data Response data.
	 * @return int
	 */
	public static function create_response( $data ) {
		global $wpdb;

		$table = self::table( 'responses' );

		$wpdb->insert(
			$table,
			array(
				'request_id'    => absint( $data['request_id'] ),
				'user_id'       => absint( $data['user_id'] ),
				'response_type' => self::sanitize_response_type( $data['response_type'] ),
				'content'       => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a request status.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_request_status( $request_id, $status ) {
		global $wpdb;

		$table  = self::table( 'requests' );
		$result = $wpdb->update(
			$table,
			array(
				'status'     => self::sanitize_request_status( $status ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $request_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Record audit entry.
	 *
	 * @param int|null $actor_user_id Actor user ID.
	 * @param string   $object_type Object type.
	 * @param int|null $object_id Object ID.
	 * @param string   $event Event name.
	 * @param string   $message Message.
	 * @return void
	 */
	public static function audit( $actor_user_id, $object_type, $object_id, $event, $message = '' ) {
		global $wpdb;

		$wpdb->insert(
			self::table( 'audit_log' ),
			array(
				'actor_user_id' => $actor_user_id ? absint( $actor_user_id ) : null,
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => $object_id ? absint( $object_id ) : null,
				'event'         => sanitize_key( $event ),
				'message'       => sanitize_textarea_field( $message ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Generate a short invite code.
	 *
	 * @return string
	 */
	public static function generate_invite_code() {
		return strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Sanitize invite code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function sanitize_invite_code( $code ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', sanitize_text_field( wp_unslash( $code ) ) ) );
	}

	/**
	 * Allowed request types.
	 *
	 * @return array
	 */
	public static function request_types() {
		return array(
			'prayer'        => __( 'Prayer request', 'littleworks-of-mercy' ),
			'meal'          => __( 'Meal help', 'littleworks-of-mercy' ),
			'ride'          => __( 'Ride or transportation', 'littleworks-of-mercy' ),
			'visit'         => __( 'Visit or companionship', 'littleworks-of-mercy' ),
			'material_need' => __( 'Material need', 'littleworks-of-mercy' ),
			'other'         => __( 'Other need', 'littleworks-of-mercy' ),
		);
	}

	/**
	 * Allowed request statuses.
	 *
	 * @return array
	 */
	public static function request_statuses() {
		return array(
			'open'       => __( 'Open', 'littleworks-of-mercy' ),
			'responding' => __( 'Someone responding', 'littleworks-of-mercy' ),
			'fulfilled'  => __( 'Fulfilled', 'littleworks-of-mercy' ),
			'closed'     => __( 'Closed', 'littleworks-of-mercy' ),
		);
	}

	/**
	 * Sanitize request type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function sanitize_request_type( $type ) {
		$type = sanitize_key( $type );

		return array_key_exists( $type, self::request_types() ) ? $type : 'prayer';
	}

	/**
	 * Sanitize request status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function sanitize_request_status( $status ) {
		$status = sanitize_key( $status );

		return array_key_exists( $status, self::request_statuses() ) ? $status : 'open';
	}

	/**
	 * Sanitize response type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function sanitize_response_type( $type ) {
		$type    = sanitize_key( $type );
		$allowed = array( 'prayer', 'help', 'message', 'status_note' );

		return in_array( $type, $allowed, true ) ? $type : 'message';
	}

	/**
	 * Sanitize membership status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function sanitize_membership_status( $status ) {
		$status  = sanitize_key( $status );
		$allowed = array( 'pending', 'active', 'rejected', 'blocked' );

		return in_array( $status, $allowed, true ) ? $status : 'pending';
	}

	/**
	 * Sanitize group member role.
	 *
	 * @param string $role Role.
	 * @return string
	 */
	public static function sanitize_member_role( $role ) {
		$role    = sanitize_key( $role );
		$allowed = array( 'member', 'coordinator' );

		return in_array( $role, $allowed, true ) ? $role : 'member';
	}

	/**
	 * Generate a unique username from an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	public static function username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'member';
		}

		$username = $base;
		$suffix   = 2;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			$suffix++;
		}

		return $username;
	}

	/**
	 * Get a configured page URL.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function get_page_url( $key ) {
		$settings = self::settings();
		$page_id  = isset( $settings[ $key ] ) ? absint( $settings[ $key ] ) : 0;

		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}
}
