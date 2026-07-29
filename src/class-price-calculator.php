<?php
/**
 * Pricing business logic.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates customer-facing EGP prices.
 */
class Price_Calculator {

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
	 * Pricing error handler.
	 *
	 * @var Pricing_Error_Handler
	 */
	private Pricing_Error_Handler $error_handler;

	/**
	 * Constructor.
	 *
	 * @param Exchange_Rate_Provider  $exchange_rate_provider  Exchange rate provider.
	 * @param Product_Meta_Repository $product_meta_repository Product meta repository.
	 * @param Pricing_Error_Handler   $error_handler           Pricing error handler.
	 */
	public function __construct( Exchange_Rate_Provider $exchange_rate_provider, Product_Meta_Repository $product_meta_repository, Pricing_Error_Handler $error_handler ) {
		$this->exchange_rate_provider  = $exchange_rate_provider;
		$this->product_meta_repository = $product_meta_repository;
		$this->error_handler           = $error_handler;
	}

	/**
	 * Calculate a variation price in EGP.
	 *
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	public function calculate_variation_price( int $variation_id ): ?float {
		try {
			$base_price_usd = $this->product_meta_repository->get_base_price_usd( $variation_id );
			$exchange_rate  = $this->exchange_rate_provider->get_exchange_rate();

			if ( null === $base_price_usd ) {
				if ( ! $this->product_meta_repository->has_pricing_metadata( $variation_id ) ) {
					return null;
				}

				$this->error_handler->report(
					'missing_base_price',
					__( 'Pricing Manager found a variation without a valid base USD price.', 'pricing-manager' ),
					array( 'variation_id' => $variation_id )
				);

				return null;
			}

			if ( $exchange_rate <= 0 ) {
				$this->error_handler->report(
					'missing_exchange_rate',
					__( 'Pricing Manager cannot calculate prices because the USD to EGP exchange rate is missing.', 'pricing-manager' ),
					array( 'variation_id' => $variation_id )
				);

				return null;
			}

			return $this->calculate_price( $base_price_usd, $this->product_meta_repository->get_profit_margin( $variation_id ), $exchange_rate );
		} catch ( \Throwable $exception ) {
			$this->error_handler->report(
				'calculation_exception',
				__( 'Pricing Manager could not calculate a variation price.', 'pricing-manager' ),
				array(
					'variation_id' => $variation_id,
					'exception'    => $exception->getMessage(),
				)
			);

			return null;
		}
	}

	/**
	 * Calculate a price from explicit values.
	 *
	 * @param float $base_price_usd Base price in USD.
	 * @param float $profit_margin  Profit margin percentage.
	 * @param float $exchange_rate  USD to EGP exchange rate.
	 * @return float
	 * @throws \InvalidArgumentException When pricing inputs are invalid.
	 * @throws \RuntimeException When the calculated price is invalid.
	 */
	public function calculate_price( float $base_price_usd, float $profit_margin, float $exchange_rate ): float {
		if ( $base_price_usd < 0 || $profit_margin < 0 || $exchange_rate <= 0 ) {
			throw new \InvalidArgumentException( 'Invalid pricing input.' );
		}

		$price = $base_price_usd * ( 1 + ( $profit_margin / 100 ) ) * $exchange_rate;

		if ( ! is_finite( $price ) || $price <= 0 ) {
			throw new \RuntimeException( 'Calculated price is invalid.' );
		}

		return round( $price, wc_get_price_decimals() );
	}
}
