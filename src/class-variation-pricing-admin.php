<?php
/**
 * Variation pricing fields.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds pricing inputs to product variations.
 */
class Variation_Pricing_Admin {

	private const NONCE_ACTION = 'pricing_manager_save_variation_pricing';
	private const NONCE_NAME   = 'pricing_manager_variation_nonce';

	/**
	 * Product meta repository.
	 *
	 * @var Product_Meta_Repository
	 */
	private Product_Meta_Repository $product_meta_repository;

	/**
	 * Constructor.
	 *
	 * @param Product_Meta_Repository $product_meta_repository Product meta repository.
	 */
	public function __construct( Product_Meta_Repository $product_meta_repository ) {
		$this->product_meta_repository = $product_meta_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
	}

	/**
	 * Render variation pricing fields.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post.
	 * @return void
	 */
	public function render_variation_fields( int $loop, array $variation_data, $variation ): void {
		unset( $variation_data );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		woocommerce_wp_text_input(
			array(
				'id'                => Product_Meta_Repository::META_BASE_PRICE_USD . '_' . $loop,
				'name'              => Product_Meta_Repository::META_BASE_PRICE_USD . '[' . (int) $variation->ID . ']',
				'value'             => $this->product_meta_repository->get_base_price_usd( (int) $variation->ID ),
				'label'             => __( 'Base price (USD)', 'pricing-manager' ),
				'desc_tip'          => true,
				'description'       => __( 'Internal USD base price used to calculate the EGP customer price.', 'pricing-manager' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.0001',
				),
				'wrapper_class'     => 'form-row form-row-first',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => Product_Meta_Repository::META_PROFIT_MARGIN . '_' . $loop,
				'name'              => Product_Meta_Repository::META_PROFIT_MARGIN . '[' . (int) $variation->ID . ']',
				'value'             => $this->product_meta_repository->get_profit_margin( (int) $variation->ID ),
				'label'             => __( 'Profit margin (%)', 'pricing-manager' ),
				'desc_tip'          => true,
				'description'       => __( 'Percentage margin applied before converting to EGP.', 'pricing-manager' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
				'wrapper_class'     => 'form-row form-row-last',
			)
		);
	}

	/**
	 * Save variation pricing fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 * @return void
	 */
	public function save_variation_fields( int $variation_id, int $loop ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		$base_prices    = isset( $_POST[ Product_Meta_Repository::META_BASE_PRICE_USD ] ) && is_array( $_POST[ Product_Meta_Repository::META_BASE_PRICE_USD ] )
			? wc_clean( wp_unslash( $_POST[ Product_Meta_Repository::META_BASE_PRICE_USD ] ) )
			: array();
		$profit_margins = isset( $_POST[ Product_Meta_Repository::META_PROFIT_MARGIN ] ) && is_array( $_POST[ Product_Meta_Repository::META_PROFIT_MARGIN ] )
			? wc_clean( wp_unslash( $_POST[ Product_Meta_Repository::META_PROFIT_MARGIN ] ) )
			: array();

		$base_price_raw = $this->get_submitted_value( $base_prices, $variation_id, $loop );
		$margin_raw     = $this->get_submitted_value( $profit_margins, $variation_id, $loop );

		$base_price_usd = '' === $base_price_raw ? null : (float) wc_format_decimal( $base_price_raw );
		$profit_margin  = '' === $margin_raw ? 0 : (float) wc_format_decimal( $margin_raw );

		$this->product_meta_repository->save_variation_pricing( $variation_id, $base_price_usd, $profit_margin );
	}

	/**
	 * Get a submitted variation field value.
	 *
	 * @param array $values       Submitted values.
	 * @param int   $variation_id Variation ID.
	 * @param int   $loop         Variation loop index.
	 * @return string
	 */
	private function get_submitted_value( array $values, int $variation_id, int $loop ): string {
		if ( isset( $values[ $variation_id ] ) ) {
			return (string) $values[ $variation_id ];
		}

		if ( isset( $values[ $loop ] ) ) {
			return (string) $values[ $loop ];
		}

		return '';
	}
}
