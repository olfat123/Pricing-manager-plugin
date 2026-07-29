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
	 * Construct the plugin.
	 */
	public function __construct() {
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
		// Activation hooks here.
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
	}

}
