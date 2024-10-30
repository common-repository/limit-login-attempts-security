<?php
/**
 * Rest class
 *
 * @since 1.0
 */
namespace llas;

defined( 'WPINC' ) || exit;

class REST
{
	private static $_instance;

	/**
	 * Init
	 *
	 * @since  1.0
	 * @access private
	 */
	private function __construct()
	{
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Register REST hooks
	 *
	 * @since  1.0
	 * @access public
	 */
	public function rest_api_init()
	{
		register_rest_route( 'llas/v1', '/myip', array(
			'methods' => 'GET',
			'callback' => array( $this, 'myip' ),
		) );
	}

	/**
	 * check user ip's Geolocation
	 *
	 * @since  1.0
	 */
	public function myip()
	{
		return Core::get_instance()->geo_ip();
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
