<?php
/**
 * Digital processing statuses.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds independent payment and fulfillment statuses to orders.
 */
class Digital_Processing_Statuses {

	public const META_PAYMENT_STATUS     = '_pricing_manager_payment_status';
	public const META_FULFILLMENT_STATUS = '_pricing_manager_fulfillment_status';

	private const NONCE_ACTION = 'pricing_manager_save_digital_processing_statuses';
	private const NONCE_NAME   = 'pricing_manager_digital_processing_nonce';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register_meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_statuses' ), 10, 2 );
	}

	/**
	 * Register the order edit meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'pricing-manager-digital-processing',
			__( 'Digital Processing', 'pricing-manager' ),
			array( $this, 'render_meta_box' ),
			$this->get_order_screen_ids(),
			'side',
			'default'
		);
	}

	/**
	 * Render payment and fulfillment status fields.
	 *
	 * @param mixed $post_or_order_object Post or order object.
	 * @return void
	 */
	public function render_meta_box( $post_or_order_object ): void {
		$order = $this->resolve_order( $post_or_order_object );

		if ( ! $order ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$payment_status     = $this->get_order_status_value( $order, self::META_PAYMENT_STATUS, 'pending' );
		$fulfillment_status = $this->get_order_status_value( $order, self::META_FULFILLMENT_STATUS, 'unfulfilled' );
		?>
		<p>
			<label for="pricing-manager-payment-status">
				<strong><?php echo esc_html__( 'Payment Status', 'pricing-manager' ); ?></strong>
			</label>
			<select
				id="pricing-manager-payment-status"
				name="pricing_manager_payment_status"
				class="widefat"
			>
				<?php foreach ( $this->get_payment_statuses() as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $payment_status, $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="pricing-manager-fulfillment-status">
				<strong><?php echo esc_html__( 'Fulfillment Status', 'pricing-manager' ); ?></strong>
			</label>
			<select
				id="pricing-manager-fulfillment-status"
				name="pricing_manager_fulfillment_status"
				class="widefat"
			>
				<?php foreach ( $this->get_fulfillment_statuses() as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $fulfillment_status, $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Save payment and fulfillment statuses.
	 *
	 * @param int   $order_id Order ID.
	 * @param mixed $post     Post object.
	 * @return void
	 */
	public function save_statuses( int $order_id, $post ): void {
		unset( $post );

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$payment_status     = isset( $_POST['pricing_manager_payment_status'] ) ? sanitize_key( wp_unslash( $_POST['pricing_manager_payment_status'] ) ) : '';
		$fulfillment_status = isset( $_POST['pricing_manager_fulfillment_status'] ) ? sanitize_key( wp_unslash( $_POST['pricing_manager_fulfillment_status'] ) ) : '';
		$payment_status     = $this->validate_status( $payment_status, $this->get_payment_statuses(), 'pending' );
		$fulfillment_status = $this->validate_status( $fulfillment_status, $this->get_fulfillment_statuses(), 'unfulfilled' );

		$previous_payment_status     = $this->get_order_status_value( $order, self::META_PAYMENT_STATUS, '' );
		$previous_fulfillment_status = $this->get_order_status_value( $order, self::META_FULFILLMENT_STATUS, '' );

		$order->update_meta_data( self::META_PAYMENT_STATUS, $payment_status );
		$order->update_meta_data( self::META_FULFILLMENT_STATUS, $fulfillment_status );

		if ( $payment_status !== $previous_payment_status || $fulfillment_status !== $previous_fulfillment_status ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: payment status, 2: fulfillment status. */
					__( 'Digital processing statuses updated. Payment: %1$s. Fulfillment: %2$s.', 'pricing-manager' ),
					$this->get_payment_statuses()[ $payment_status ],
					$this->get_fulfillment_statuses()[ $fulfillment_status ]
				)
			);
		}

		$order->save();
	}

	/**
	 * Resolve an order from the meta box context.
	 *
	 * @param mixed $post_or_order_object Post or order object.
	 * @return \WC_Order|null
	 */
	private function resolve_order( $post_or_order_object ): ?\WC_Order {
		if ( $post_or_order_object instanceof \WC_Order ) {
			return $post_or_order_object;
		}

		if ( is_object( $post_or_order_object ) && isset( $post_or_order_object->ID ) ) {
			$order = wc_get_order( (int) $post_or_order_object->ID );

			return $order instanceof \WC_Order ? $order : null;
		}

		return null;
	}

	/**
	 * Get an order meta status value.
	 *
	 * @param \WC_Order $order        Order object.
	 * @param string    $meta_key     Meta key.
	 * @param string    $default_value Default value.
	 * @return string
	 */
	private function get_order_status_value( \WC_Order $order, string $meta_key, string $default_value ): string {
		$value = sanitize_key( (string) $order->get_meta( $meta_key, true ) );

		return '' === $value ? $default_value : $value;
	}

	/**
	 * Validate a submitted status.
	 *
	 * @param string $status   Submitted status.
	 * @param array  $statuses Allowed statuses.
	 * @param string $default_status Default status.
	 * @return string
	 */
	private function validate_status( string $status, array $statuses, string $default_status ): string {
		return isset( $statuses[ $status ] ) ? $status : $default_status;
	}

	/**
	 * Get allowed payment statuses.
	 *
	 * @return array
	 */
	private function get_payment_statuses(): array {
		return array(
			'pending'    => __( 'Pending', 'pricing-manager' ),
			'paid'       => __( 'Paid', 'pricing-manager' ),
			'failed'     => __( 'Failed', 'pricing-manager' ),
			'refunded'   => __( 'Refunded', 'pricing-manager' ),
		);
	}

	/**
	 * Get allowed fulfillment statuses.
	 *
	 * @return array
	 */
	private function get_fulfillment_statuses(): array {
		return array(
			'unfulfilled' => __( 'Unfulfilled', 'pricing-manager' ),
			'queued'      => __( 'Queued', 'pricing-manager' ),
			'fulfilled'   => __( 'Fulfilled', 'pricing-manager' ),
			'failed'      => __( 'Failed', 'pricing-manager' ),
			'cancelled'   => __( 'Cancelled', 'pricing-manager' ),
		);
	}

	/**
	 * Get supported order screen IDs.
	 *
	 * @return array
	 */
	private function get_order_screen_ids(): array {
		return array(
			'shop_order',
			'woocommerce_page_wc-orders',
		);
	}
}
