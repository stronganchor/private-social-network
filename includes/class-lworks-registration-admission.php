<?php
/**
 * Bounded, cross-worker admission for public registration work.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LWorks_Registration_Admission {
	const OPTION = 'lworks_registration_admission_v1';
	const STATE_MAX_BYTES = 32768;
	const MAX_PEERS = 100;

	/**
	 * Charge an attempt before CAPTCHA, password hashing, account creation or mail.
	 * Failed submissions retain their charge; reservations are never refunded.
	 *
	 * @return bool
	 */
	public static function reserve() {
		global $wpdb;

		$peer = self::peer_key();
		if ( false === $peer ) {
			return false;
		}

		$lock = 'lworks_reg_' . substr( hash( 'sha256', ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . '|' . $wpdb->options ), 0, 40 );
		$locked = false;
		$allowed = false;
		$released = false;
		try {
			$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 1)', $lock ) );
			if ( ! $locked ) {
				return false;
			}

			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION ) );
			if ( ! empty( $wpdb->last_error ) ) {
				return false;
			}
			$now = time();
			$state = self::read_state( $raw, $now );
			if ( false === $state ) {
				return false;
			}
			$current = isset( $state['peers'][ $peer ] ) ? $state['peers'][ $peer ] : array(
				'hour' => self::empty_bucket( $now, HOUR_IN_SECONDS ),
				'day'  => self::empty_bucket( $now, DAY_IN_SECONDS ),
			);
			if ( $state['hour']['count'] >= 30 || $state['day']['count'] >= 100 || $current['hour']['count'] >= 5 || $current['day']['count'] >= 20 ) {
				return false;
			}
			$state['hour']['count']++;
			$state['day']['count']++;
			$current['hour']['count']++;
			$current['day']['count']++;
			$state['peers'][ $peer ] = $current;
			if ( count( $state['peers'] ) > self::MAX_PEERS ) {
				return false;
			}
			$json = wp_json_encode( $state );
			if ( ! is_string( $json ) || strlen( $json ) > self::STATE_MAX_BYTES ) {
				return false;
			}

			// Fence the write itself against wpdb reconnecting and losing its lock.
			$written = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				 SELECT %s, %s, 'no' WHERE IS_USED_LOCK(%s) = CONNECTION_ID()
				 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'",
				self::OPTION,
				$json,
				$lock
			) );
			if ( ! in_array( $written, array( 1, 2 ), true ) || ! empty( $wpdb->last_error ) ) {
				return false;
			}
			$readback = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION ) );
			$allowed = empty( $wpdb->last_error ) && is_string( $readback ) && hash_equals( $json, $readback );
			wp_cache_delete( self::OPTION, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		} catch ( Throwable $error ) {
			$allowed = false;
		} finally {
			if ( $locked ) {
				try {
					$released = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
				} catch ( Throwable $error ) {
					$released = false;
				}
			}
		}
		return $allowed && $released;
	}

	/**
	 * Use only the socket peer, never untrusted forwarding headers.
	 * IPv4-mapped IPv6 shares the native IPv4 budget.
	 *
	 * @return string|false Salted hash; never a stored IP address.
	 */
	private static function peer_key() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		if ( ! is_string( $address ) || strlen( $address ) > 45 || ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$packed = inet_pton( $address );
		if ( false === $packed ) {
			return false;
		}
		if ( 16 === strlen( $packed ) && substr( $packed, 0, 12 ) === str_repeat( "\x00", 10 ) . "\xff\xff" ) {
			$packed = substr( $packed, 12 );
		}
		return hash_hmac( 'sha256', $packed, wp_salt( 'nonce' ) );
	}

	private static function empty_bucket( $now, $window ) {
		return array( 'start' => (int) ( floor( $now / $window ) * $window ), 'count' => 0 );
	}

	private static function bucket( $value, $now, $window, $limit ) {
		if ( ! is_array( $value ) || 2 !== count( $value ) || ! isset( $value['start'], $value['count'] ) || ! is_int( $value['start'] ) || ! is_int( $value['count'] ) || $value['start'] < 0 || $value['start'] > $now || 0 !== $value['start'] % $window || $value['count'] < 0 || $value['count'] > $limit ) {
			return false;
		}
		return $value['start'] === self::empty_bucket( $now, $window )['start'] ? $value : self::empty_bucket( $now, $window );
	}

	/**
	 * Invalid persisted data is not silently reset into a fresh allowance.
	 *
	 * @return array|false
	 */
	private static function read_state( $raw, $now ) {
		if ( null === $raw ) {
			return array( 'version' => 1, 'hour' => self::empty_bucket( $now, HOUR_IN_SECONDS ), 'day' => self::empty_bucket( $now, DAY_IN_SECONDS ), 'peers' => array() );
		}
		if ( ! is_string( $raw ) || strlen( $raw ) > self::STATE_MAX_BYTES ) {
			return false;
		}
		$state = json_decode( $raw, true, 8 );
		if ( ! is_array( $state ) || 4 !== count( $state ) || ! isset( $state['version'], $state['hour'], $state['day'], $state['peers'] ) || 1 !== $state['version'] || ! is_array( $state['peers'] ) || count( $state['peers'] ) > self::MAX_PEERS ) {
			return false;
		}
		$state['hour'] = self::bucket( $state['hour'], $now, HOUR_IN_SECONDS, 30 );
		$state['day'] = self::bucket( $state['day'], $now, DAY_IN_SECONDS, 100 );
		if ( false === $state['hour'] || false === $state['day'] ) {
			return false;
		}
		foreach ( $state['peers'] as $key => $peer ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{64}$/D', $key ) || ! is_array( $peer ) || 2 !== count( $peer ) || ! isset( $peer['hour'], $peer['day'] ) ) {
				return false;
			}
			$hour = self::bucket( $peer['hour'], $now, HOUR_IN_SECONDS, 5 );
			$day = self::bucket( $peer['day'], $now, DAY_IN_SECONDS, 20 );
			if ( false === $hour || false === $day ) {
				return false;
			}
			if ( 0 === $day['count'] ) {
				unset( $state['peers'][ $key ] );
			} else {
				$state['peers'][ $key ] = array( 'hour' => $hour, 'day' => $day );
			}
		}
		return $state;
	}
}
