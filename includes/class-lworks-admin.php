<?php
/**
 * Admin screens.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
	}

	/**
	 * Add admin pages.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'littleWORKS', 'littleworks-of-mercy' ),
			__( 'littleWORKS', 'littleworks-of-mercy' ),
			LWORKS_CAP_MANAGE_ALL,
			'lworks',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-groups',
			58
		);

		add_submenu_page(
			'lworks',
			__( 'Groups', 'littleworks-of-mercy' ),
			__( 'Groups', 'littleworks-of-mercy' ),
			LWORKS_CAP_MANAGE_ALL,
			'lworks-groups',
			array( __CLASS__, 'render_groups_page' )
		);

		add_submenu_page(
			'lworks',
			__( 'Pending Registrations', 'littleworks-of-mercy' ),
			__( 'Pending', 'littleworks-of-mercy' ),
			LWORKS_CAP_MANAGE_ALL,
			'lworks-pending',
			array( __CLASS__, 'render_pending_page' )
		);

		add_submenu_page(
			'lworks',
			__( 'Audit Log', 'littleworks-of-mercy' ),
			__( 'Audit Log', 'littleworks-of-mercy' ),
			LWORKS_CAP_MANAGE_ALL,
			'lworks-audit',
			array( __CLASS__, 'render_audit_page' )
		);

		add_submenu_page(
			'lworks',
			__( 'Settings', 'littleworks-of-mercy' ),
			__( 'Settings', 'littleworks-of-mercy' ),
			LWORKS_CAP_MANAGE_ALL,
			'lworks-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Handle admin forms.
	 *
	 * @return void
	 */
	public static function handle_admin_actions() {
		if ( empty( $_POST['lworks_admin_action'] ) || ! current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['lworks_admin_action'] ) );

		if ( 'save_group' === $action ) {
			self::handle_save_group();
		}

		if ( 'assign_coordinator' === $action ) {
			self::handle_assign_coordinator();
		}

		if ( 'membership_action' === $action ) {
			self::handle_membership_action();
		}

		if ( 'save_settings' === $action ) {
			self::handle_save_settings();
		}
	}

	/**
	 * Render admin overview.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		$groups  = LWorks_Repository::get_groups();
		$pending = LWorks_Repository::get_pending_memberships();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'littleWORKS of Mercy', 'littleworks-of-mercy' ) . '</h1>';
		self::render_admin_notice();
		echo '<p>' . esc_html__( 'Private parish/community request boards with approved registration and coordinator moderation.', 'littleworks-of-mercy' ) . '</p>';

		echo '<h2>' . esc_html__( 'Setup shortcodes', 'littleworks-of-mercy' ) . '</h2>';
		echo '<table class="widefat striped"><tbody>';
		self::render_shortcode_row( '[lworks_registration]', __( 'Public registration form', 'littleworks-of-mercy' ) );
		self::render_shortcode_row( '[lworks_login]', __( 'Login form with remember-me enabled by default', 'littleworks-of-mercy' ) );
		self::render_shortcode_row( '[lworks_dashboard]', __( 'Private member dashboard and request feed', 'littleworks-of-mercy' ) );
		self::render_shortcode_row( '[lworks_coordinator]', __( 'Frontend coordinator approval screen', 'littleworks-of-mercy' ) );
		self::render_shortcode_row( '[lworks_profile]', __( 'Standalone member notification settings', 'littleworks-of-mercy' ) );
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Current state', 'littleworks-of-mercy' ) . '</h2>';
		echo '<ul>';
		echo '<li>' . esc_html( sprintf( __( '%d groups configured', 'littleworks-of-mercy' ), count( $groups ) ) ) . '</li>';
		echo '<li>' . esc_html( sprintf( __( '%d pending registrations', 'littleworks-of-mercy' ), count( $pending ) ) ) . '</li>';
		echo '</ul>';

		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=lworks-groups' ) ) . '">' . esc_html__( 'Manage groups', 'littleworks-of-mercy' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Render group management.
	 *
	 * @return void
	 */
	public static function render_groups_page() {
		$groups        = LWorks_Repository::get_groups();
		$editing_group = null;

		if ( isset( $_GET['group_id'] ) ) {
			$editing_group = LWorks_Repository::get_group( absint( wp_unslash( $_GET['group_id'] ) ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'littleWORKS Groups', 'littleworks-of-mercy' ) . '</h1>';
		self::render_admin_notice();

		echo '<h2>' . esc_html( $editing_group ? __( 'Edit group', 'littleworks-of-mercy' ) : __( 'Add group', 'littleworks-of-mercy' ) ) . '</h2>';
		self::render_group_form( $editing_group );

		echo '<h2>' . esc_html__( 'Existing groups', 'littleworks-of-mercy' ) . '</h2>';
		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'No groups have been created yet.', 'littleworks-of-mercy' ) . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr><th>' . esc_html__( 'Name', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Invite code', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Invite link', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Status', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Actions', 'littleworks-of-mercy' ) . '</th></tr></thead><tbody>';
			foreach ( $groups as $group ) {
				$invite_url = self::group_invite_url( $group );
				echo '<tr>';
				echo '<td>' . esc_html( $group->name ) . '</td>';
				echo '<td><code>' . esc_html( $group->invite_code ) . '</code></td>';
				echo '<td>' . ( $invite_url ? '<code>' . esc_html( $invite_url ) . '</code>' : esc_html__( 'Set a registration page in Settings.', 'littleworks-of-mercy' ) ) . '</td>';
				echo '<td>' . esc_html( (int) $group->active ? __( 'Active', 'littleworks-of-mercy' ) : __( 'Inactive', 'littleworks-of-mercy' ) ) . '</td>';
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=lworks-groups&group_id=' . absint( $group->id ) ) ) . '">' . esc_html__( 'Edit', 'littleworks-of-mercy' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Assign coordinator', 'littleworks-of-mercy' ) . '</h2>';
		self::render_assign_coordinator_form( $groups );
		echo '</div>';
	}

	/**
	 * Render pending registrations.
	 *
	 * @return void
	 */
	public static function render_pending_page() {
		$pending = LWorks_Repository::get_pending_memberships();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Pending Registrations', 'littleworks-of-mercy' ) . '</h1>';
		self::render_admin_notice();

		if ( empty( $pending ) ) {
			echo '<p>' . esc_html__( 'There are no pending registrations.', 'littleworks-of-mercy' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Name', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Email', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Group', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Connection note', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Actions', 'littleworks-of-mercy' ) . '</th></tr></thead><tbody>';

		foreach ( $pending as $membership ) {
			$user = get_user_by( 'id', $membership->user_id );
			if ( ! $user ) {
				continue;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $user->display_name ) . '</td>';
			echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
			echo '<td>' . esc_html( $membership->group_name ) . '</td>';
			echo '<td>' . esc_html( $membership->notes ) . '</td>';
			echo '<td>';
			self::render_admin_membership_form( $membership->id, 'approve', __( 'Approve', 'littleworks-of-mercy' ) );
			self::render_admin_membership_form( $membership->id, 'reject', __( 'Reject', 'littleworks-of-mercy' ) );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render recent audit events.
	 *
	 * @return void
	 */
	public static function render_audit_page() {
		$entries = LWorks_Repository::get_audit_entries( 200 );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Audit Log', 'littleworks-of-mercy' ) . '</h1>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No audit entries have been recorded yet.', 'littleworks-of-mercy' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Time', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Actor', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Event', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Object', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Message', 'littleworks-of-mercy' ) . '</th></tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			$actor = $entry->actor_user_id ? get_user_by( 'id', $entry->actor_user_id ) : null;
			echo '<tr>';
			echo '<td>' . esc_html( get_date_from_gmt( $entry->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td>';
			echo '<td>' . esc_html( $actor ? $actor->display_name : __( 'System', 'littleworks-of-mercy' ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry->event ) . '</code></td>';
			echo '<td>' . esc_html( $entry->object_type . '#' . $entry->object_id ) . '</td>';
			echo '<td>' . esc_html( $entry->message ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		$settings = LWorks_Repository::settings();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'littleWORKS Settings', 'littleworks-of-mercy' ) . '</h1>';
		self::render_admin_notice();
		?>
		<form method="post">
			<?php wp_nonce_field( 'lworks_save_settings', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_admin_action" value="save_settings">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Registration', 'littleworks-of-mercy' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_invite_code" value="1" <?php checked( ! empty( $settings['require_invite_code'] ) ); ?>>
							<?php esc_html_e( 'Require an invite code to register', 'littleworks-of-mercy' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="registration_min_seconds"><?php esc_html_e( 'Minimum signup seconds', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="number" min="0" max="3600" id="registration_min_seconds" name="registration_min_seconds" value="<?php echo esc_attr( absint( $settings['registration_min_seconds'] ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="registration_max_seconds"><?php esc_html_e( 'Maximum signup seconds', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="number" min="60" max="604800" id="registration_max_seconds" name="registration_max_seconds" value="<?php echo esc_attr( absint( $settings['registration_max_seconds'] ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'hCaptcha', 'littleworks-of-mercy' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_hcaptcha" value="1" <?php checked( ! empty( $settings['enable_hcaptcha'] ) ); ?>> <?php esc_html_e( 'Enable hCaptcha on registration', 'littleworks-of-mercy' ); ?></label>
						<p><input type="text" class="regular-text" name="hcaptcha_site_key" placeholder="<?php esc_attr_e( 'Site key', 'littleworks-of-mercy' ); ?>" value="<?php echo esc_attr( $settings['hcaptcha_site_key'] ); ?>"></p>
						<p><input type="password" class="regular-text" name="hcaptcha_secret_key" placeholder="<?php esc_attr_e( 'Secret key', 'littleworks-of-mercy' ); ?>" value="<?php echo esc_attr( $settings['hcaptcha_secret_key'] ); ?>"></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Google reCAPTCHA', 'littleworks-of-mercy' ); ?></th>
					<td>
						<label><input type="checkbox" name="enable_recaptcha" value="1" <?php checked( ! empty( $settings['enable_recaptcha'] ) ); ?>> <?php esc_html_e( 'Enable reCAPTCHA v2 checkbox on registration', 'littleworks-of-mercy' ); ?></label>
						<p><input type="text" class="regular-text" name="recaptcha_site_key" placeholder="<?php esc_attr_e( 'Site key', 'littleworks-of-mercy' ); ?>" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>"></p>
						<p><input type="password" class="regular-text" name="recaptcha_secret_key" placeholder="<?php esc_attr_e( 'Secret key', 'littleworks-of-mercy' ); ?>" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>"></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="member_remember_days"><?php esc_html_e( 'Member remember-me days', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="number" min="1" max="3650" id="member_remember_days" name="member_remember_days" value="<?php echo esc_attr( absint( $settings['member_remember_days'] ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="staff_remember_days"><?php esc_html_e( 'Coordinator/admin remember-me days', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="number" min="1" max="3650" id="staff_remember_days" name="staff_remember_days" value="<?php echo esc_attr( absint( $settings['staff_remember_days'] ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="notification_email"><?php esc_html_e( 'Fallback notification email', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="email" class="regular-text" id="notification_email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="registration_page_id"><?php esc_html_e( 'Registration page', 'littleworks-of-mercy' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'registration_page_id',
								'id'                => 'registration_page_id',
								'show_option_none'  => __( 'Select a page', 'littleworks-of-mercy' ),
								'option_none_value' => '0',
								'selected'          => absint( $settings['registration_page_id'] ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dashboard_page_id"><?php esc_html_e( 'Member dashboard page', 'littleworks-of-mercy' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'dashboard_page_id',
								'id'                => 'dashboard_page_id',
								'show_option_none'  => __( 'Select a page', 'littleworks-of-mercy' ),
								'option_none_value' => '0',
								'selected'          => absint( $settings['dashboard_page_id'] ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="coordinator_page_id"><?php esc_html_e( 'Coordinator page', 'littleworks-of-mercy' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'coordinator_page_id',
								'id'                => 'coordinator_page_id',
								'show_option_none'  => __( 'Select a page', 'littleworks-of-mercy' ),
								'option_none_value' => '0',
								'selected'          => absint( $settings['coordinator_page_id'] ),
							)
						);
						?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'littleworks-of-mercy' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'GitHub updates', 'littleworks-of-mercy' ); ?></h2>
		<p><?php esc_html_e( 'Updates are checked from https://github.com/stronganchor/private-social-network on the main branch and GitHub releases. For a private repository, define LWORKS_GITHUB_TOKEN in wp-config.php or provide it with the lworks_github_token filter.', 'littleworks-of-mercy' ); ?></p>
		<?php
		echo '</div>';
	}

	/**
	 * Save group action.
	 *
	 * @return void
	 */
	private static function handle_save_group() {
		self::verify_admin_nonce( 'lworks_save_group' );

		$group_id = LWorks_Repository::save_group(
			array(
				'id'          => isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0,
				'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'slug'        => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'invite_code' => isset( $_POST['invite_code'] ) ? wp_unslash( $_POST['invite_code'] ) : '',
				'active'      => isset( $_POST['active'] ) ? 1 : 0,
			)
		);

		LWorks_Repository::audit( get_current_user_id(), 'group', $group_id, 'group_saved', '' );
		self::redirect_with_notice( 'lworks-groups', 'saved' );
	}

	/**
	 * Assign coordinator action.
	 *
	 * @return void
	 */
	private static function handle_assign_coordinator() {
		self::verify_admin_nonce( 'lworks_assign_coordinator' );

		$email    = isset( $_POST['coordinator_email'] ) ? sanitize_email( wp_unslash( $_POST['coordinator_email'] ) ) : '';
		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$user     = get_user_by( 'email', $email );
		$group    = LWorks_Repository::get_group( $group_id );

		if ( ! $user || ! $group ) {
			self::redirect_with_notice( 'lworks-groups', 'missing_user_or_group' );
		}

		$user->add_role( 'lworks_coordinator' );
		$membership_id = LWorks_Repository::save_membership( $group_id, $user->ID, 'active', 'coordinator', '' );
		LWorks_Repository::approve_membership( $membership_id, get_current_user_id() );
		LWorks_Repository::audit( get_current_user_id(), 'membership', $membership_id, 'coordinator_assigned', $email );

		self::redirect_with_notice( 'lworks-groups', 'coordinator_assigned' );
	}

	/**
	 * Admin approve/reject action.
	 *
	 * @return void
	 */
	private static function handle_membership_action() {
		self::verify_admin_nonce( 'lworks_admin_membership_action' );

		$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
		$decision      = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$membership    = LWorks_Repository::get_membership( $membership_id );

		if ( ! $membership ) {
			self::redirect_with_notice( 'lworks-pending', 'missing_membership' );
		}

		if ( 'approve' === $decision ) {
			LWorks_Repository::approve_membership( $membership_id, get_current_user_id() );
			LWorks_Repository::audit( get_current_user_id(), 'membership', $membership_id, 'membership_approved', '' );
			self::notify_user_approved( $membership->user_id );
			self::redirect_with_notice( 'lworks-pending', 'approved' );
		}

		if ( 'reject' === $decision ) {
			LWorks_Repository::reject_membership( $membership_id, get_current_user_id() );
			LWorks_Repository::audit( get_current_user_id(), 'membership', $membership_id, 'membership_rejected', '' );
			self::redirect_with_notice( 'lworks-pending', 'rejected' );
		}

		self::redirect_with_notice( 'lworks-pending', 'invalid_decision' );
	}

	/**
	 * Save settings action.
	 *
	 * @return void
	 */
	private static function handle_save_settings() {
		self::verify_admin_nonce( 'lworks_save_settings' );

		LWorks_Repository::save_settings(
			array(
				'require_invite_code' => isset( $_POST['require_invite_code'] ) ? 1 : 0,
				'registration_min_seconds' => isset( $_POST['registration_min_seconds'] ) ? min( 3600, max( 0, absint( $_POST['registration_min_seconds'] ) ) ) : 4,
				'registration_max_seconds' => isset( $_POST['registration_max_seconds'] ) ? min( 604800, max( 60, absint( $_POST['registration_max_seconds'] ) ) ) : 86400,
				'enable_hcaptcha'    => isset( $_POST['enable_hcaptcha'] ) ? 1 : 0,
				'hcaptcha_site_key'  => isset( $_POST['hcaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hcaptcha_site_key'] ) ) : '',
				'hcaptcha_secret_key' => isset( $_POST['hcaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hcaptcha_secret_key'] ) ) : '',
				'enable_recaptcha'   => isset( $_POST['enable_recaptcha'] ) ? 1 : 0,
				'recaptcha_site_key' => isset( $_POST['recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ) ) : '',
				'recaptcha_secret_key' => isset( $_POST['recaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ) ) : '',
				'member_remember_days' => isset( $_POST['member_remember_days'] ) ? min( 3650, max( 1, absint( $_POST['member_remember_days'] ) ) ) : 180,
				'staff_remember_days'  => isset( $_POST['staff_remember_days'] ) ? min( 3650, max( 1, absint( $_POST['staff_remember_days'] ) ) ) : 30,
				'notification_email'   => isset( $_POST['notification_email'] ) ? sanitize_email( wp_unslash( $_POST['notification_email'] ) ) : get_option( 'admin_email' ),
				'registration_page_id' => isset( $_POST['registration_page_id'] ) ? absint( $_POST['registration_page_id'] ) : 0,
				'dashboard_page_id'    => isset( $_POST['dashboard_page_id'] ) ? absint( $_POST['dashboard_page_id'] ) : 0,
				'coordinator_page_id'  => isset( $_POST['coordinator_page_id'] ) ? absint( $_POST['coordinator_page_id'] ) : 0,
			)
		);

		self::redirect_with_notice( 'lworks-settings', 'saved' );
	}

	/**
	 * Render group form.
	 *
	 * @param object|null $group Group.
	 * @return void
	 */
	private static function render_group_form( $group = null ) {
		?>
		<form method="post">
			<?php wp_nonce_field( 'lworks_save_group', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_admin_action" value="save_group">
			<input type="hidden" name="group_id" value="<?php echo esc_attr( $group ? $group->id : 0 ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lworks_group_name"><?php esc_html_e( 'Name', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="text" class="regular-text" id="lworks_group_name" name="name" required value="<?php echo esc_attr( $group ? $group->name : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lworks_group_slug"><?php esc_html_e( 'Slug', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="text" class="regular-text" id="lworks_group_slug" name="slug" value="<?php echo esc_attr( $group ? $group->slug : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lworks_invite_code"><?php esc_html_e( 'Invite code', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="text" class="regular-text" id="lworks_invite_code" name="invite_code" value="<?php echo esc_attr( $group ? $group->invite_code : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="lworks_description"><?php esc_html_e( 'Description', 'littleworks-of-mercy' ); ?></label></th>
					<td><textarea class="large-text" id="lworks_description" name="description" rows="4"><?php echo esc_textarea( $group ? $group->description : '' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'littleworks-of-mercy' ); ?></th>
					<td><label><input type="checkbox" name="active" value="1" <?php checked( ! $group || (int) $group->active ); ?>> <?php esc_html_e( 'Allow registration and member access for this group', 'littleworks-of-mercy' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( $group ? __( 'Update group', 'littleworks-of-mercy' ) : __( 'Create group', 'littleworks-of-mercy' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render coordinator assignment form.
	 *
	 * @param array $groups Groups.
	 * @return void
	 */
	private static function render_assign_coordinator_form( $groups ) {
		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'Create a group before assigning coordinators.', 'littleworks-of-mercy' ) . '</p>';
			return;
		}

		?>
		<form method="post">
			<?php wp_nonce_field( 'lworks_assign_coordinator', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_admin_action" value="assign_coordinator">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="coordinator_email"><?php esc_html_e( 'Existing user email', 'littleworks-of-mercy' ); ?></label></th>
					<td><input type="email" class="regular-text" id="coordinator_email" name="coordinator_email" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="coordinator_group_id"><?php esc_html_e( 'Group', 'littleworks-of-mercy' ); ?></label></th>
					<td>
						<select id="coordinator_group_id" name="group_id" required>
							<?php foreach ( $groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group->id ); ?>"><?php echo esc_html( $group->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Assign coordinator', 'littleworks-of-mercy' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render admin membership action form.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $decision Decision.
	 * @param string $label Label.
	 * @return void
	 */
	private static function render_admin_membership_form( $membership_id, $decision, $label ) {
		echo '<form method="post" style="display:inline-block;margin-right:6px;">';
		wp_nonce_field( 'lworks_admin_membership_action', 'lworks_nonce' );
		echo '<input type="hidden" name="lworks_admin_action" value="membership_action">';
		echo '<input type="hidden" name="membership_id" value="' . esc_attr( $membership_id ) . '">';
		echo '<input type="hidden" name="decision" value="' . esc_attr( $decision ) . '">';
		submit_button( $label, 'approve' === $decision ? 'primary small' : 'secondary small', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render shortcode row.
	 *
	 * @param string $shortcode Shortcode.
	 * @param string $description Description.
	 * @return void
	 */
	private static function render_shortcode_row( $shortcode, $description ) {
		echo '<tr><td><code>' . esc_html( $shortcode ) . '</code></td><td>' . esc_html( $description ) . '</td></tr>';
	}

	/**
	 * Build an invite URL for a group.
	 *
	 * @param object $group Group row.
	 * @return string
	 */
	private static function group_invite_url( $group ) {
		$settings             = LWorks_Repository::settings();
		$registration_page_id = isset( $settings['registration_page_id'] ) ? absint( $settings['registration_page_id'] ) : 0;

		if ( ! $registration_page_id || empty( $group->invite_code ) ) {
			return '';
		}

		$url = get_permalink( $registration_page_id );
		if ( ! $url ) {
			return '';
		}

		return add_query_arg( 'invite', $group->invite_code, $url );
	}

	/**
	 * Verify an admin nonce.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function verify_admin_nonce( $action ) {
		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'littleworks-of-mercy' ) );
		}
	}

	/**
	 * Redirect to a plugin admin page with a notice code.
	 *
	 * @param string $page Page slug.
	 * @param string $notice Notice code.
	 * @return void
	 */
	private static function redirect_with_notice( $page, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => $page,
					'lworks_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin notice from URL code.
	 *
	 * @return void
	 */
	private static function render_admin_notice() {
		if ( empty( $_GET['lworks_notice'] ) ) {
			return;
		}

		$code     = sanitize_key( wp_unslash( $_GET['lworks_notice'] ) );
		$messages = array(
			'saved'                => __( 'Saved.', 'littleworks-of-mercy' ),
			'coordinator_assigned' => __( 'Coordinator assigned.', 'littleworks-of-mercy' ),
			'approved'             => __( 'Registration approved.', 'littleworks-of-mercy' ),
			'rejected'             => __( 'Registration rejected.', 'littleworks-of-mercy' ),
			'missing_user_or_group' => __( 'That user or group could not be found.', 'littleworks-of-mercy' ),
			'missing_membership'   => __( 'That registration could not be found.', 'littleworks-of-mercy' ),
			'invalid_decision'     => __( 'The requested action was not recognized.', 'littleworks-of-mercy' ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
	}

	/**
	 * Notify a user after admin approval.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function notify_user_approved( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Your littleWORKS account was approved', 'littleworks-of-mercy' );
		$body    = __( 'Your account was approved. You can now log in to the private member area.', 'littleworks-of-mercy' ) . "\n\n" . LWorks_Repository::get_page_url( 'dashboard_page_id' );

		wp_mail( $user->user_email, $subject, $body );
	}
}
