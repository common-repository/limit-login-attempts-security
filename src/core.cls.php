<?php
/**
 * Core class
 *
 * @since 1.0
 */
namespace llas;

defined( 'WPINC' ) || exit;

class Core
{
	private static $_instance;

	const PREFIX_SET = array(
		'continent',
		'continent_code',
		'country',
		'country_code',
		'city',
		'postal',
		'subdivision',
		'subdivision_code',
	);

	private $_visitor_geo_data = array();
	private $_err_added = false;

	/**
	 * Init
	 *
	 * @since  1.0
	 * @access private
	 */
	private function __construct()
	{
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'login_head', array( $this, 'login_head' ) );
		// add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 999, 2 );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 2, 3 );

		REST::get_instance();
	}

	/**
	 * Login page display messages
	 *
	 * @since  1.0
	 * @access public
	 */
	public function login_head()
	{
		global $error;

		if ( $this->_err_added ) {
			return;
		}

		// check whitelist
		if ( ! $this->try_whitelist() ) {
			$error .= Lang::msg( 'not_in_whitelist' );
			return;
		}

		// check blacklist
		if ( $this->try_blacklist() ) {
			$error .= Lang::msg( 'in_blacklist' );
			return;
		}
	}

	/**
	 * Login handler
	 *
	 * @since  1.0
	 * @access public
	 */
	public function wp_authenticate_user( $user, $pswd )
	{

		return $user;
	}

	/**
	 * Authenticate
	 *
	 * @since  1.0
	 * @access public
	 */
	public function authenticate( $user, $username, $password )
	{
		$in_whitelist = $this->try_whitelist();
		if ( is_wp_error( $user ) || $in_whitelist === 'hit' ) {
			return $user;
		}

		$error = new \WP_Error();

		if ( ! $in_whitelist ) {
			$error->add( 'not_in_whitelist', Lang::msg( 'not_in_whitelist' ) );
			$this->_err_added = true;
		}

		if ( $this->try_blacklist() ) {
			$error->add( 'in_blacklist', Lang::msg( 'in_blacklist' ) );
			$this->_err_added = true;
		}

		if ( $this->_err_added ) {
			// bypass verifying user info
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
			return $error;
		}

		return $user;
	}

	/**
	 * Validate if hit whitelist
	 *
	 * @since  1.0
	 * @access public
	 */
	private function try_whitelist()
	{
		$list = self::conf( 'whitelist', array() );
		if ( ! $list ) {
			return true;
		}

		if ( $this->maybe_hit_rule( $list ) ) {
			return 'hit';
		}

		return false;
	}

	/**
	 * Validate if hit blacklist
	 *
	 * @since  1.0
	 * @access public
	 */
	private function try_blacklist()
	{
		$list = self::conf( 'blacklist', array() );
		if ( ! $list ) {
			return false;
		}

		if ( $this->maybe_hit_rule( $list ) ) {
			return 'hit';
		}

		return false;
	}

	/**
	 * Validate if hit whitelist
	 *
	 * @since  1.0
	 * @access public
	 */
	private function maybe_hit_rule( $list )
	{
		if ( ! $this->_visitor_geo_data ) {
			$this->_visitor_geo_data = $this->geo_ip();
		}

		foreach ( $list as $v ) {
			$v = explode( ',', $v );

			// Go through each rule
			foreach ( $v as $v2 ) {

				if ( ! strpos( $v2, ':' ) ) { // Optional `ip:` case
					$curr_k = 'ip';
				}
				else {
					list( $curr_k, $v2 ) = explode( ':', $v2, 2 );
				}

				$curr_k = trim( $curr_k );
				$v2 = trim( $v2 );

				// Invalid rule
				if ( ! $v2 ) {
					continue 2;
				}

				// Rule set not match
				if ( empty( $this->_visitor_geo_data[ $curr_k ] ) ) {
					continue 2;
				}

				$v2 = strtolower( $v2 );
				$visitor_v = strtolower( $this->_visitor_geo_data[ $curr_k ] );
				$visitor_v = trim( $visitor_v );

				// If has IP wildcard range, convert $v2
				if ( $curr_k == 'ip' && strpos( $v2, '*' ) !== false ) {
					// If is same ip type (both are ipv4 or v6)
					$visitor_ip_type = \WP_Http::is_ip_address( $visitor_v );
					if ( $visitor_ip_type == \WP_Http::is_ip_address( $v2 ) ) {
						$ip_separator = $visitor_ip_type == 4 ? '.' : ':';
						$uip = explode( $ip_separator, $visitor_v );
						$v2 = explode( $ip_separator, $v2 );
						foreach ( $uip as $k3 => $v3 ) {
							if ( $v2[ $k3 ] == '*' ) {
								$v2[ $k3 ] = $v3;
							}
						}
						$v2 = implode( $ip_separator, $v2 );
					}

				}

				if ( $visitor_v != $v2 ) {
					continue 2;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Admin setting page
	 *
	 * @since  1.0
	 * @access public
	 */
	public function admin_menu()
	{
		add_options_page( 'Light Login Security', 'Light Login Security', 'manage_options', 'llas', array( $this, 'setting_page' ) );
	}

	/**
	 * Sanitize list
	 *
	 * @since  1.0
	 * @access public
	 */
	private function _sanitize_list( $list )
	{
		if ( ! is_array( $list ) ) {
			$list = explode( "\n", trim( $list ) );
		}

		foreach ( $list as $k => $v ) {
			$list[ $k ] = implode( ', ', array_map( 'trim', explode( ',', $v ) ) );
		}

		return array_filter( $list );
	}

	/**
	 * Display and save options
	 *
	 * @since  1.0
	 * @access public
	 */
	public function setting_page()
	{
		if ( ! empty( $_POST ) ) {
			check_admin_referer( 'llas' );
			// Save options
			$this->conf_update( 'whitelist', $this->_sanitize_list( $_POST[ 'whitelist' ] ) );
			$this->conf_update( 'blacklist', $this->_sanitize_list( $_POST[ 'blacklist' ] ) );
		}

		require_once LLAS_DIR . 'tpl/settings.tpl.php';
	}

	/**
	 * Get visitor's IP
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function ip()
	{
		$_ip = '';
		if ( function_exists( 'apache_request_headers' ) ) {
			$apache_headers = apache_request_headers();
			$_ip = ! empty( $apache_headers['True-Client-IP'] ) ? $apache_headers['True-Client-IP'] : false;
			if ( ! $_ip ) {
				$_ip = ! empty( $apache_headers['X-Forwarded-For'] ) ? $apache_headers['X-Forwarded-For'] : false;
				$_ip = explode( ", ", $_ip );
				$_ip = array_shift( $_ip );
			}

			if ( ! $_ip ) {
				$_ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : false;
			}
		}

		return preg_replace( '/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $_ip );
	}

	/**
	 * Get geolocation info of visitor IP
	 *
	 * @since 1.0
	 * @access public
	 */
	public function geo_ip()
	{
		$ip = self::ip();

		$response = wp_remote_get( "https://www.doapi.us/ip/$ip/json" );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'remote_get_fail', 'Failed to fetch geolocation info', array( 'status' => 404 ) );
		}

		$data = $response[ 'body' ];

		$data = json_decode( $data, true );

		// Build geo data
		$geo_list = array( 'ip' => $ip );
		foreach ( $data as $prefix => $v ) {
			if ( in_array( $prefix, self::PREFIX_SET ) ) {
				$geo_list[ $prefix ] = trim( $v );
			}
		}

		return $geo_list;
	}

	/**
	 * Get option
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function conf( $id, $default_v = false )
	{
		return get_option( 'llas.' . $id, $default_v );
	}

	/**
	 * Update option 
	 *
	 * @since  1.0
	 * @access public
	 */
	public static function conf_update( $id, $data )
	{
		update_option( 'llas.' . $id, $data );
	}

	/**
	 * Get the current instance object.
	 *
	 * @since 1.0
	 * @access public
	 */
	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

}
