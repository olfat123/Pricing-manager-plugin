<?php
/**
 * Pricing error handling.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Logs pricing failures and exposes safe admin notices.
 */
class Pricing_Error_Handler {

	private const OPTION_RECENT_ERRORS = 'pricing_manager_recent_errors';
	private const TRANSIENT_PREFIX     = 'pricing_manager_error_';
	private const MAX_RECENT_ERRORS    = 10;
	private const ERROR_TTL            = 300;
	private const DISMISS_ACTION       = 'pricing_manager_dismiss_error_notice';
	private const DISMISS_NONCE_NAME   = 'pricing_manager_dismiss_error_nonce';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'handle_dismiss_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Report a pricing error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human-readable safe message.
	 * @param array  $context Diagnostic context.
	 * @return void
	 */
	public function report( string $code, string $message, array $context = array() ): void {
		if ( $this->is_recent_duplicate( $code, $context ) ) {
			return;
		}

		$this->log_error( $code, $message, $context );
		$this->store_recent_error( $code, $message, $context );
	}

	/**
	 * Dismiss stored pricing notices.
	 *
	 * @return void
	 */
	public function handle_dismiss_notice(): void {
		if ( ! isset( $_GET['pricing_manager_dismiss_error'], $_GET[ self::DISMISS_NONCE_NAME ] ) ) {
			return;
		}

		if ( ! current_user_can( Admin_Capabilities::manage_pricing() ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET[ self::DISMISS_NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::DISMISS_ACTION ) ) {
			return;
		}

		delete_option( self::OPTION_RECENT_ERRORS );

		wp_safe_redirect(
			remove_query_arg(
				array(
					'pricing_manager_dismiss_error',
					self::DISMISS_NONCE_NAME,
				)
			)
		);
		exit;
	}

	/**
	 * Render a concise admin notice for recent pricing issues.
	 *
	 * @return void
	 */
	public function render_admin_notice(): void {
		if ( ! current_user_can( Admin_Capabilities::manage_pricing() ) ) {
			return;
		}

		if ( ! $this->should_render_admin_notice() ) {
			return;
		}

		$errors = get_option( self::OPTION_RECENT_ERRORS, array() );

		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}

		$latest_error = reset( $errors );

		if ( ! is_array( $latest_error ) || empty( $latest_error['message'] ) ) {
			return;
		}

		echo wp_kses_post( $this->get_notice_html( $latest_error ) );
	}

	/**
	 * Log the error through WooCommerce when available.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	private function log_error( string $code, string $message, array $context ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning(
				$message,
				array_merge(
					$context,
					array(
						'source' => 'pricing-manager',
						'code'   => $code,
					)
				)
			);
		}
	}

	/**
	 * Store recent errors for admin visibility.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	private function store_recent_error( string $code, string $message, array $context ): void {
		$errors = get_option( self::OPTION_RECENT_ERRORS, array() );

		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		array_unshift(
			$errors,
			array(
				'code'       => sanitize_key( $code ),
				'message'    => sanitize_text_field( $message ),
				'context'    => $this->sanitize_context( $context ),
				'created_at' => time(),
			)
		);

		update_option( self::OPTION_RECENT_ERRORS, array_slice( $errors, 0, self::MAX_RECENT_ERRORS ), false );
	}

	/**
	 * Build admin notice markup.
	 *
	 * @param array $error Error data.
	 * @return string
	 */
	private function get_notice_html( array $error ): string {
		$message      = isset( $error['message'] ) ? (string) $error['message'] : '';
		$product_link = $this->get_product_link( $error );
		$dismiss_url  = wp_nonce_url(
			add_query_arg( 'pricing_manager_dismiss_error', '1' ),
			self::DISMISS_ACTION,
			self::DISMISS_NONCE_NAME
		);

		$actions = '';

		if ( $product_link ) {
			$actions .= ' <a href="' . esc_url( $product_link ) . '">' . esc_html__( 'Edit product', 'pricing-manager' ) . '</a>';
		}

		$actions .= ' <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'pricing-manager' ) . '</a>';

		return sprintf(
			'<div class="notice notice-warning is-dismissible"><p>%1$s%2$s</p></div>',
			esc_html( $message ),
			$actions
		);
	}

	/**
	 * Get an edit link for the product related to an error.
	 *
	 * @param array $error Error data.
	 * @return string
	 */
	private function get_product_link( array $error ): string {
		$variation_id = isset( $error['context']['variation_id'] ) ? absint( $error['context']['variation_id'] ) : 0;

		if ( ! $variation_id ) {
			return '';
		}

		$product_id = wp_get_post_parent_id( $variation_id );

		if ( ! $product_id ) {
			$product_id = $variation_id;
		}

		$edit_link = get_edit_post_link( $product_id, 'raw' );

		return $edit_link ? $edit_link : '';
	}

	/**
	 * Sanitize diagnostic context for storage.
	 *
	 * @param array $context Error context.
	 * @return array
	 */
	private function sanitize_context( array $context ): array {
		$sanitized = array();

		if ( isset( $context['variation_id'] ) ) {
			$sanitized['variation_id'] = absint( $context['variation_id'] );
		}

		return $sanitized;
	}

	/**
	 * Check whether pricing notices should render on the current screen.
	 *
	 * @return bool
	 */
	private function should_render_admin_notice(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		$allowed_screen_ids = array(
			'product',
			'shop_order',
			'woocommerce_page_wc-orders',
			'woocommerce_page_pricing-manager-dashboard',
			'woocommerce_page_wc-settings',
		);

		return in_array( $screen->id, $allowed_screen_ids, true );
	}

	/**
	 * Check and record whether an error was recently reported.
	 *
	 * @param string $code    Error code.
	 * @param array  $context Error context.
	 * @return bool
	 */
	private function is_recent_duplicate( string $code, array $context ): bool {
		$fingerprint = self::TRANSIENT_PREFIX . md5( $code . wp_json_encode( $context ) );

		if ( get_transient( $fingerprint ) ) {
			return true;
		}

		set_transient( $fingerprint, 1, self::ERROR_TTL );

		return false;
	}
}
