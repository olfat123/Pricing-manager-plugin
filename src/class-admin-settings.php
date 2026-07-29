<?php
/**
 * Admin settings presentation.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Adds plugin settings to WooCommerce.
 */
class Admin_Settings {

	private const FIELD_TYPE = 'pricing_manager_exchange_rate';

	/**
	 * Settings repository.
	 *
	 * @var Settings_Repository
	 */
	private Settings_Repository $settings_repository;

	/**
	 * Constructor.
	 *
	 * @param Settings_Repository $settings_repository Settings repository.
	 */
	public function __construct( Settings_Repository $settings_repository ) {
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_get_settings_general', array( $this, 'add_exchange_rate_setting' ) );
		add_action( 'woocommerce_admin_field_' . self::FIELD_TYPE, array( $this, 'render_exchange_rate_field' ) );
		add_filter( 'sanitize_option_' . Settings_Repository::OPTION_EXCHANGE_RATE, array( $this, 'sanitize_exchange_rate' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'clear_price_cache' ) );
	}

	/**
	 * Add exchange rate field to WooCommerce general settings.
	 *
	 * @param array $settings WooCommerce general settings.
	 * @return array
	 */
	public function add_exchange_rate_setting( array $settings ): array {
		$exchange_rate_setting = array(
			'title'    => __( 'USD to EGP exchange rate', 'pricing-manager' ),
			'desc'     => __( 'Leave empty to use the online USD to EGP exchange rate automatically.', 'pricing-manager' ),
			'id'       => Settings_Repository::OPTION_EXCHANGE_RATE,
			'type'     => self::FIELD_TYPE,
			'desc_tip' => true,
		);

		foreach ( $settings as $index => $setting ) {
			if ( isset( $setting['id'] ) && 'woocommerce_currency' === $setting['id'] ) {
				array_splice( $settings, $index + 1, 0, array( $exchange_rate_setting ) );

				return $settings;
			}
		}

		foreach ( $settings as $index => $setting ) {
			if ( isset( $setting['id'], $setting['type'] ) && 'pricing_options' === $setting['id'] && 'sectionend' === $setting['type'] ) {
				array_splice( $settings, $index, 0, array( $exchange_rate_setting ) );

				return $settings;
			}
		}

		return $settings;
	}

	/**
	 * Render the exchange rate as a WooCommerce settings table row.
	 *
	 * @param array $value Field configuration.
	 * @return void
	 */
	public function render_exchange_rate_field( array $value ): void {
		$field_id      = Settings_Repository::OPTION_EXCHANGE_RATE;
		$exchange_rate = $this->settings_repository->get_manual_exchange_rate();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $value['title'] ); ?>
					<?php echo wc_help_tip( esc_html( $value['desc'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( self::FIELD_TYPE ); ?>">
				<input
					name="<?php echo esc_attr( $field_id ); ?>"
					id="<?php echo esc_attr( $field_id ); ?>"
					type="number"
					style="max-width: 180px;"
					value="<?php echo esc_attr( $exchange_rate > 0 ? (string) $exchange_rate : '' ); ?>"
					class="wc_input_decimal regular-text"
					inputmode="decimal"
					min="0"
					step="0.0001"
				/>
				<p class="description"><?php echo esc_html( $value['desc'] ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sanitize the manual exchange rate.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_exchange_rate( $value ): string {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$exchange_rate = (float) wc_format_decimal( wp_unslash( $value ) );

		return $exchange_rate > 0 ? (string) $exchange_rate : '';
	}

	/**
	 * Clear cached product prices after WooCommerce general settings are saved.
	 *
	 * @return void
	 */
	public function clear_price_cache(): void {
		$this->settings_repository->clear_product_price_cache();
	}
}
