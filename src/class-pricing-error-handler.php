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
	private const MAX_RECENT_ERRORS    = 10;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
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
		$this->log_error( $code, $message, $context );
		$this->store_recent_error( $code, $message );
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

		$errors = get_option( self::OPTION_RECENT_ERRORS, array() );

		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}

		$latest_error = reset( $errors );

		if ( ! is_array( $latest_error ) || empty( $latest_error['message'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( $latest_error['message'] )
		);
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
	 * @return void
	 */
	private function store_recent_error( string $code, string $message ): void {
		$errors = get_option( self::OPTION_RECENT_ERRORS, array() );

		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		array_unshift(
			$errors,
			array(
				'code'       => sanitize_key( $code ),
				'message'    => sanitize_text_field( $message ),
				'created_at' => time(),
			)
		);

		update_option( self::OPTION_RECENT_ERRORS, array_slice( $errors, 0, self::MAX_RECENT_ERRORS ), false );
	}
}
