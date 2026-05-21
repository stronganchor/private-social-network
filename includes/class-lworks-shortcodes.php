<?php
/**
 * Frontend shortcodes and form handlers.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Shortcodes {
	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'lworks_registration', array( __CLASS__, 'registration_shortcode' ) );
		add_shortcode( 'lworks_login', array( __CLASS__, 'login_shortcode' ) );
		add_shortcode( 'lworks_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
		add_shortcode( 'lworks_request_board', array( __CLASS__, 'dashboard_shortcode' ) );
		add_shortcode( 'lworks_coordinator', array( __CLASS__, 'coordinator_shortcode' ) );
	}

	/**
	 * Registration form.
	 *
	 * @return string
	 */
	public static function registration_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( is_user_logged_in() ) {
			return self::notice( __( 'You are already logged in.', 'littleworks-of-mercy' ), 'info' );
		}

		$groups  = LWorks_Repository::get_groups( true );
		$message = '';

		if ( self::is_post_action( 'lworks_register' ) ) {
			$message = self::handle_registration();
		}

		ob_start();
		echo '<div class="lworks lworks-registration">';
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( empty( $groups ) ) {
			echo self::notice( __( 'Registration is not open yet. Please check back later.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return ob_get_clean();
		}

		?>
		<form method="post" class="lworks-form">
			<?php wp_nonce_field( 'lworks_register', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_action" value="lworks_register">

			<div class="lworks-grid lworks-grid-2">
				<label>
					<span><?php esc_html_e( 'First name', 'littleworks-of-mercy' ); ?></span>
					<input type="text" name="first_name" required autocomplete="given-name">
				</label>
				<label>
					<span><?php esc_html_e( 'Last name', 'littleworks-of-mercy' ); ?></span>
					<input type="text" name="last_name" required autocomplete="family-name">
				</label>
			</div>

			<label>
				<span><?php esc_html_e( 'Email address', 'littleworks-of-mercy' ); ?></span>
				<input type="email" name="email" required autocomplete="email">
			</label>

			<label>
				<span><?php esc_html_e( 'Phone number', 'littleworks-of-mercy' ); ?></span>
				<input type="tel" name="phone" autocomplete="tel">
			</label>

			<div class="lworks-grid lworks-grid-2">
				<label>
					<span><?php esc_html_e( 'Password', 'littleworks-of-mercy' ); ?></span>
					<input type="password" name="password" required autocomplete="new-password" minlength="10">
				</label>
				<label>
					<span><?php esc_html_e( 'Confirm password', 'littleworks-of-mercy' ); ?></span>
					<input type="password" name="password_confirm" required autocomplete="new-password" minlength="10">
				</label>
			</div>

			<label>
				<span><?php esc_html_e( 'Parish or community', 'littleworks-of-mercy' ); ?></span>
				<select name="group_id" required>
					<option value=""><?php esc_html_e( 'Choose one', 'littleworks-of-mercy' ); ?></option>
					<?php foreach ( $groups as $group ) : ?>
						<option value="<?php echo esc_attr( $group->id ); ?>"><?php echo esc_html( $group->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span><?php esc_html_e( 'Invite code', 'littleworks-of-mercy' ); ?></span>
				<input type="text" name="invite_code" inputmode="latin" autocomplete="off">
			</label>

			<label>
				<span><?php esc_html_e( 'Connection to this community', 'littleworks-of-mercy' ); ?></span>
				<textarea name="connection_note" rows="4"></textarea>
			</label>

			<button type="submit" class="lworks-button"><?php esc_html_e( 'Request access', 'littleworks-of-mercy' ); ?></button>
		</form>
		<?php

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Login form.
	 *
	 * @return string
	 */
	public static function login_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( is_user_logged_in() ) {
			return '<div class="lworks">' . self::notice( __( 'You are logged in.', 'littleworks-of-mercy' ), 'success' ) . '</div>';
		}

		ob_start();
		echo '<div class="lworks lworks-login">';
		wp_login_form(
			array(
				'remember'       => true,
				'value_remember' => true,
				'redirect'       => LWorks_Repository::get_page_url( 'dashboard_page_id' ),
			)
		);
		echo '<p class="lworks-help"><a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Forgot your password?', 'littleworks-of-mercy' ) . '</a></p>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Member dashboard and request board.
	 *
	 * @return string
	 */
	public static function dashboard_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return self::login_shortcode();
		}

		if ( ! current_user_can( LWORKS_CAP_VIEW ) ) {
			return '<div class="lworks">' . self::notice( __( 'Your account does not have access to the member area.', 'littleworks-of-mercy' ), 'error' ) . '</div>';
		}

		$user_id     = get_current_user_id();
		$message     = self::handle_dashboard_actions();
		$memberships = LWorks_Repository::get_user_memberships( $user_id, array( 'active' ) );
		$pending     = LWorks_Repository::get_user_memberships( $user_id, array( 'pending' ) );

		ob_start();
		echo '<div class="lworks lworks-dashboard">';
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( empty( $memberships ) ) {
			if ( ! empty( $pending ) ) {
				echo self::notice( __( 'Your registration is waiting for coordinator approval.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo self::notice( __( 'You are not assigned to any active parish or community yet.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			return ob_get_clean();
		}

		self::render_request_form( $memberships );
		self::render_request_feed( $user_id );

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Coordinator pending registration review.
	 *
	 * @return string
	 */
	public static function coordinator_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return self::login_shortcode();
		}

		if ( ! current_user_can( LWORKS_CAP_MANAGE_ASSIGNED ) && ! current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			return '<div class="lworks">' . self::notice( __( 'You do not have coordinator access.', 'littleworks-of-mercy' ), 'error' ) . '</div>';
		}

		$message = self::handle_coordinator_actions();
		$pending = LWorks_Repository::get_pending_memberships( get_current_user_id() );

		ob_start();
		echo '<div class="lworks lworks-coordinator">';
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<h2>' . esc_html__( 'Pending registrations', 'littleworks-of-mercy' ) . '</h2>';

		if ( empty( $pending ) ) {
			echo self::notice( __( 'There are no pending registrations for your groups.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			return ob_get_clean();
		}

		echo '<div class="lworks-list">';
		foreach ( $pending as $membership ) {
			$user = get_user_by( 'id', $membership->user_id );
			if ( ! $user ) {
				continue;
			}

			echo '<article class="lworks-card">';
			echo '<h3>' . esc_html( $user->display_name ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Group:', 'littleworks-of-mercy' ) . '</strong> ' . esc_html( $membership->group_name ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Email:', 'littleworks-of-mercy' ) . '</strong> <a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></p>';
			$phone = get_user_meta( $user->ID, 'lworks_phone', true );
			if ( $phone ) {
				echo '<p><strong>' . esc_html__( 'Phone:', 'littleworks-of-mercy' ) . '</strong> ' . esc_html( $phone ) . '</p>';
			}
			if ( $membership->notes ) {
				echo '<p><strong>' . esc_html__( 'Connection:', 'littleworks-of-mercy' ) . '</strong> ' . esc_html( $membership->notes ) . '</p>';
			}
			echo '<div class="lworks-actions">';
			self::render_membership_action_form( $membership->id, 'approve', __( 'Approve', 'littleworks-of-mercy' ) );
			self::render_membership_action_form( $membership->id, 'reject', __( 'Reject', 'littleworks-of-mercy' ) );
			echo '</div>';
			echo '</article>';
		}
		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Handle registration submission.
	 *
	 * @return string
	 */
	private static function handle_registration() {
		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_register' ) ) {
			return self::notice( __( 'The registration form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		$settings         = LWorks_Repository::settings();
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$password_confirm = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		$group_id         = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$invite_code      = isset( $_POST['invite_code'] ) ? LWorks_Repository::sanitize_invite_code( $_POST['invite_code'] ) : '';
		$connection_note  = isset( $_POST['connection_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['connection_note'] ) ) : '';

		if ( ! is_email( $email ) || '' === $first_name || '' === $last_name ) {
			return self::notice( __( 'Please enter your name and a valid email address.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( email_exists( $email ) ) {
			return self::notice( __( 'An account already exists for that email address. Please log in or reset your password.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( strlen( $password ) < 10 || $password !== $password_confirm ) {
			return self::notice( __( 'Please enter matching passwords with at least 10 characters.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( $invite_code ) {
			$invite_group = LWorks_Repository::get_group_by_invite_code( $invite_code );
			if ( ! $invite_group ) {
				return self::notice( __( 'That invite code was not recognized.', 'littleworks-of-mercy' ), 'error' );
			}
			$group_id = (int) $invite_group->id;
		} elseif ( ! empty( $settings['require_invite_code'] ) ) {
			return self::notice( __( 'An invite code is required for registration.', 'littleworks-of-mercy' ), 'error' );
		}

		$group = LWorks_Repository::get_group( $group_id );
		if ( ! $group || ! (int) $group->active ) {
			return self::notice( __( 'Please choose an active parish or community.', 'littleworks-of-mercy' ), 'error' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => LWorks_Repository::username_from_email( $email ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => 'lworks_member',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return self::notice( $user_id->get_error_message(), 'error' );
		}

		update_user_meta( $user_id, 'lworks_phone', $phone );
		$membership_id = LWorks_Repository::save_membership( $group_id, $user_id, 'pending', 'member', $connection_note );

		LWorks_Repository::audit( $user_id, 'membership', $membership_id, 'registration_requested', 'Registration requested for ' . $group->name );
		self::notify_registration_requested( $user_id, $group, $connection_note );

		return self::notice( __( 'Your request has been received. A coordinator will review it before you can see the private member area.', 'littleworks-of-mercy' ), 'success' );
	}

	/**
	 * Handle dashboard postbacks.
	 *
	 * @return string
	 */
	private static function handle_dashboard_actions() {
		if ( empty( $_POST['lworks_action'] ) ) {
			return '';
		}

		$action = sanitize_key( wp_unslash( $_POST['lworks_action'] ) );

		if ( 'lworks_create_request' === $action ) {
			return self::handle_create_request();
		}

		if ( 'lworks_create_response' === $action ) {
			return self::handle_create_response();
		}

		if ( 'lworks_update_request_status' === $action ) {
			return self::handle_update_request_status();
		}

		return '';
	}

	/**
	 * Create request handler.
	 *
	 * @return string
	 */
	private static function handle_create_request() {
		if ( ! current_user_can( LWORKS_CAP_POST ) ) {
			return self::notice( __( 'You do not have permission to post.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_create_request' ) ) {
			return self::notice( __( 'The request form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		$user_id      = get_current_user_id();
		$group_id     = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$request_type = isset( $_POST['request_type'] ) ? LWorks_Repository::sanitize_request_type( wp_unslash( $_POST['request_type'] ) ) : 'prayer';
		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content      = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( ! LWorks_Repository::user_can_access_group( $user_id, $group_id ) ) {
			return self::notice( __( 'You cannot post to that group.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) ) {
			return self::notice( __( 'Please enter a title and details.', 'littleworks-of-mercy' ), 'error' );
		}

		$request_id = LWorks_Repository::create_request(
			array(
				'group_id'      => $group_id,
				'user_id'       => $user_id,
				'request_type'  => $request_type,
				'title'         => $title,
				'content'       => $content,
			)
		);

		LWorks_Repository::audit( $user_id, 'request', $request_id, 'request_created', $title );
		self::notify_new_request( $request_id );

		return self::notice( __( 'Your request was posted for your group.', 'littleworks-of-mercy' ), 'success' );
	}

	/**
	 * Create response handler.
	 *
	 * @return string
	 */
	private static function handle_create_response() {
		if ( ! current_user_can( LWORKS_CAP_RESPOND ) ) {
			return self::notice( __( 'You do not have permission to respond.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_create_response' ) ) {
			return self::notice( __( 'The response form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		$user_id       = get_current_user_id();
		$request_id    = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		$response_type = isset( $_POST['response_type'] ) ? LWorks_Repository::sanitize_response_type( wp_unslash( $_POST['response_type'] ) ) : 'message';
		$content       = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$request       = LWorks_Repository::get_request( $request_id );

		if ( ! $request || ! LWorks_Repository::user_can_access_group( $user_id, $request->group_id ) ) {
			return self::notice( __( 'You cannot respond to that request.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( 'prayer' !== $response_type && '' === trim( wp_strip_all_tags( $content ) ) ) {
			return self::notice( __( 'Please enter a short message.', 'littleworks-of-mercy' ), 'error' );
		}

		$response_id = LWorks_Repository::create_response(
			array(
				'request_id'    => $request_id,
				'user_id'       => $user_id,
				'response_type' => $response_type,
				'content'       => $content,
			)
		);

		if ( 'help' === $response_type && 'open' === $request->status ) {
			LWorks_Repository::update_request_status( $request_id, 'responding' );
		}

		LWorks_Repository::audit( $user_id, 'response', $response_id, 'response_created', 'Response to request ' . $request_id );
		self::notify_request_owner( $request, $response_type );

		return self::notice( __( 'Your response was saved.', 'littleworks-of-mercy' ), 'success' );
	}

	/**
	 * Request status handler.
	 *
	 * @return string
	 */
	private static function handle_update_request_status() {
		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_update_request_status' ) ) {
			return self::notice( __( 'The status form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		$user_id    = get_current_user_id();
		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		$status     = isset( $_POST['status'] ) ? LWorks_Repository::sanitize_request_status( wp_unslash( $_POST['status'] ) ) : 'open';
		$request    = LWorks_Repository::get_request( $request_id );

		if ( ! $request ) {
			return self::notice( __( 'That request could not be found.', 'littleworks-of-mercy' ), 'error' );
		}

		$can_update = ( (int) $request->user_id === $user_id ) || LWorks_Repository::user_can_manage_group( $user_id, $request->group_id );
		if ( ! $can_update ) {
			return self::notice( __( 'You cannot update that request.', 'littleworks-of-mercy' ), 'error' );
		}

		LWorks_Repository::update_request_status( $request_id, $status );
		LWorks_Repository::audit( $user_id, 'request', $request_id, 'request_status_updated', $status );

		return self::notice( __( 'The request status was updated.', 'littleworks-of-mercy' ), 'success' );
	}

	/**
	 * Coordinator approval/rejection handler.
	 *
	 * @return string
	 */
	private static function handle_coordinator_actions() {
		if ( ! self::is_post_action( 'lworks_membership_action' ) ) {
			return '';
		}

		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_membership_action' ) ) {
			return self::notice( __( 'The review form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		$membership_id = isset( $_POST['membership_id'] ) ? absint( $_POST['membership_id'] ) : 0;
		$decision      = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$membership    = LWorks_Repository::get_membership( $membership_id );

		if ( ! $membership || ! LWorks_Repository::user_can_manage_group( get_current_user_id(), $membership->group_id ) ) {
			return self::notice( __( 'You cannot review that registration.', 'littleworks-of-mercy' ), 'error' );
		}

		if ( 'approve' === $decision ) {
			LWorks_Repository::approve_membership( $membership_id, get_current_user_id() );
			LWorks_Repository::audit( get_current_user_id(), 'membership', $membership_id, 'membership_approved', '' );
			self::notify_user_approved( $membership->user_id );

			return self::notice( __( 'The registration was approved.', 'littleworks-of-mercy' ), 'success' );
		}

		if ( 'reject' === $decision ) {
			LWorks_Repository::reject_membership( $membership_id, get_current_user_id() );
			LWorks_Repository::audit( get_current_user_id(), 'membership', $membership_id, 'membership_rejected', '' );

			return self::notice( __( 'The registration was rejected.', 'littleworks-of-mercy' ), 'success' );
		}

		return '';
	}

	/**
	 * Render private request form.
	 *
	 * @param array $memberships Active memberships.
	 * @return void
	 */
	private static function render_request_form( $memberships ) {
		$request_types = LWorks_Repository::request_types();
		?>
		<section class="lworks-panel">
			<h2><?php esc_html_e( 'Share a request', 'littleworks-of-mercy' ); ?></h2>
			<form method="post" class="lworks-form">
				<?php wp_nonce_field( 'lworks_create_request', 'lworks_nonce' ); ?>
				<input type="hidden" name="lworks_action" value="lworks_create_request">

				<div class="lworks-grid lworks-grid-2">
					<label>
						<span><?php esc_html_e( 'Group', 'littleworks-of-mercy' ); ?></span>
						<select name="group_id" required>
							<?php foreach ( $memberships as $membership ) : ?>
								<?php if ( (int) $membership->group_active ) : ?>
									<option value="<?php echo esc_attr( $membership->group_id ); ?>"><?php echo esc_html( $membership->group_name ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Type', 'littleworks-of-mercy' ); ?></span>
						<select name="request_type" required>
							<?php foreach ( $request_types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<label>
					<span><?php esc_html_e( 'Title', 'littleworks-of-mercy' ); ?></span>
					<input type="text" name="title" required maxlength="191">
				</label>

				<label>
					<span><?php esc_html_e( 'Details', 'littleworks-of-mercy' ); ?></span>
					<textarea name="content" rows="5" required></textarea>
				</label>

				<button type="submit" class="lworks-button"><?php esc_html_e( 'Post request', 'littleworks-of-mercy' ); ?></button>
			</form>
		</section>
		<?php
	}

	/**
	 * Render private request feed.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function render_request_feed( $user_id ) {
		$requests  = LWorks_Repository::get_requests_for_user( $user_id );
		$responses = LWorks_Repository::get_responses_for_requests( wp_list_pluck( $requests, 'id' ) );
		$types     = LWorks_Repository::request_types();
		$statuses  = LWorks_Repository::request_statuses();

		echo '<section class="lworks-feed">';
		echo '<h2>' . esc_html__( 'Latest requests', 'littleworks-of-mercy' ) . '</h2>';

		if ( empty( $requests ) ) {
			echo self::notice( __( 'There are no requests in your groups yet.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</section>';
			return;
		}

		foreach ( $requests as $request ) {
			$author = get_user_by( 'id', $request->user_id );
			echo '<article class="lworks-request lworks-card">';
			echo '<div class="lworks-card-header">';
			echo '<div>';
			echo '<p class="lworks-meta">' . esc_html( $request->group_name ) . ' · ' . esc_html( isset( $types[ $request->request_type ] ) ? $types[ $request->request_type ] : $request->request_type ) . '</p>';
			echo '<h3>' . esc_html( $request->title ) . '</h3>';
			echo '</div>';
			echo '<span class="lworks-status lworks-status-' . esc_attr( $request->status ) . '">' . esc_html( isset( $statuses[ $request->status ] ) ? $statuses[ $request->status ] : $request->status ) . '</span>';
			echo '</div>';
			echo '<p class="lworks-meta">' . esc_html( $author ? $author->display_name : __( 'Member', 'littleworks-of-mercy' ) ) . ' · ' . esc_html( get_date_from_gmt( $request->created_at, get_option( 'date_format' ) ) ) . '</p>';
			echo '<div class="lworks-content">' . wp_kses_post( wpautop( $request->content ) ) . '</div>';

			self::render_request_responses( $request, isset( $responses[ $request->id ] ) ? $responses[ $request->id ] : array() );
			self::render_response_forms( $request );
			self::render_status_form( $request );

			echo '</article>';
		}

		echo '</section>';
	}

	/**
	 * Render existing responses.
	 *
	 * @param object $request Request row.
	 * @param array  $responses Responses.
	 * @return void
	 */
	private static function render_request_responses( $request, $responses ) {
		if ( empty( $responses ) ) {
			return;
		}

		echo '<div class="lworks-responses">';
		foreach ( $responses as $response ) {
			$user = get_user_by( 'id', $response->user_id );
			echo '<div class="lworks-response">';
			echo '<strong>' . esc_html( $user ? $user->display_name : __( 'Member', 'littleworks-of-mercy' ) ) . '</strong> ';

			if ( 'prayer' === $response->response_type ) {
				echo esc_html__( 'is praying for this.', 'littleworks-of-mercy' );
			} elseif ( 'help' === $response->response_type ) {
				echo esc_html__( 'can help:', 'littleworks-of-mercy' ) . ' ' . wp_kses_post( $response->content );
			} else {
				echo wp_kses_post( $response->content );
			}

			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render response controls.
	 *
	 * @param object $request Request row.
	 * @return void
	 */
	private static function render_response_forms( $request ) {
		?>
		<div class="lworks-response-controls">
			<form method="post" class="lworks-inline-form">
				<?php wp_nonce_field( 'lworks_create_response', 'lworks_nonce' ); ?>
				<input type="hidden" name="lworks_action" value="lworks_create_response">
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id ); ?>">
				<input type="hidden" name="response_type" value="prayer">
				<button type="submit" class="lworks-button lworks-button-secondary"><?php esc_html_e( 'I will pray', 'littleworks-of-mercy' ); ?></button>
			</form>
			<form method="post" class="lworks-inline-form lworks-help-form">
				<?php wp_nonce_field( 'lworks_create_response', 'lworks_nonce' ); ?>
				<input type="hidden" name="lworks_action" value="lworks_create_response">
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id ); ?>">
				<input type="hidden" name="response_type" value="help">
				<input type="text" name="content" placeholder="<?php esc_attr_e( 'How can you help?', 'littleworks-of-mercy' ); ?>" required>
				<button type="submit" class="lworks-button lworks-button-secondary"><?php esc_html_e( 'Offer help', 'littleworks-of-mercy' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Render status controls for owners/coordinators.
	 *
	 * @param object $request Request row.
	 * @return void
	 */
	private static function render_status_form( $request ) {
		$user_id = get_current_user_id();
		if ( (int) $request->user_id !== $user_id && ! LWorks_Repository::user_can_manage_group( $user_id, $request->group_id ) ) {
			return;
		}

		$statuses = LWorks_Repository::request_statuses();
		?>
		<form method="post" class="lworks-inline-form lworks-status-form">
			<?php wp_nonce_field( 'lworks_update_request_status', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_action" value="lworks_update_request_status">
			<input type="hidden" name="request_id" value="<?php echo esc_attr( $request->id ); ?>">
			<label>
				<span><?php esc_html_e( 'Status', 'littleworks-of-mercy' ); ?></span>
				<select name="status">
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $request->status, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit" class="lworks-button lworks-button-secondary"><?php esc_html_e( 'Update', 'littleworks-of-mercy' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render a coordinator membership action form.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $decision Decision.
	 * @param string $label Button label.
	 * @return void
	 */
	private static function render_membership_action_form( $membership_id, $decision, $label ) {
		?>
		<form method="post" class="lworks-inline-form">
			<?php wp_nonce_field( 'lworks_membership_action', 'lworks_nonce' ); ?>
			<input type="hidden" name="lworks_action" value="lworks_membership_action">
			<input type="hidden" name="membership_id" value="<?php echo esc_attr( $membership_id ); ?>">
			<input type="hidden" name="decision" value="<?php echo esc_attr( $decision ); ?>">
			<button type="submit" class="lworks-button <?php echo 'reject' === $decision ? 'lworks-button-danger' : ''; ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Notify coordinators/site admin of new registration.
	 *
	 * @param int    $user_id User ID.
	 * @param object $group Group row.
	 * @param string $connection_note Connection note.
	 * @return void
	 */
	private static function notify_registration_requested( $user_id, $group, $connection_note ) {
		$user       = get_user_by( 'id', $user_id );
		$settings   = LWorks_Repository::settings();
		$recipients = self::group_coordinator_emails( $group->id );

		if ( empty( $recipients ) && is_email( $settings['notification_email'] ) ) {
			$recipients[] = $settings['notification_email'];
		}

		if ( empty( $recipients ) || ! $user ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: group name. */
			__( 'New littleWORKS registration for %s', 'littleworks-of-mercy' ),
			$group->name
		);

		$body = sprintf(
			"%s\n\n%s\n%s\n\n%s\n%s",
			__( 'A new member requested access.', 'littleworks-of-mercy' ),
			$user->display_name,
			$user->user_email,
			__( 'Connection note:', 'littleworks-of-mercy' ),
			$connection_note
		);

		wp_mail( $recipients, $subject, $body );
	}

	/**
	 * Notify group members about a new request without exposing sensitive content.
	 *
	 * @param int $request_id Request ID.
	 * @return void
	 */
	private static function notify_new_request( $request_id ) {
		$request = LWorks_Repository::get_request( $request_id );
		if ( ! $request ) {
			return;
		}

		$recipients = self::active_group_member_emails( $request->group_id, $request->user_id );
		if ( empty( $recipients ) ) {
			return;
		}

		$subject = __( 'New littleWORKS request in your group', 'littleworks-of-mercy' );
		$body    = __( 'A new private request has been posted in one of your littleWORKS groups. Please log in to view it.', 'littleworks-of-mercy' ) . "\n\n" . LWorks_Repository::get_page_url( 'dashboard_page_id' );

		wp_mail( $recipients, $subject, $body );
	}

	/**
	 * Notify request owner about a response without exposing details.
	 *
	 * @param object $request Request row.
	 * @param string $response_type Response type.
	 * @return void
	 */
	private static function notify_request_owner( $request, $response_type ) {
		$user = get_user_by( 'id', $request->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Someone responded to your littleWORKS request', 'littleworks-of-mercy' );
		$body    = __( 'Someone responded to your private request. Please log in to view the response.', 'littleworks-of-mercy' ) . "\n\n" . LWorks_Repository::get_page_url( 'dashboard_page_id' );

		wp_mail( $user->user_email, $subject, $body );
	}

	/**
	 * Notify a user after approval.
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

	/**
	 * Get active group member emails.
	 *
	 * @param int $group_id Group ID.
	 * @param int $exclude_user_id User ID to exclude.
	 * @return array
	 */
	private static function active_group_member_emails( $group_id, $exclude_user_id = 0 ) {
		global $wpdb;

		$members = LWorks_Repository::table( 'group_members' );
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$members} WHERE group_id = %d AND status = 'active'",
				absint( $group_id )
			)
		);

		$emails = array();
		foreach ( $user_ids as $user_id ) {
			if ( absint( $exclude_user_id ) === absint( $user_id ) ) {
				continue;
			}

			$user = get_user_by( 'id', $user_id );
			if ( $user && is_email( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Get coordinator emails for a group.
	 *
	 * @param int $group_id Group ID.
	 * @return array
	 */
	private static function group_coordinator_emails( $group_id ) {
		global $wpdb;

		$members = LWorks_Repository::table( 'group_members' );
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$members} WHERE group_id = %d AND status = 'active' AND member_role = 'coordinator'",
				absint( $group_id )
			)
		);

		$emails = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && is_email( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Check posted action.
	 *
	 * @param string $action Action.
	 * @return bool
	 */
	private static function is_post_action( $action ) {
		return isset( $_POST['lworks_action'] ) && $action === sanitize_key( wp_unslash( $_POST['lworks_action'] ) );
	}

	/**
	 * Render a notice.
	 *
	 * @param string $message Message.
	 * @param string $type Type.
	 * @return string
	 */
	private static function notice( $message, $type = 'info' ) {
		return '<div class="lworks-notice lworks-notice-' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
	}
}
