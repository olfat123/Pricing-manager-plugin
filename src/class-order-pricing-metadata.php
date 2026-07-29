<?php
/**
 * Order pricing metadata integration.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Stores immutable pricing snapshots on WooCommerce order items.
 */
class Order_Pricing_Metadata {

	public const META_BASE_PRICE_USD = '_pricing_manager_base_price_usd';
	public const META_PROFIT_MARGIN  = '_pricing_manager_profit_margin_percent';
	public const META_EXCHANGE_RATE  = '_pricing_manager_usd_egp_exchange_rate';

	/**
	 * Product meta repository.
	 *
	 * @var Product_Meta_Repository
	 */
	private Product_Meta_Repository $product_meta_repository;

	/**
	 * Exchange rate provider.
	 *
	 * @var Exchange_Rate_Provider
	 */
	private Exchange_Rate_Provider $exchange_rate_provider;

	/**
	 * Price calculator.
	 *
	 * @var Price_Calculator
	 */
	private Price_Calculator $price_calculator;

	/**
	 * Pricing error handler.
	 *
	 * @var Pricing_Error_Handler
	 */
	private Pricing_Error_Handler $error_handler;

	/**
	 * Constructor.
	 *
	 * @param Product_Meta_Repository $product_meta_repository Product meta repository.
	 * @param Exchange_Rate_Provider  $exchange_rate_provider  Exchange rate provider.
	 * @param Price_Calculator        $price_calculator        Price calculator.
	 * @param Pricing_Error_Handler   $error_handler           Pricing error handler.
	 */
	public function __construct( Product_Meta_Repository $product_meta_repository, Exchange_Rate_Provider $exchange_rate_provider, Price_Calculator $price_calculator, Pricing_Error_Handler $error_handler ) {
		$this->product_meta_repository = $product_meta_repository;
		$this->exchange_rate_provider  = $exchange_rate_provider;
		$this->price_calculator        = $price_calculator;
		$this->error_handler           = $error_handler;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'store_pricing_snapshot' ), 10, 4 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'format_order_item_meta_key' ), 10, 3 );
	}

	/**
	 * Store pricing metadata on an order line item.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         Order object.
	 *
	 * @return void
	 */
	public function store_pricing_snapshot( $item, string $cart_item_key, array $values, $order ): void {
		unset( $cart_item_key, $order );

		$variation_id = isset( $values['variation_id'] ) ? (int) $values['variation_id'] : 0;

		if ( $variation_id <= 0 ) {
			return;
		}

		try {
			$base_price_usd = $this->product_meta_repository->get_base_price_usd( $variation_id );
			$exchange_rate  = $this->exchange_rate_provider->get_exchange_rate();
			$calculated_egp = $this->price_calculator->calculate_variation_price( $variation_id );
		} catch ( \Throwable $exception ) {
			$this->error_handler->report(
				'order_snapshot_exception',
				__( 'Pricing Manager could not store pricing metadata on an order item.', 'pricing-manager' ),
				array(
					'variation_id' => $variation_id,
					'exception'    => $exception->getMessage(),
				)
			);

			return;
		}

		if ( null === $base_price_usd || $exchange_rate <= 0 || null === $calculated_egp ) {
			$this->error_handler->report(
				'order_snapshot_invalid',
				__( 'Pricing Manager skipped order pricing metadata because pricing inputs were invalid.', 'pricing-manager' ),
				array( 'variation_id' => $variation_id )
			);

			return;
		}

		$profit_margin = $this->product_meta_repository->get_profit_margin( $variation_id );

		$this->add_immutable_meta( $item, self::META_BASE_PRICE_USD, wc_format_decimal( $base_price_usd ) );
		$this->add_immutable_meta( $item, self::META_PROFIT_MARGIN, wc_format_decimal( $profit_margin ) );
		$this->add_immutable_meta( $item, self::META_EXCHANGE_RATE, wc_format_decimal( $exchange_rate, 4 ) );
	}

	/**
	 * Add metadata once so a snapshot is not overwritten later.
	 *
	 * @param \WC_Order_Item_Product $item  Order item.
	 * @param string                 $key   Meta key.
	 * @param string                 $value Meta value.
	 *
	 * @return void
	 */
	private function add_immutable_meta( $item, string $key, string $value ): void {
		if ( '' !== $item->get_meta( $key, true ) ) {
			return;
		}

		$item->add_meta_data( $key, $value, true );
	}

	/**
	 * Format pricing metadata labels in order item displays.
	 *
	 * @param string $display_key Display key.
	 * @param object $meta        Meta object.
	 * @param mixed  $item        Order item.
	 *
	 * @return string
	 */
	public function format_order_item_meta_key( string $display_key, object $meta, $item ): string {
		unset( $item );

		$labels = $this->get_meta_labels();

		if ( isset( $labels[ $meta->key ] ) ) {
			return $labels[ $meta->key ];
		}

		return $display_key;
	}

	/**
	 * Get readable pricing metadata labels.
	 *
	 * @return array
	 */
	private function get_meta_labels(): array {
		return array(
			self::META_BASE_PRICE_USD => __( 'Base price (USD)', 'pricing-manager' ),
			self::META_PROFIT_MARGIN  => __( 'Profit margin (%)', 'pricing-manager' ),
			self::META_EXCHANGE_RATE  => __( 'USD to EGP exchange rate', 'pricing-manager' ),
		);
	}
}
