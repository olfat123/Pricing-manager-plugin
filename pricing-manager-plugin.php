<?php
/**
 * Plugin Name: Pricing manager
 * Plugin URI: https://pricing.com
 * Description: Pricing manager plugin.
 * Author: Olfat Hakeem
 * Version: 1.0.0
 * Text Domain: pricing-manager
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'PRICING_MANAGER_PLUGIN_VERSION' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_VERSION', '1.0.0' );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_DEBUG' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_DEBUG', true );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_FILE' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_BASENAME' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_BASENAME', plugin_basename( PRICING_MANAGER_PLUGIN_FILE ) );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_DIR_PATH' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_DIR_PATH', untrailingslashit( plugin_dir_path( PRICING_MANAGER_PLUGIN_FILE ) ) );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_TEMPLATES_DIR_PATH' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_TEMPLATES_DIR_PATH', untrailingslashit( plugin_dir_path( PRICING_MANAGER_PLUGIN_FILE ) ) . '/templates/' );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_DIR_URL' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_DIR_URL', untrailingslashit( plugins_url( '/', PRICING_MANAGER_PLUGIN_FILE ) ) );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_JS_DIR_URL' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_JS_DIR_URL', untrailingslashit( plugins_url( '/assets/js/', PRICING_MANAGER_PLUGIN_FILE ) ) );
}
if ( ! defined( 'PRICING_MANAGER_PLUGIN_CSS_DIR_URL' ) ) {
	define( 'PRICING_MANAGER_PLUGIN_CSS_DIR_URL', untrailingslashit( plugins_url( '/assets/css/', PRICING_MANAGER_PLUGIN_FILE ) ) );
}

register_activation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pricing_manager_plugin_activated' );
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'pricing_manager_plugin_deactivated' );
	}
);

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

Pricing_Manager::instance();
