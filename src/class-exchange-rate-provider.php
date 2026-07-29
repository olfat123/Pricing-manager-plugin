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
	 * Pricing error handler.
	 *
	 * @var Pricing_Error_Handler
	 */
	private Pricing_Error_Handler $error_handler;

	/**
	 * Constructor.
	 *
	 * @param Settings_Repository   $settings_repository Settings repository.
	 * @param Pricing_Error_Handler $error_handler       Pricing error handler.
	 */
	public function __construct( Settings_Repository $settings_repository, Pricing_Error_Handler $error_handler ) {
		$this->settings_repository = $settings_repository;
		$this->error_handler       = $error_handler;
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

		try {
			$response = wp_safe_remote_get(
				self::RATE_ENDPOINT,
				array(
					'timeout' => 8,
				)
			);
		} catch ( \Throwable $exception ) {
			$this->error_handler->report(
				'exchange_rate_exception',
				__( 'Pricing Manager could not fetch the online USD to EGP exchange rate.', 'pricing-manager' ),
				array( 'exception' => $exception->getMessage() )
			);

			return 0;
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->error_handler->report(
				'exchange_rate_unavailable',
				__( 'Pricing Manager could not fetch the online USD to EGP exchange rate.', 'pricing-manager' ),
				array(
					'response_code' => is_wp_error( $response ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response ),
				)
			);

			return 0;
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $payload ) ) {
			$this->error_handler->report(
				'exchange_rate_invalid_payload',
				__( 'Pricing Manager received an invalid online exchange-rate response.', 'pricing-manager' )
			);

			return 0;
		}

		$rate = isset( $payload['rates']['EGP'] ) && is_numeric( $payload['rates']['EGP'] ) ? (float) $payload['rates']['EGP'] : 0;

		if ( $rate <= 0 ) {
			$this->error_handler->report(
				'exchange_rate_missing',
				__( 'Pricing Manager could not find a valid EGP rate in the online exchange-rate response.', 'pricing-manager' )
			);
		}

		if ( $rate > 0 ) {
			$this->settings_repository->save_online_exchange_rate( $rate );
			delete_transient( Settings_Repository::TRANSIENT_RATE_LOCK );
		}

		return $rate > 0 ? $rate : 0;
	}
}
