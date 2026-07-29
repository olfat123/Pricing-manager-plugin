<?php
/**
 * Product pricing metadata data access.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes variation pricing metadata.
 */
class Product_Meta_Repository {

	public const META_BASE_PRICE_USD = '_pricing_manager_base_price_usd';
	public const META_PROFIT_MARGIN  = '_pricing_manager_profit_margin';

	/**
	 * Get the USD base price for a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return float|null
	 */
	public function get_base_price_usd( int $variation_id ): ?float {
		$value = get_post_meta( $variation_id, self::META_BASE_PRICE_USD, true );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$base_price = (float) $value;

		return $base_price > 0 ? $base_price : null;
	}

	/**
	 * Get the profit margin percentage for a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return float
	 */
	public function get_profit_margin( int $variation_id ): float {
		$value = get_post_meta( $variation_id, self::META_PROFIT_MARGIN, true );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return 0;
		}

		return max( 0, (float) $value );
	}

	/**
	 * Check whether a variation has any managed pricing metadata.
	 *
	 * @param int $variation_id Variation ID.
	 * @return bool
	 */
	public function has_pricing_metadata( int $variation_id ): bool {
		return metadata_exists( 'post', $variation_id, self::META_BASE_PRICE_USD )
			|| metadata_exists( 'post', $variation_id, self::META_PROFIT_MARGIN );
	}

	/**
	 * Save pricing metadata for a variation.
	 *
	 * @param int        $variation_id   Variation ID.
	 * @param float|null $base_price_usd USD base price.
	 * @param float      $profit_margin  Profit margin percentage.
	 * @return void
	 */
	public function save_variation_pricing( int $variation_id, ?float $base_price_usd, float $profit_margin ): void {
		if ( null === $base_price_usd ) {
			delete_post_meta( $variation_id, self::META_BASE_PRICE_USD );
		} else {
			update_post_meta( $variation_id, self::META_BASE_PRICE_USD, max( 0, $base_price_usd ) );
		}

		update_post_meta( $variation_id, self::META_PROFIT_MARGIN, max( 0, $profit_margin ) );

		$this->clear_product_price_cache( $variation_id );
	}

	/**
	 * Clear WooCommerce product price caches.
	 *
	 * @param int $variation_id Variation ID.
	 * @return void
	 */
	private function clear_product_price_cache( int $variation_id ): void {
		if ( class_exists( '\WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::get_transient_version( 'product', true );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $variation_id );

			$parent_id = wp_get_post_parent_id( $variation_id );

			if ( $parent_id ) {
				wc_delete_product_transients( $parent_id );
			}
		}
	}
}
