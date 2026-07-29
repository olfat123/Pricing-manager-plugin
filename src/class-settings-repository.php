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

	public const OPTION_EXCHANGE_RATE        = 'pricing_manager_usd_egp_exchange_rate';
	public const OPTION_ONLINE_EXCHANGE_RATE = 'pricing_manager_online_usd_egp_exchange_rate';
	public const TRANSIENT_RATE_LOCK         = 'pricing_manager_usd_egp_exchange_rate_lock';

	/**
	 * Get the effective USD to EGP exchange rate.
	 *
	 * @return float
	 */
	public function get_exchange_rate(): float {
		$manual_rate = $this->get_manual_exchange_rate();

		if ( $manual_rate > 0 ) {
			return $manual_rate;
		}

		return $this->get_online_exchange_rate();
	}

	/**
	 * Get the manually configured USD to EGP exchange rate.
	 *
	 * @return float
	 */
	public function get_manual_exchange_rate(): float {
		$rate = (float) get_option( self::OPTION_EXCHANGE_RATE, 0 );

		return $rate > 0 ? $rate : 0;
	}

	/**
	 * Get the cached online USD to EGP exchange rate.
	 *
	 * @return float
	 */
	public function get_online_exchange_rate(): float {
		$rate = (float) get_option( self::OPTION_ONLINE_EXCHANGE_RATE, 0 );

		return $rate > 0 ? $rate : 0;
	}

	/**
	 * Save the manually configured USD to EGP exchange rate.
	 *
	 * @param float $exchange_rate Exchange rate.
	 * @return void
	 */
	public function save_manual_exchange_rate( float $exchange_rate ): void {
		if ( $exchange_rate > 0 ) {
			update_option( self::OPTION_EXCHANGE_RATE, $exchange_rate );
		} else {
			delete_option( self::OPTION_EXCHANGE_RATE );
		}

		$this->clear_product_price_cache();
	}

	/**
	 * Save the cached online USD to EGP exchange rate.
	 *
	 * @param float $exchange_rate Exchange rate.
	 * @return void
	 */
	public function save_online_exchange_rate( float $exchange_rate ): void {
		update_option( self::OPTION_ONLINE_EXCHANGE_RATE, max( 0, $exchange_rate ) );

		$this->clear_product_price_cache();
	}

	/**
	 * Clear product price caches.
	 *
	 * @return void
	 */
	public function clear_product_price_cache(): void {
		if ( class_exists( '\WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::get_transient_version( 'product', true );
		}
	}
}
