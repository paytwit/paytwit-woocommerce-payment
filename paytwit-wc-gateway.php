<?php
/*
   Plugin name: افزونه ووکامرس   پی تویت 
   Plugin URI: http://paytwit.com/
   Description: یک افرونه درگاه پرداخت برای پی تویت 
   Version: 1.0
   Author: behzad rohizadeh
   Author URI: http://paytwit.com/
   Text Domain:paytwit
   Domain Path: /languages
*/


class Paytwit_WC_Main {

	
	protected static $_instance = null;

	/**
	 * Name of plugin in wordpress admin area
	 * @var
	 */
	private $name;

	/**
	 * Description of plugin in wordpress admin area
	 * @var
	 */
	private $description;

	/**
	 * Author of plugin in wordpress admin area
	 * @var
	 */
	private $author;

	
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();

		$this->name         = __('PAY gateway for Woocommerce', 'paytwit');
		$this->description  = __('paytwit.com payment gateway for Woocommerce', 'paytwit');
		$this->author       = __('Paytwit', 'paytwit');
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'localization' ) );
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'load_custom_scripts'));

	}

	public function load_custom_scripts(){
		wp_enqueue_style( 'pay_gateway', paytwit_URL.'/assets/css/pay_gateway.css', array(), '1.0.1' );

	}
	/**
	 * Make plugin translatable
	 */
	public function localization() {
		$plugin_rel_path = plugin_basename(paytwit_PATH).'/languages';
		load_plugin_textdomain('paytwit', false, $plugin_rel_path);
	}

	/**
	 *
	 * Add plugin gateway class to woocommerce gateways
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = 'Paytwit_WC_gateway';
		return $gateways;
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Define PAY Constants.
	 */
	private function define_constants() {
		$this->define( 'paytwit_URL', plugin_dir_url(__FILE__) );
		$this->define( 'paytwit_PATH', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		/**
		 * Gateway Class.
		 */
		include_once( paytwit_PATH . 'class-gateway.php' );

	}
	
}
function get_instance_pay_gateway() {
	return Paytwit_WC_Main::instance();
}
get_instance_pay_gateway();