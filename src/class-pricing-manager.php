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
		$this->exchange_rate_provider  = new Exchange_Rate_Provider( $this->settings_repository );
		$this->product_meta_repository = new Product_Meta_Repository();
		$this->price_calculator        = new Price_Calculator( $this->exchange_rate_provider, $this->product_meta_repository );

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

		$admin_settings         = new Admin_Settings( $this->settings_repository );
		$variation_pricing      = new Variation_Pricing_Admin( $this->product_meta_repository );
		$customer_price_filters = new Price_Filter( $this->price_calculator, $this->exchange_rate_provider );

		$admin_settings->register_hooks();
		$variation_pricing->register_hooks();
		$customer_price_filters->register_hooks();
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
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
