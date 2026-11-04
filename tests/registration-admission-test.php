<?php
// Offline actual-handler/admission regressions; temporary files stay outside the repo.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DB_NAME', 'offline_fixture' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
function wp_salt( $scheme ) { return 'offline-fixture-salt'; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_cache_delete( $key, $group ) { $GLOBALS['cache_deletes'][] = $key; return true; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function wp_unslash( $value ) { return $value; }
function wp_hash( $value ) { return hash_hmac( 'sha256', $value, 'fixture-token-salt' ); }
function wp_verify_nonce( $nonce, $action ) { return 'valid-fixture' === $nonce; }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function apply_filters( $name, $value, ...$args ) { return $value; }
function email_exists( $email ) { return isset( $GLOBALS['users_by_email'][ $email ] ); }
function get_user_by( $field, $id ) { return $GLOBALS['users'][ $id ] ?? null; }
function wp_insert_user( $data ) {
	$GLOBALS['account_writes']++;
	if ( $GLOBALS['fail_insert'] ) { return new WP_Error( 'fixture_failure', 'Account write failed.' ); }
	$id = 100 + $GLOBALS['account_writes'];
	$GLOBALS['users'][ $id ] = (object) $data;
	$GLOBALS['users_by_email'][ $data['user_email'] ] = $id;
	return $id;
}
function update_user_meta( ...$args ) { $GLOBALS['metadata_writes']++; }
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
class LWorks_Email {
	public static function send( $to, $subject, $body ) { $GLOBALS['mail'][] = (array) $to; return true; }
}
class LWorks_Repository {
	public static function settings() { return array( 'registration_min_seconds' => 4, 'registration_max_seconds' => 86400, 'notification_email' => 'site@example.test' ); }
	public static function get_group( $id ) { return 1 === $id ? (object) array( 'id' => 1, 'active' => 1, 'name' => 'Fixture group' ) : null; }
	public static function username_from_email( $email ) { return 'fixture-' . hash( 'sha256', $email ); }
	public static function save_membership( ...$args ) { $GLOBALS['membership_writes']++; return 1; }
	public static function audit( ...$args ) { $GLOBALS['audit_writes']++; }
	public static function sanitize_invite_token( $value ) { return $value; }
	public static function sanitize_invite_code( $value ) { return $value; }
	public static function get_invite_by_token( $value ) { return 'valid-invite' === $value ? (object) array( 'id' => 8, 'group_id' => 1, 'email_restriction' => '', 'created_by' => 10 ) : null; }
	public static function invite_is_usable( $invite ) { return true; }
	public static function use_invite( $id, $user_id ) { $GLOBALS['invite_uses']++; return true; }
	public static function approve_membership( ...$args ) { $GLOBALS['approvals']++; }
	public static function get_group_by_invite_code( $code ) { return 'valid-code' === $code ? (object) array( 'id' => 1 ) : null; }
	public static function get_page_url( $name ) { return 'https://fixture.invalid/member/'; }
	public static function table( $name ) { return 'wp_lworks_' . $name; }
}

/**
 * Query-aware DB substitute. Its named-lock implementation uses an OS file
 * lock across independent PHP workers; production code decides every budget.
 */
class AdmissionFixtureDb {
	public $options = 'wp_options';
	public $last_error = '';
	public $value = null;
	public $failure = '';
	public $held = false;
	public $releases = 0;
	public $locks = array();
	public $reads = 0;
	private $directory;
	private $lock_file;
	public function __construct( $directory = '' ) { $this->directory = $directory; }
	public function prepare( $sql, ...$args ) { return (object) array( 'sql' => $sql, 'args' => $args ); }
	public function get_var( $query ) {
		$this->last_error = '';
		if ( false !== strpos( $query->sql, 'GET_LOCK(' ) ) {
			$this->locks[] = $query->args[0];
			if ( 'lock' === $this->failure ) { return null; }
			if ( $this->directory ) {
				$this->lock_file = fopen( $this->directory . '/lock', 'c+' );
				$deadline = microtime( true ) + 1;
				do {
					if ( flock( $this->lock_file, LOCK_EX | LOCK_NB ) ) { $this->held = true; return '1'; }
					usleep( 1000 );
				} while ( microtime( true ) < $deadline );
				return '0';
			}
			$this->held = true;
			return '1';
		}
		if ( false !== strpos( $query->sql, 'RELEASE_LOCK(' ) ) {
			$this->releases++;
			$owned = $this->held;
			$this->held = false;
			if ( $this->lock_file ) { flock( $this->lock_file, LOCK_UN ); fclose( $this->lock_file ); $this->lock_file = null; }
			return 'release' === $this->failure ? null : ( $owned ? '1' : '0' );
		}
		if ( false !== strpos( $query->sql, 'SELECT option_value' ) ) {
			$this->reads++;
			if ( 'read' === $this->failure ) { $this->last_error = 'fixture read error'; return null; }
			if ( 'readback' === $this->failure && $this->reads > 1 ) { return 'different-state'; }
			if ( 'throw' === $this->failure ) { throw new RuntimeException( 'fixture DB exception' ); }
			return $this->directory ? ( is_file( $this->directory . '/state' ) ? file_get_contents( $this->directory . '/state' ) : null ) : $this->value;
		}
		throw new RuntimeException( 'Unexpected fixture SQL: ' . $query->sql );
	}
	public function query( $query ) {
		$this->last_error = '';
		if ( false === strpos( $query->sql, 'IS_USED_LOCK(' ) || false === strpos( $query->sql, 'CONNECTION_ID()' ) || false === strpos( $query->sql, "autoload = 'no'" ) ) { throw new RuntimeException( 'Missing ownership/non-autoload write fence' ); }
		if ( 'write' === $this->failure ) { $this->last_error = 'fixture write failure'; return false; }
		if ( 'reconnect' === $this->failure ) { $this->held = false; return 0; }
		if ( ! $this->held ) { return 0; }
		if ( $this->directory ) { file_put_contents( $this->directory . '/state', $query->args[1] ); } else { $this->value = $query->args[1]; }
		return 1;
	}
	public function get_col( $query ) { return $GLOBALS['coordinators']; }
}

require_once dirname( __DIR__ ) . '/includes/class-lworks-registration-admission.php';
require_once dirname( __DIR__ ) . '/includes/class-lworks-shortcodes.php';

if ( isset( $argv[1] ) && '--worker' === $argv[1] ) {
	$GLOBALS['wpdb'] = new AdmissionFixtureDb( $argv[2] );
	$_SERVER['REMOTE_ADDR'] = $argv[3];
	$accepted = 0;
	for ( $i = 0; $i < (int) $argv[4]; $i++ ) { $accepted += LWorks_Registration_Admission::reserve() ? 1 : 0; }
	echo $accepted;
	exit;
}

$checks = 0;
function check( $condition, $message ) { $GLOBALS['checks']++; if ( ! $condition ) { throw new RuntimeException( $message ); } }
function reset_fixture() {
	$GLOBALS['wpdb'] = new AdmissionFixtureDb();
	foreach ( array( 'account_writes', 'metadata_writes', 'membership_writes', 'audit_writes', 'invite_uses', 'approvals' ) as $name ) { $GLOBALS[$name] = 0; }
	$GLOBALS['users'] = array( 10 => (object) array( 'user_email' => 'coordinator@example.test' ) );
	$GLOBALS['users_by_email'] = array();
	$GLOBALS['mail'] = array();
	$GLOBALS['cache_deletes'] = array();
	$GLOBALS['coordinators'] = array( 10 );
	$GLOBALS['fail_insert'] = false;
	$_SERVER = array( 'REMOTE_ADDR' => '192.0.2.1' );
	$_FILES = array();
	fixture_post();
}
function fixture_post( $email = 'person@example.test' ) {
	$started = time() - 5;
	$_POST = array( 'lworks_nonce' => 'valid-fixture', 'lworks_started_at' => (string) $started, 'lworks_form_token' => wp_hash( 'lworks_register|' . $started ), 'lworks_website' => '', 'email' => $email, 'first_name' => 'Fixture', 'last_name' => 'Person', 'phone' => '', 'password' => 'long-fixture-password', 'password_confirm' => 'long-fixture-password', 'group_id' => '1', 'connection_note' => 'Known by the fixture community.' );
}
function handler() { $method = new ReflectionMethod( 'LWorks_Shortcodes', 'handle_registration' ); $method->setAccessible( true ); return $method->invoke( null ); }
function state() { return json_decode( $GLOBALS['wpdb']->value, true ); }
function set_state( $state ) { $GLOBALS['wpdb']->value = json_encode( $state ); }

reset_fixture();
for ( $i = 0; $i < 20; $i++ ) { fixture_post( 'person' . $i . '@example.test' ); handler(); }
check( 5 === $GLOBALS['account_writes'] && 5 === $GLOBALS['membership_writes'] && 5 === count( $GLOBALS['mail'] ), 'Replay exceeded per-IP account/mail budget' );
check( 5 === state()['day']['count'], 'Rejected attempts changed persistent allowance' );
check( false === strpos( $wpdb->value, '192.0.2.1' ), 'Persisted state exposes IP address' );
check( 20 === $wpdb->releases && ! $wpdb->held, 'Admission did not release all acquired locks' );

reset_fixture();
for ( $i = 0; $i < 35; $i++ ) { $_SERVER['REMOTE_ADDR'] = '192.0.2.' . ( $i + 1 ); fixture_post( 'global' . $i . '@example.test' ); handler(); }
check( 30 === $GLOBALS['account_writes'] && 30 === count( $GLOBALS['mail'] ), 'Multiple peers exceeded site-hour account/mail budget' );

reset_fixture();
handler();
$saved = state();
$peer = array_key_first( $saved['peers'] );
$saved['hour']['start'] -= HOUR_IN_SECONDS;
$saved['day']['count'] = 100;
set_state( $saved );
check( ! LWorks_Registration_Admission::reserve(), 'Site daily budget reset with hourly rollover' );
$saved['day']['count'] = 20;
$saved['peers'][$peer]['day']['count'] = 20;
set_state( $saved );
check( ! LWorks_Registration_Admission::reserve(), 'Peer daily budget reset with hourly rollover' );
$saved['peers'][$peer]['day']['count'] = 1;
$saved['peers'][$peer]['hour']['start'] -= HOUR_IN_SECONDS;
set_state( $saved );
check( LWorks_Registration_Admission::reserve(), 'Expired hourly window did not replenish' );
$saved = state();
$saved['day']['start'] -= DAY_IN_SECONDS;
$saved['peers'][$peer]['day']['start'] -= DAY_IN_SECONDS;
$saved['hour']['start'] -= DAY_IN_SECONDS;
$saved['peers'][$peer]['hour']['start'] -= DAY_IN_SECONDS;
set_state( $saved );
$_SERVER['REMOTE_ADDR'] = '2001:db8::1';
check( LWorks_Registration_Admission::reserve() && 1 === count( state()['peers'] ) && 1 === state()['day']['count'], 'Expired peer map was not pruned' );

reset_fixture();
for ( $i = 0; $i < 5; $i++ ) { check( LWorks_Registration_Admission::reserve(), 'Expected IPv4 allowance missing' ); }
$_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.1';
check( ! LWorks_Registration_Admission::reserve(), 'IPv4 mapped address bypassed peer budget' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.2';
check( ! LWorks_Registration_Admission::reserve(), 'Forwarded headers bypassed peer budget' );
reset_fixture(); $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
for ( $i = 0; $i < 5; $i++ ) { LWorks_Registration_Admission::reserve(); }
$_SERVER['REMOTE_ADDR'] = '2001:0db8:0:0:0:0:0:1';
check( ! LWorks_Registration_Admission::reserve(), 'Equivalent IPv6 spelling bypassed peer budget' );
foreach ( array( '', 'not-an-ip', array( '192.0.2.1' ) ) as $bad_ip ) { reset_fixture(); $_SERVER['REMOTE_ADDR'] = $bad_ip; handler(); check( 0 === $GLOBALS['account_writes'], 'Malformed peer permitted account work' ); }

foreach ( array( 'lock', 'read', 'write', 'readback', 'throw', 'reconnect', 'release' ) as $failure ) {
	reset_fixture(); $wpdb->failure = $failure; handler();
	check( 0 === $GLOBALS['account_writes'] && 0 === count( $GLOBALS['mail'] ), 'DB/lock failure permitted work: ' . $failure );
	check( ! $wpdb->held && ( 'lock' === $failure || 1 === $wpdb->releases ), 'DB failure retained named lock: ' . $failure );
}
foreach ( array( '', 'not-json', '{}', 'false', str_repeat( 'x', 32769 ), '{"version":1,"hour":{},"day":{},"peers":[]}' ) as $raw ) {
	reset_fixture(); $wpdb->value = $raw; handler();
	check( 0 === $GLOBALS['account_writes'] && $raw === $wpdb->value && 1 === $wpdb->releases, 'Malformed state was reset/admitted' );
}
reset_fixture(); LWorks_Registration_Admission::reserve();
$saved = state(); $saved['peers'] = array_fill_keys( array_map( static function( $i ) { return hash( 'sha256', (string) $i ); }, range( 0, 100 ) ), reset( $saved['peers'] ) );
set_state( $saved );
check( ! LWorks_Registration_Admission::reserve(), 'Oversized peer state admitted' );
reset_fixture(); LWorks_Registration_Admission::reserve(); $first_lock = $wpdb->locks[0]; $wpdb->options = 'other_options'; LWorks_Registration_Admission::reserve();
check( $first_lock !== $wpdb->locks[1] && strlen( $first_lock ) < 64, 'Lock is not site-scoped/bounded' );

foreach ( array( 'password' => str_repeat( 'x', 257 ), 'connection_note' => str_repeat( 'x', 2001 ), 'email' => array( 'nested' ), 'unknown' => array( 'nested' ) ) as $field => $value ) {
	reset_fixture(); $_POST[$field] = $value; handler();
	check( 0 === $GLOBALS['account_writes'] && 0 === count( $wpdb->locks ), 'Unbounded raw field reached admission: ' . $field );
}
reset_fixture(); $_SERVER['CONTENT_LENGTH'] = '32769'; handler(); check( 0 === count( $wpdb->locks ), 'Oversized body reached admission' );
reset_fixture(); $_FILES = array( 'unexpected' => array() ); handler(); check( 0 === count( $wpdb->locks ), 'File upload reached signup admission' );
reset_fixture(); $_POST['lworks_nonce'] = 'invalid'; handler(); check( 0 === count( $wpdb->locks ), 'Invalid nonce spent a signup reservation' );
reset_fixture(); $_POST['lworks_website'] = 'spam'; handler(); check( 0 === $GLOBALS['account_writes'] && 1 === state()['day']['count'], 'Rejected spam attempt was refunded' );
reset_fixture(); $GLOBALS['fail_insert'] = true; handler(); check( 1 === state()['day']['count'] && 0 === count( $GLOBALS['mail'] ), 'Failed account write was refunded/notified' );
reset_fixture(); $_POST['invite_token'] = 'valid-invite'; handler();
check( 1 === $GLOBALS['account_writes'] && 1 === $GLOBALS['invite_uses'] && 1 === $GLOBALS['approvals'] && array( 'person@example.test' ) === $GLOBALS['mail'][0], 'Valid secure invite semantics changed' );
for ( $i = 0; $i < 7; $i++ ) { fixture_post( 'invite' . $i . '@example.test' ); $_POST['invite_token'] = 'valid-invite'; handler(); }
check( 5 === $GLOBALS['account_writes'] && 5 === count( $GLOBALS['mail'] ), 'Invites bypass admission budget' );
reset_fixture(); $_POST['invite_code'] = 'valid-code'; handler(); check( 1 === $GLOBALS['account_writes'] && 0 === $GLOBALS['approvals'], 'Legacy group invite stopped creating pending signup' );
reset_fixture();
$GLOBALS['coordinators'] = range( 1, 12 );
foreach ( $GLOBALS['coordinators'] as $id ) { $GLOBALS['users'][$id] = (object) array( 'user_email' => sprintf( 'staff%02d@example.test', $id ) ); }
handler();
check( 5 === count( $GLOBALS['mail'][0] ) && 'site@example.test' === $GLOBALS['mail'][0][0], 'Large roster mail is not bounded/prioritized' );
check( array( 'site@example.test', 'staff01@example.test', 'staff02@example.test', 'staff03@example.test', 'staff04@example.test' ) === $GLOBALS['mail'][0], 'Large roster truncation is not deterministic' );

function concurrent_reservations( $shared_peer ) {
	$directory = sys_get_temp_dir() . '/lworks-admission-' . bin2hex( random_bytes( 8 ) );
	if ( ! mkdir( $directory, 0700 ) ) { throw new RuntimeException( 'Could not make isolated fixture directory' ); }
	$workers = array();
	try {
		for ( $i = 0; $i < 12; $i++ ) {
			$command = array( PHP_BINARY, __FILE__, '--worker', $directory, $shared_peer ? '198.51.100.1' : '198.51.100.' . ( $i + 1 ), $shared_peer ? '1' : '3' );
			$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
			if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Worker launch failed' ); }
			fclose( $pipes[0] );
			$workers[] = array( $process, $pipes );
		}
		$accepted = 0;
		foreach ( $workers as $worker ) {
			$output = stream_get_contents( $worker[1][1] ); $errors = stream_get_contents( $worker[1][2] );
			fclose( $worker[1][1] ); fclose( $worker[1][2] ); $code = proc_close( $worker[0] );
			check( 0 === $code && '' === $errors && ctype_digit( $output ), 'Independent worker failed: ' . $errors );
			$accepted += (int) $output;
		}
		check( ( $shared_peer ? 5 : 30 ) === $accepted, 'Concurrent workers crossed admission limit' );
		$state = json_decode( file_get_contents( $directory . '/state' ), true );
		check( $accepted === $state['hour']['count'], 'Concurrent persisted count lost accepted reservations' );
	} finally {
		foreach ( array( 'lock', 'state' ) as $name ) { if ( is_file( $directory . '/' . $name ) ) { unlink( $directory . '/' . $name ); } }
		rmdir( $directory );
	}
}
concurrent_reservations( true );
concurrent_reservations( false );
echo "OK: $checks registration admission checks, including 24 independent workers; no WordPress, real DB, mail or network.\n";
