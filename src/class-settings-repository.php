<?php
/**
 * Settings data access.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes plugin settings.
 */
class Settings_Repository {

	public const OPTION_EXCHANGE_RATE = 'pricing_manager_usd_egp_exchange_rate';
	public const TRANSIENT_RATE_LOCK  = 'pricing_manager_usd_egp_exchange_rate_lock';

	/**
	 * Get the configured USD to EGP exchange rate.
	 *
	 * @return float
	 */
	public function get_exchange_rate(): float {
		$rate = (float) get_option( self::OPTION_EXCHANGE_RATE, 0 );

		return $rate > 0 ? $rate : 0;
	}

	/**
	 * Save the configured USD to EGP exchange rate.
	 *
	 * @param float $exchange_rate Exchange rate.
	 * @return void
	 */
	public function save_exchange_rate( float $exchange_rate ): void {
		update_option( self::OPTION_EXCHANGE_RATE, max( 0, $exchange_rate ) );

		if ( class_exists( '\WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::get_transient_version( 'product', true );
		}
	}
}
