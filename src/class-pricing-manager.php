<?php
/**
 * Pricing Manager Plugin
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Pricing_Manager
 *
 * @package Yallacoins\PricingManager
 */
class Pricing_Manager {

	/**
	 * Instance to call certain functions globally within the plugin
	 *
	 * @var self|null instance
	 */
	protected static ?Pricing_Manager $instance = null;

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $settings_repository;

	/**
	 * Pricing error handler.
	 *
	 * @var Pricing_Error_Handler
	 */
	private Pricing_Error_Handler $error_handler;

	/**
	 * Exchange rate provider.
	 *
	 * @var Exchange_Rate_Provider
	 */
	private Exchange_Rate_Provider $exchange_rate_provider;

	/**
	 * Product meta repository.
	 *
	 * @var Product_Meta_Repository
	 */
	private Product_Meta_Repository $product_meta_repository;

	/**
	 * Price calculator.
	 *
	 * @var Price_Calculator
	 */
	private Price_Calculator $price_calculator;

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		$this->settings_repository     = new Settings_Repository();
		$this->error_handler           = new Pricing_Error_Handler();
		$this->exchange_rate_provider  = new Exchange_Rate_Provider( $this->settings_repository, $this->error_handler );
		$this->product_meta_repository = new Product_Meta_Repository();
		$this->price_calculator        = new Price_Calculator( $this->exchange_rate_provider, $this->product_meta_repository, $this->error_handler );

		add_action( 'init', array( $this, 'load_plugin' ), 0 );
		add_action( 'pricing_manager_plugin_activated', array( $this, 'activation_hooks' ) );
		add_action( 'pricing_manager_plugin_deactivated', array( $this, 'deactivation_hooks' ) );
	}

	/**
	 * Pricing_Manager Customization.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @return Pricing_Manager|null Pricing_Manager instance.
	 */
	public static function instance(): ?Pricing_Manager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin activation hooks.
	 */
	public function activation_hooks() {
		$this->exchange_rate_provider->ensure_default_exchange_rate();
	}

	/**
	 * Plugin activation hooks.
	 */
	public function deactivation_hooks() {
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_woocommerce_missing_notice' ) );
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks(): void {
		add_action( 'init', array( $this, 'init' ), 1 );

	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		$admin_settings         = new Admin_Settings( $this->settings_repository );
		$variation_pricing      = new Variation_Pricing_Admin( $this->product_meta_repository );
		$customer_price_filters = new Price_Filter( $this->price_calculator, $this->exchange_rate_provider, $this->error_handler );
		$order_pricing_metadata = new Order_Pricing_Metadata( $this->product_meta_repository, $this->exchange_rate_provider, $this->price_calculator, $this->error_handler );
		$digital_statuses       = new Digital_Processing_Statuses();
		$admin_dashboard        = new Admin_Dashboard( new Dashboard_Repository() );

		$admin_settings->register_hooks();
		$this->error_handler->register_hooks();
		$variation_pricing->register_hooks();
		$customer_price_filters->register_hooks();
		$order_pricing_metadata->register_hooks();
		$digital_statuses->register_hooks();
		$admin_dashboard->register_hooks();
	}

	/**
	 * Render a WooCommerce dependency notice.
	 *
	 * @return void
	 */
	public function render_woocommerce_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Pricing Manager requires WooCommerce to be installed and active.', 'pricing-manager' )
		);
	}
}
