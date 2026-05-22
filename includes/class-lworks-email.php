<?php
/**
 * Email helpers.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Email {
	/**
	 * Send plugin email with a site-domain sender.
	 *
	 * @param string|array $to Recipient address or addresses.
	 * @param string       $subject Message subject.
	 * @param string       $body Message body.
	 * @param array        $headers Additional headers.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $headers = array() ) {
		$headers = array_merge( self::default_headers(), (array) $headers );

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Build default plugin email headers.
	 *
	 * @return array
	 */
	private static function default_headers() {
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
		);

		$reply_to = sanitize_email( get_option( 'admin_email' ) );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return $headers;
	}

	/**
	 * Sender display name.
	 *
	 * @return string
	 */
	private static function from_name() {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$name = trim( preg_replace( '/[\r\n]+/', ' ', $name ) );

		return $name ? $name : 'littleWORKS';
	}

	/**
	 * Sender email using the current site domain.
	 *
	 * @return string
	 */
	private static function from_email() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			$host = wp_parse_url( network_home_url(), PHP_URL_HOST );
		}

		$host = strtolower( (string) $host );
		$host = preg_replace( '/^www\./', '', $host );
		$host = preg_replace( '/[^a-z0-9.-]/', '', $host );
		$email = 'no-reply@' . $host;

		if ( ! $host || ! is_email( $email ) ) {
			$email = sanitize_email( get_option( 'admin_email' ) );
		}

		return is_email( $email ) ? $email : 'wordpress@localhost.localdomain';
	}
}
