<?php
/**
 * WooCommerce price filters.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces variation prices with calculated EGP prices.
 */
class Price_Filter {

	/**
	 * Price calculator.
	 *
	 * @var Price_Calculator
	 */
	private Price_Calculator $price_calculator;

	/**
	 * Exchange rate provider.
	 *
	 * @var Exchange_Rate_Provider
	 */
	private Exchange_Rate_Provider $exchange_rate_provider;

	/**
	 * Pricing error handler.
	 *
	 * @var Pricing_Error_Handler
	 */
	private Pricing_Error_Handler $error_handler;

	/**
	 * Constructor.
	 *
	 * @param Price_Calculator       $price_calculator       Price calculator.
	 * @param Exchange_Rate_Provider $exchange_rate_provider Exchange rate provider.
	 * @param Pricing_Error_Handler  $error_handler          Pricing error handler.
	 */
	public function __construct( Price_Calculator $price_calculator, Exchange_Rate_Provider $exchange_rate_provider, Pricing_Error_Handler $error_handler ) {
		$this->price_calculator       = $price_calculator;
		$this->exchange_rate_provider = $exchange_rate_provider;
		$this->error_handler          = $error_handler;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_variation_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'filter_variation_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'filter_variation_sale_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'filter_variation_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_variation_sale_price' ), 20, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'add_variation_prices_hash' ), 20, 3 );
		add_filter( 'woocommerce_currency', array( $this, 'force_egp_currency' ), 20 );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'force_egp_currency_symbol' ), 20, 2 );
	}

	/**
	 * Filter variation price values.
	 *
	 * @param string|float $price   Existing price.
	 * @param mixed        $product Product object.
	 * @return string|float
	 */
	public function filter_variation_price( $price, $product ) {
		$variation_id = $this->get_variation_id( $product );

		if ( ! $variation_id ) {
			return $price;
		}

		try {
			$calculated_price = $this->price_calculator->calculate_variation_price( $variation_id );
		} catch ( \Throwable $exception ) {
			$this->error_handler->report(
				'price_filter_exception',
				__( 'Pricing Manager could not prepare a customer-facing price.', 'pricing-manager' ),
				array(
					'variation_id' => $variation_id,
					'exception'    => $exception->getMessage(),
				)
			);

			return $price;
		}

		return null === $calculated_price ? $price : (string) $calculated_price;
	}

	/**
	 * Disable sale prices for managed variations.
	 *
	 * @param string|float $price   Existing sale price.
	 * @param mixed        $product Product object.
	 * @return string|float
	 */
	public function filter_variation_sale_price( $price, $product ) {
		$variation_id = $this->get_variation_id( $product );

		if ( ! $variation_id ) {
			return $price;
		}

		try {
			$calculated_price = $this->price_calculator->calculate_variation_price( $variation_id );
		} catch ( \Throwable $exception ) {
			$this->error_handler->report(
				'sale_price_filter_exception',
				__( 'Pricing Manager could not prepare a customer-facing sale price.', 'pricing-manager' ),
				array(
					'variation_id' => $variation_id,
					'exception'    => $exception->getMessage(),
				)
			);

			return $price;
		}

		return null === $calculated_price ? $price : '';
	}

	/**
	 * Force WooCommerce prices to render as EGP.
	 *
	 * @param string $currency Existing currency code.
	 * @return string
	 */
	public function force_egp_currency( string $currency ): string {
		unset( $currency );

		return 'EGP';
	}

	/**
	 * Force WooCommerce currency symbol to EGP.
	 *
	 * @param string $currency_symbol Existing symbol.
	 * @param string $currency        Currency code.
	 * @return string
	 */
	public function force_egp_currency_symbol( string $currency_symbol, string $currency ): string {
		unset( $currency_symbol, $currency );

		return 'EGP';
	}

	/**
	 * Add managed pricing inputs to WooCommerce's variation price cache hash.
	 *
	 * @param array $hash    Cache hash parts.
	 * @param mixed $product Variable product.
	 * @param bool  $display Display context flag.
	 * @return array
	 */
	public function add_variation_prices_hash( array $hash, $product, bool $display ): array {
		unset( $display );

		$hash['pricing_manager_exchange_rate'] = $this->exchange_rate_provider->get_exchange_rate();

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_children' ) ) {
			return $hash;
		}

		$variation_prices = array();

		foreach ( $product->get_children() as $variation_id ) {
			try {
				$variation_prices[ $variation_id ] = $this->price_calculator->calculate_variation_price( (int) $variation_id );
			} catch ( \Throwable $exception ) {
				$variation_prices[ $variation_id ] = null;
				$this->error_handler->report(
					'price_hash_exception',
					__( 'Pricing Manager could not include a variation in the price cache hash.', 'pricing-manager' ),
					array(
						'variation_id' => (int) $variation_id,
						'exception'    => $exception->getMessage(),
					)
				);
			}
		}

		$hash['pricing_manager_variation_prices'] = wp_json_encode( $variation_prices );

		return $hash;
	}

	/**
	 * Resolve a variation ID from a WooCommerce product object.
	 *
	 * @param mixed $product Product object.
	 * @return int
	 */
	private function get_variation_id( $product ): int {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}
}
