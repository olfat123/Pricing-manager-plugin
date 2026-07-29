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
	 * Constructor.
	 *
	 * @param Exchange_Rate_Provider  $exchange_rate_provider  Exchange rate provider.
	 * @param Product_Meta_Repository $product_meta_repository Product meta repository.
	 */
	public function __construct( Exchange_Rate_Provider $exchange_rate_provider, Product_Meta_Repository $product_meta_repository ) {
		$this->exchange_rate_provider  = $exchange_rate_provider;
		$this->product_meta_repository = $product_meta_repository;
	}

	/**
	 * Calculate a variation price in EGP.
	 *
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	public function calculate_variation_price( int $variation_id ): ?float {
		$base_price_usd = $this->product_meta_repository->get_base_price_usd( $variation_id );
		$exchange_rate  = $this->exchange_rate_provider->get_exchange_rate();

		if ( null === $base_price_usd || $exchange_rate <= 0 ) {
			return null;
		}

		return $this->calculate_price( $base_price_usd, $this->product_meta_repository->get_profit_margin( $variation_id ), $exchange_rate );
	}

	/**
	 * Calculate a price from explicit values.
	 *
	 * @param float $base_price_usd Base price in USD.
	 * @param float $profit_margin  Profit margin percentage.
	 * @param float $exchange_rate  USD to EGP exchange rate.
	 * @return float
	 */
	public function calculate_price( float $base_price_usd, float $profit_margin, float $exchange_rate ): float {
		$price = $base_price_usd * ( 1 + ( $profit_margin / 100 ) ) * $exchange_rate;

		return round( $price, wc_get_price_decimals() );
	}
}
