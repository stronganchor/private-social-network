<?php
// Offline authorization regressions against production classes; no WP or mail transport.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'LWORKS_CAP_MANAGE_ALL', 'manage_all' );
define( 'LWORKS_CAP_POST', 'post_requests' );
define( 'LWORKS_CAP_RESPOND', 'respond' );
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function sanitize_textarea_field( $value ) { return strip_tags( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_kses_post( $value ) { return strip_tags( $value, '<p>' ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function __( $value, $domain = '' ) { return $value; }
function wp_verify_nonce( $nonce, $action ) { return 'valid-fixture' === $nonce; }
function current_user_can( $cap ) { return 'manage_all' === $cap ? $GLOBALS['admin'] : true; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function current_time( $format, $gmt = false ) { return '2026-10-21 12:00:00'; }
function get_option( $name, $default = false ) { return $default; }
function get_user_by( $field, $id ) { return null; }

class GroupAccessFixtureDb {
    public $prefix = 'wp_';
    public $insert_id = 100;
    public $writes = 0;
    public $groups;
    public $requests;
    public function __construct() {
        $this->groups = array( 1 => (object) array( 'id' => 1, 'active' => 1 ), 2 => (object) array( 'id' => 2, 'active' => 0 ) );
        $this->requests = array( 50 => (object) array( 'id' => 50, 'group_id' => 2, 'user_id' => 11, 'status' => 'open' ) );
    }
    public function prepare( $sql, ...$params ) { return (object) array( 'sql' => $sql, 'params' => $params ); }
    public function get_row( $query ) {
        if ( false !== strpos( $query->sql, 'lworks_groups' ) ) { return $this->groups[$query->params[0]] ?? null; }
        if ( false !== strpos( $query->sql, 'lworks_requests' ) ) { return $this->requests[$query->params[0]] ?? null; }
        if ( false !== strpos( $query->sql, 'lworks_group_members' ) && in_array( $query->params[0], array( 10, 11 ), true ) ) {
            return (object) array( 'status' => 'active', 'member_role' => 10 === $query->params[0] ? 'coordinator' : 'member' );
        }
        return null;
    }
    public function get_col( $query ) {
        if ( false === strpos( $query->sql, "member_role = 'coordinator'" ) ) { return array(); }
        if ( 10 !== $query->params[0] ) { return array(); }
        return array_values( array_filter( array( 1, 2 ), function( $id ) use ( $query ) {
            return false === strpos( $query->sql, 'g.active = 1' ) || $this->groups[$id]->active;
        } ) );
    }
    public function insert( $table, $data, $formats ) {
        if ( 'wp_lworks_requests' === $table || 'wp_lworks_responses' === $table ) { $this->writes++; }
        $this->insert_id++;
        return 1;
    }
    public function update( $table, $data, $where, ...$formats ) {
        $this->writes++;
        foreach ( $data as $key => $value ) { $this->requests[$where['id']]->$key = $value; }
        return 1;
    }
}

require_once dirname( __DIR__ ) . '/includes/class-lworks-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-lworks-shortcodes.php';
$GLOBALS['wpdb'] = new GroupAccessFixtureDb();
$GLOBALS['admin'] = false;
$GLOBALS['user_id'] = 11;
$checks = 0;
function check( $condition, $message ) {
    $GLOBALS['checks']++;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function invoke_handler( $name ) {
    $method = new ReflectionMethod( 'LWorks_Shortcodes', $name );
    $method->setAccessible( true );
    return $method->invoke( null );
}

check( LWorks_Repository::user_can_access_group( 11, 1 ), 'Active member was denied' );
check( ! LWorks_Repository::user_can_access_group( 11, 2 ), 'Disabled group still permits member access' );
check( ! LWorks_Repository::user_can_access_group( 11, 999 ), 'Missing group was accessible' );
check( ! LWorks_Repository::user_can_access_group( 99, 1 ), 'Nonmember was allowed' );
check( LWorks_Repository::user_can_manage_group( 10, 1 ), 'Active coordinator was denied' );
check( ! LWorks_Repository::user_can_manage_group( 10, 2 ), 'Disabled group still permits coordinator access' );
check( ! LWorks_Repository::user_can_manage_group( 11, 1 ), 'Ordinary member gained coordinator access' );
check( array( 1 ) === LWorks_Repository::get_managed_group_ids( 10 ), 'Coordinator roster includes a disabled group' );

$_POST = array( 'lworks_nonce' => 'valid-fixture', 'group_id' => 2, 'title' => 'Fixture', 'content' => 'Fixture details' );
invoke_handler( 'handle_create_request' );
check( 0 === $wpdb->writes, 'Stale request form wrote to a disabled group' );
$_POST['request_id'] = 50;
$_POST['response_type'] = 'message';
invoke_handler( 'handle_create_response' );
check( 0 === $wpdb->writes, 'Stale response form wrote to a disabled group' );
$_POST['status'] = 'closed';
invoke_handler( 'handle_update_request_status' );
check( 0 === $wpdb->writes, 'Request owner changed a disabled group request' );

$wpdb->groups[2]->active = 1;
invoke_handler( 'handle_update_request_status' );
check( 1 === $wpdb->writes && 'closed' === $wpdb->requests[50]->status, 'Active-group owner update was broken' );
invoke_handler( 'handle_create_request' );
check( 2 === $wpdb->writes, 'Active-group request submission was broken' );
$_POST['lworks_nonce'] = 'invalid';
invoke_handler( 'handle_create_request' );
check( 2 === $wpdb->writes, 'Invalid nonce was accepted' );

$wpdb->groups[2]->active = 0;
$GLOBALS['admin'] = true;
$GLOBALS['user_id'] = 99;
check( LWorks_Repository::user_can_access_group( 99, 2 ), 'Administrator access override was removed' );
check( LWorks_Repository::user_can_manage_group( 99, 2 ), 'Administrator management override was removed' );
$_POST['lworks_nonce'] = 'valid-fixture';
invoke_handler( 'handle_update_request_status' );
check( 3 === $wpdb->writes, 'Administrator cannot manage a disabled group request' );
echo "OK: $checks disabled-group access checks; no WordPress, database, mail or network required.\n";
