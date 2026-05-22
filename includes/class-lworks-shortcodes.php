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
		add_shortcode( 'lworks_member_links', array( __CLASS__, 'member_links_shortcode' ) );
		add_shortcode( 'lworks_login', array( __CLASS__, 'login_shortcode' ) );
		add_shortcode( 'lworks_dashboard', array( __CLASS__, 'dashboard_shortcode' ) );
		add_shortcode( 'lworks_request_board', array( __CLASS__, 'dashboard_shortcode' ) );
		add_shortcode( 'lworks_coordinator', array( __CLASS__, 'coordinator_shortcode' ) );
		add_shortcode( 'lworks_profile', array( __CLASS__, 'profile_shortcode' ) );
	}

	/**
	 * Session-aware member links.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function member_links_shortcode( $atts = array() ) {
		LWorks_Plugin::use_frontend_assets();

		$atts = shortcode_atts(
			array(
				'wrap_class'      => '',
				'button_class'    => 'lworks-button',
				'secondary_class' => 'lworks-button lworks-button-secondary',
				'logout_class'    => '',
				'show_logout'     => '1',
				'show_coordinator' => '1',
			),
			$atts,
			'lworks_member_links'
		);

		$wrap_class      = trim( 'lworks-member-links ' . self::sanitize_class_list( $atts['wrap_class'] ) );
		$button_class    = self::sanitize_class_list( $atts['button_class'] );
		$secondary_class = self::sanitize_class_list( $atts['secondary_class'] );
		$logout_class    = self::sanitize_class_list( '' !== $atts['logout_class'] ? $atts['logout_class'] : $atts['secondary_class'] );
		$show_logout     = self::truthy_shortcode_value( $atts['show_logout'] );
		$show_coordinator = self::truthy_shortcode_value( $atts['show_coordinator'] );
		$links           = array();

		if ( is_user_logged_in() ) {
			$links[] = array(
				'url'   => LWorks_Repository::get_page_url( 'dashboard_page_id' ),
				'label' => __( 'Dashboard', 'littleworks-of-mercy' ),
				'class' => $button_class,
			);

			if ( $show_coordinator && self::current_user_can_use_staff_pages() ) {
				$coordinator_url = LWorks_Repository::get_configured_page_url( 'coordinator_page_id' );

				if ( $coordinator_url ) {
					$links[] = array(
						'url'   => $coordinator_url,
						'label' => __( 'Coordinator Review', 'littleworks-of-mercy' ),
						'class' => $secondary_class,
					);
				}
			}

			if ( $show_logout ) {
				$links[] = array(
					'url'   => wp_logout_url( home_url( '/' ) ),
					'label' => __( 'Sign out', 'littleworks-of-mercy' ),
					'class' => $logout_class,
				);
			}
		} else {
			$links[] = array(
				'url'   => LWorks_Repository::get_page_url( 'registration_page_id' ),
				'label' => __( 'Request access', 'littleworks-of-mercy' ),
				'class' => $button_class,
			);
			$links[] = array(
				'url'   => self::login_page_url(),
				'label' => __( 'Member sign in', 'littleworks-of-mercy' ),
				'class' => $secondary_class,
			);
		}

		ob_start();
		echo '<div class="' . esc_attr( $wrap_class ) . '">';
		foreach ( $links as $link ) {
			echo '<a class="' . esc_attr( $link['class'] ) . '" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Registration form.
	 *
	 * @return string
	 */
	public static function registration_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( is_user_logged_in() ) {
			return '<div class="lworks">' . self::notice( __( 'You are already logged in.', 'littleworks-of-mercy' ), 'info' ) . self::member_links_shortcode() . '</div>';
		}

		$groups              = LWorks_Repository::get_groups( true );
		$message             = '';
		$prefill_invite_code = isset( $_GET['invite'] ) ? LWorks_Repository::sanitize_invite_code( wp_unslash( $_GET['invite'] ) ) : '';
		$prefill_group       = $prefill_invite_code ? LWorks_Repository::get_group_by_invite_code( $prefill_invite_code ) : null;
		$settings            = LWorks_Repository::settings();

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
			<?php self::render_registration_antispam_fields( $settings ); ?>

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

			<?php if ( $prefill_group ) : ?>
				<input type="hidden" name="group_id" value="<?php echo esc_attr( $prefill_group->id ); ?>">
				<input type="hidden" name="invite_code" value="<?php echo esc_attr( $prefill_invite_code ); ?>">
				<p class="lworks-prefilled-group"><strong><?php esc_html_e( 'Parish or community:', 'littleworks-of-mercy' ); ?></strong> <?php echo esc_html( $prefill_group->name ); ?></p>
			<?php else : ?>
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
					<input type="text" name="invite_code" inputmode="latin" autocomplete="off" value="<?php echo esc_attr( $prefill_invite_code ); ?>">
				</label>
			<?php endif; ?>

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
			return '<div class="lworks">' . self::notice( __( 'You are logged in.', 'littleworks-of-mercy' ), 'success' ) . self::member_links_shortcode() . '</div>';
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
	 * Member profile and notification settings.
	 *
	 * @return string
	 */
	public static function profile_shortcode() {
		LWorks_Plugin::use_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return self::login_shortcode();
		}

		if ( ! current_user_can( LWORKS_CAP_VIEW ) ) {
			return '<div class="lworks">' . self::notice( __( 'Your account does not have access to member settings.', 'littleworks-of-mercy' ), 'error' ) . '</div>';
		}

		$message = '';
		if ( self::is_post_action( 'lworks_save_preferences' ) ) {
			$message = self::handle_save_preferences();
		}

		ob_start();
		echo '<div class="lworks lworks-profile">';
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		self::render_preferences_panel();
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
		self::render_member_header( 'dashboard' );
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
		self::render_preferences_panel();

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

		if ( ! self::current_user_can_use_staff_pages() ) {
			return '<div class="lworks">' . self::notice( __( 'You do not have coordinator access.', 'littleworks-of-mercy' ), 'error' ) . '</div>';
		}

		$message = self::handle_coordinator_actions();
		$pending = LWorks_Repository::get_pending_memberships( get_current_user_id() );

		ob_start();
		echo '<div class="lworks lworks-coordinator">';
		self::render_member_header( 'coordinator' );
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<h2>' . esc_html__( 'Pending registrations', 'littleworks-of-mercy' ) . '</h2>';

		if ( empty( $pending ) ) {
			echo self::notice( __( 'There are no pending registrations for your groups.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
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
		}

		self::render_coordinator_roster();
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Find the member login page, falling back to the WordPress login form.
	 *
	 * @return string
	 */
	private static function login_page_url() {
		$page = get_page_by_path( 'member-login' );

		if ( $page ) {
			$url = get_permalink( $page );
			if ( $url ) {
				return $url;
			}
		}

		return wp_login_url( LWorks_Repository::get_page_url( 'dashboard_page_id' ) );
	}

	/**
	 * Sanitize a space-separated class list.
	 *
	 * @param string $classes Class list.
	 * @return string
	 */
	private static function sanitize_class_list( $classes ) {
		$classes = preg_split( '/\s+/', (string) $classes );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	/**
	 * Render member/staff navigation for private pages.
	 *
	 * @param string $active Active section ID.
	 * @return void
	 */
	private static function render_member_header( $active = '' ) {
		$links = array();

		$dashboard_url = LWorks_Repository::get_configured_page_url( 'dashboard_page_id' );
		if ( $dashboard_url ) {
			$links[] = array(
				'id'    => 'dashboard',
				'url'   => $dashboard_url,
				'label' => __( 'Dashboard', 'littleworks-of-mercy' ),
				'class' => 'lworks-button lworks-button-secondary',
			);
		}

		$staff_links = array();
		if ( self::current_user_can_use_staff_pages() ) {
			$coordinator_url = LWorks_Repository::get_configured_page_url( 'coordinator_page_id' );
			if ( $coordinator_url ) {
				$staff_links[] = array(
					'id'    => 'coordinator',
					'url'   => $coordinator_url,
					'label' => __( 'Coordinator Review', 'littleworks-of-mercy' ),
					'class' => 'lworks-button lworks-button-secondary lworks-button-staff',
				);
			}

			/**
			 * Add future staff-only frontend links to the private member header.
			 *
			 * Link arrays should include id, url, label, and optional class keys.
			 *
			 * @param array  $staff_links Staff navigation links.
			 * @param string $active      Active section ID.
			 * @param int    $user_id     Current user ID.
			 */
			$staff_links = apply_filters( 'lworks_staff_nav_links', $staff_links, $active, get_current_user_id() );
		}

		$links = array_merge( $links, $staff_links );

		/**
		 * Customize private member navigation links before logout is appended.
		 *
		 * @param array  $links  Navigation links.
		 * @param string $active Active section ID.
		 * @param int    $user_id Current user ID.
		 */
		$links = apply_filters( 'lworks_member_nav_links', $links, $active, get_current_user_id() );

		$links[] = array(
			'id'    => 'logout',
			'url'   => wp_logout_url( home_url( '/' ) ),
			'label' => __( 'Sign out', 'littleworks-of-mercy' ),
			'class' => 'lworks-button lworks-button-secondary lworks-button-quiet',
		);

		echo '<nav class="lworks-member-header" aria-label="' . esc_attr__( 'Member navigation', 'littleworks-of-mercy' ) . '">';
		echo '<a class="lworks-member-home" href="' . esc_url( home_url( '/' ) ) . '"><span class="lworks-member-home-mark" aria-hidden="true"></span><span>' . esc_html__( 'littleWORKS Home', 'littleworks-of-mercy' ) . '</span></a>';
		echo '<div class="lworks-member-nav-actions">';

		foreach ( $links as $link ) {
			if ( empty( $link['url'] ) || empty( $link['label'] ) ) {
				continue;
			}

			$id      = isset( $link['id'] ) ? sanitize_key( $link['id'] ) : '';
			$class   = isset( $link['class'] ) ? self::sanitize_class_list( $link['class'] ) : 'lworks-button lworks-button-secondary';
			$current = $id && $active === $id;

			if ( $current ) {
				$class .= ' lworks-button-current';
			}

			echo '<a class="' . esc_attr( trim( $class ) ) . '" href="' . esc_url( $link['url'] ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $link['label'] ) . '</a>';
		}

		echo '</div>';
		echo '</nav>';
	}

	/**
	 * Whether the current user can see staff-only frontend links.
	 *
	 * @return bool
	 */
	private static function current_user_can_use_staff_pages() {
		if ( current_user_can( LWORKS_CAP_MANAGE_ASSIGNED ) || current_user_can( LWORKS_CAP_MANAGE_ALL ) ) {
			return true;
		}

		return ! empty( LWorks_Repository::get_managed_group_ids( get_current_user_id() ) );
	}

	/**
	 * Parse shortcode boolean-ish values.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	private static function truthy_shortcode_value( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Render built-in registration anti-spam fields and optional captcha widgets.
	 *
	 * @param array $settings Plugin settings.
	 * @return void
	 */
	private static function render_registration_antispam_fields( $settings ) {
		$started_at = time();
		$token      = self::registration_form_token( $started_at );

		echo '<input type="hidden" name="lworks_started_at" value="' . esc_attr( $started_at ) . '">';
		echo '<input type="hidden" name="lworks_form_token" value="' . esc_attr( $token ) . '">';
		echo '<label class="lworks-honeypot" aria-hidden="true" tabindex="-1"><span>' . esc_html__( 'Website', 'littleworks-of-mercy' ) . '</span><input type="text" name="lworks_website" value="" autocomplete="off" tabindex="-1"></label>';

		if ( ! empty( $settings['enable_hcaptcha'] ) && ! empty( $settings['hcaptcha_site_key'] ) ) {
			echo '<div class="h-captcha" data-sitekey="' . esc_attr( $settings['hcaptcha_site_key'] ) . '"></div>';
			wp_enqueue_script( 'lworks-hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
		}

		if ( ! empty( $settings['enable_recaptcha'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
			echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $settings['recaptcha_site_key'] ) . '"></div>';
			wp_enqueue_script( 'lworks-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		}

		do_action( 'lworks_registration_antispam_fields', $settings );
	}

	/**
	 * Validate built-in registration anti-spam controls and optional captcha.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_registration_antispam() {
		$settings   = LWorks_Repository::settings();
		$started_at = isset( $_POST['lworks_started_at'] ) ? absint( $_POST['lworks_started_at'] ) : 0;
		$token      = isset( $_POST['lworks_form_token'] ) ? sanitize_text_field( wp_unslash( $_POST['lworks_form_token'] ) ) : '';
		$honeypot   = isset( $_POST['lworks_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['lworks_website'] ) ) ) : '';

		if ( '' !== $honeypot ) {
			return new WP_Error( 'lworks_spam_honeypot', __( 'Registration could not be accepted. Please try again.', 'littleworks-of-mercy' ) );
		}

		if ( ! $started_at || ! hash_equals( self::registration_form_token( $started_at ), $token ) ) {
			return new WP_Error( 'lworks_spam_token', __( 'The registration form expired. Please reload the page and try again.', 'littleworks-of-mercy' ) );
		}

		$elapsed     = time() - $started_at;
		$min_seconds = isset( $settings['registration_min_seconds'] ) ? absint( $settings['registration_min_seconds'] ) : 4;
		$max_seconds = isset( $settings['registration_max_seconds'] ) ? absint( $settings['registration_max_seconds'] ) : DAY_IN_SECONDS;

		if ( $min_seconds && $elapsed < $min_seconds ) {
			return new WP_Error( 'lworks_spam_fast_submit', __( 'Registration was submitted too quickly. Please try again.', 'littleworks-of-mercy' ) );
		}

		if ( $max_seconds && $elapsed > $max_seconds ) {
			return new WP_Error( 'lworks_spam_stale_submit', __( 'The registration form expired. Please reload the page and try again.', 'littleworks-of-mercy' ) );
		}

		if ( ! empty( $settings['enable_hcaptcha'] ) ) {
			$hcaptcha = self::verify_hcaptcha( $settings );
			if ( is_wp_error( $hcaptcha ) ) {
				return $hcaptcha;
			}
		}

		if ( ! empty( $settings['enable_recaptcha'] ) ) {
			$recaptcha = self::verify_recaptcha( $settings );
			if ( is_wp_error( $recaptcha ) ) {
				return $recaptcha;
			}
		}

		$result = apply_filters( 'lworks_registration_antispam_result', true, $_POST, $settings );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new WP_Error( 'lworks_spam_filter', __( 'Registration could not be accepted. Please try again.', 'littleworks-of-mercy' ) );
		}

		return true;
	}

	/**
	 * Build a signed registration form token.
	 *
	 * @param int $started_at Timestamp.
	 * @return string
	 */
	private static function registration_form_token( $started_at ) {
		return wp_hash( 'lworks_register|' . absint( $started_at ) );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function verify_hcaptcha( $settings ) {
		$response = isset( $_POST['h-captcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ) : '';

		return self::verify_captcha_response(
			$response,
			isset( $settings['hcaptcha_secret_key'] ) ? $settings['hcaptcha_secret_key'] : '',
			'https://hcaptcha.com/siteverify'
		);
	}

	/**
	 * Verify Google reCAPTCHA v2.
	 *
	 * @param array $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private static function verify_recaptcha( $settings ) {
		$response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

		return self::verify_captcha_response(
			$response,
			isset( $settings['recaptcha_secret_key'] ) ? $settings['recaptcha_secret_key'] : '',
			'https://www.google.com/recaptcha/api/siteverify'
		);
	}

	/**
	 * Verify a captcha service response.
	 *
	 * @param string $response Token from browser.
	 * @param string $secret Secret key.
	 * @param string $endpoint Verification endpoint.
	 * @return true|WP_Error
	 */
	private static function verify_captcha_response( $response, $secret, $endpoint ) {
		if ( '' === $secret || '' === $response ) {
			return new WP_Error( 'lworks_captcha_missing', __( 'Please complete the captcha challenge.', 'littleworks-of-mercy' ) );
		}

		$remote = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $response,
					'remoteip' => self::remote_ip(),
				),
			)
		);

		if ( is_wp_error( $remote ) ) {
			return new WP_Error( 'lworks_captcha_unavailable', __( 'Captcha verification is temporarily unavailable. Please try again.', 'littleworks-of-mercy' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $remote ), true );
		if ( empty( $data['success'] ) ) {
			return new WP_Error( 'lworks_captcha_failed', __( 'Captcha verification failed. Please try again.', 'littleworks-of-mercy' ) );
		}

		return true;
	}

	/**
	 * Best-effort remote IP for captcha verification.
	 *
	 * @return string
	 */
	private static function remote_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
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

		$spam_error = self::validate_registration_antispam();
		if ( is_wp_error( $spam_error ) ) {
			return self::notice( $spam_error->get_error_message(), 'error' );
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

		if ( 'lworks_save_preferences' === $action ) {
			return self::handle_save_preferences();
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
	 * Save notification preferences.
	 *
	 * @return string
	 */
	private static function handle_save_preferences() {
		if ( ! isset( $_POST['lworks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lworks_nonce'] ) ), 'lworks_save_preferences' ) ) {
			return self::notice( __( 'The settings form expired. Please try again.', 'littleworks-of-mercy' ), 'error' );
		}

		LWorks_Repository::save_user_preferences(
			get_current_user_id(),
			array(
				'email_new_requests' => isset( $_POST['email_new_requests'] ) ? 1 : 0,
				'email_responses'    => isset( $_POST['email_responses'] ) ? 1 : 0,
			)
		);

		LWorks_Repository::audit( get_current_user_id(), 'user', get_current_user_id(), 'preferences_updated', '' );

		return self::notice( __( 'Your notification settings were saved.', 'littleworks-of-mercy' ), 'success' );
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
			echo '<p class="lworks-meta">' . esc_html( $request->group_name ) . ' &middot; ' . esc_html( isset( $types[ $request->request_type ] ) ? $types[ $request->request_type ] : $request->request_type ) . '</p>';
			echo '<h3>' . esc_html( $request->title ) . '</h3>';
			echo '</div>';
			echo '<span class="lworks-status lworks-status-' . esc_attr( $request->status ) . '">' . esc_html( isset( $statuses[ $request->status ] ) ? $statuses[ $request->status ] : $request->status ) . '</span>';
			echo '</div>';
			echo '<p class="lworks-meta">' . esc_html( $author ? $author->display_name : __( 'Member', 'littleworks-of-mercy' ) ) . ' &middot; ' . esc_html( get_date_from_gmt( $request->created_at, get_option( 'date_format' ) ) ) . '</p>';
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
	 * Render notification preferences.
	 *
	 * @return void
	 */
	private static function render_preferences_panel() {
		$preferences = LWorks_Repository::get_user_preferences( get_current_user_id() );
		?>
		<section class="lworks-panel lworks-preferences">
			<h2><?php esc_html_e( 'Notification settings', 'littleworks-of-mercy' ); ?></h2>
			<form method="post" class="lworks-form">
				<?php wp_nonce_field( 'lworks_save_preferences', 'lworks_nonce' ); ?>
				<input type="hidden" name="lworks_action" value="lworks_save_preferences">
				<label class="lworks-checkbox">
					<input type="checkbox" name="email_new_requests" value="1" <?php checked( ! empty( $preferences['email_new_requests'] ) ); ?>>
					<span><?php esc_html_e( 'Email me when a new request is posted in one of my groups.', 'littleworks-of-mercy' ); ?></span>
				</label>
				<label class="lworks-checkbox">
					<input type="checkbox" name="email_responses" value="1" <?php checked( ! empty( $preferences['email_responses'] ) ); ?>>
					<span><?php esc_html_e( 'Email me when someone responds to one of my requests.', 'littleworks-of-mercy' ); ?></span>
				</label>
				<button type="submit" class="lworks-button lworks-button-secondary"><?php esc_html_e( 'Save settings', 'littleworks-of-mercy' ); ?></button>
			</form>
		</section>
		<?php
	}

	/**
	 * Render a coordinator-visible roster for managed groups.
	 *
	 * @return void
	 */
	private static function render_coordinator_roster() {
		$group_ids = LWorks_Repository::get_managed_group_ids( get_current_user_id() );
		$members   = LWorks_Repository::get_members_for_groups( $group_ids, array( 'active', 'pending' ) );

		echo '<section class="lworks-panel lworks-roster">';
		echo '<h2>' . esc_html__( 'Group members', 'littleworks-of-mercy' ) . '</h2>';

		if ( empty( $members ) ) {
			echo self::notice( __( 'No members are assigned to your groups yet.', 'littleworks-of-mercy' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</section>';
			return;
		}

		echo '<div class="lworks-table-wrap"><table class="lworks-table">';
		echo '<thead><tr><th>' . esc_html__( 'Name', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Group', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Role', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Status', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Email', 'littleworks-of-mercy' ) . '</th><th>' . esc_html__( 'Phone', 'littleworks-of-mercy' ) . '</th></tr></thead><tbody>';

		foreach ( $members as $membership ) {
			$user = get_user_by( 'id', $membership->user_id );
			if ( ! $user ) {
				continue;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $user->display_name ) . '</td>';
			echo '<td>' . esc_html( $membership->group_name ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $membership->member_role ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $membership->status ) ) . '</td>';
			echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
			echo '<td>' . esc_html( get_user_meta( $user->ID, 'lworks_phone', true ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</section>';
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

		$preferences = LWorks_Repository::get_user_preferences( $user->ID );
		if ( empty( $preferences['email_responses'] ) ) {
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
			$preferences = $user ? LWorks_Repository::get_user_preferences( $user->ID ) : array();
			if ( $user && is_email( $user->user_email ) && ! empty( $preferences['email_new_requests'] ) ) {
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
