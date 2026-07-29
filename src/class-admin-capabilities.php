<?php
/**
 * Admin capability helpers.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes plugin admin capabilities.
 */
class Admin_Capabilities {

	/**
	 * Get the capability required to manage pricing features.
	 *
	 * @return string
	 */
	public static function manage_pricing(): string {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}
}
