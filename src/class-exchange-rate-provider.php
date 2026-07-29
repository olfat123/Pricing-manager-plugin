<?php
/**
 * Exchange rate provider.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches and caches the USD to EGP exchange rate.
 */
class Exchange_Rate_Provider {

	private const RATE_ENDPOINT = 'https://open.er-api.com/v6/latest/USD';

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $settings_repository;

	/**
	 * Constructor.
	 *
	 * @param Settings_Repository $settings_repository Settings repository.
	 */
	public function __construct( Settings_Repository $settings_repository ) {
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Get the current exchange rate.
	 *
	 * @return float
	 */
	public function get_exchange_rate(): float {
		return $this->ensure_default_exchange_rate();
	}

	/**
	 * Ensure an online exchange rate exists.
	 *
	 * @return float
	 */
	public function ensure_default_exchange_rate(): float {
		$current_rate = $this->settings_repository->get_exchange_rate();

		if ( $current_rate > 0 || get_transient( Settings_Repository::TRANSIENT_RATE_LOCK ) ) {
			return $current_rate;
		}

		set_transient( Settings_Repository::TRANSIENT_RATE_LOCK, 1, HOUR_IN_SECONDS );

		$response = wp_safe_remote_get(
			self::RATE_ENDPOINT,
			array(
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return 0;
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );
		$rate    = isset( $payload['rates']['EGP'] ) ? (float) $payload['rates']['EGP'] : 0;

		if ( $rate > 0 ) {
			$this->settings_repository->save_exchange_rate( $rate );
			delete_transient( Settings_Repository::TRANSIENT_RATE_LOCK );
		}

		return $rate > 0 ? $rate : 0;
	}
}
