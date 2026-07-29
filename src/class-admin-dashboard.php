<?php
/**
 * Admin dashboard presentation.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the pricing operations dashboard.
 */
class Admin_Dashboard {

	private const PAGE_SLUG           = 'pricing-manager-dashboard';
	private const FILTER_NONCE_ACTION = 'pricing_manager_dashboard_filters';
	private const FILTER_NONCE_NAME   = 'pricing_manager_dashboard_nonce';
	private const PER_PAGE_OPTION     = 'pricing_manager_dashboard_per_page';

	/**
	 * Dashboard repository.
	 *
	 * @var Dashboard_Repository
	 */
	private Dashboard_Repository $dashboard_repository;

	/**
	 * Constructor.
	 *
	 * @param Dashboard_Repository $dashboard_repository Dashboard repository.
	 */
	public function __construct( Dashboard_Repository $dashboard_repository ) {
		$this->dashboard_repository = $dashboard_repository;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
	}

	/**
	 * Add dashboard menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$required_capability = $this->get_required_capability();

		$hook_suffix = add_submenu_page(
			'woocommerce',
			__( 'Pricing Dashboard', 'pricing-manager' ),
			__( 'Pricing Dashboard', 'pricing-manager' ),
			$required_capability,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_action( 'load-' . $hook_suffix, array( $this, 'add_screen_options' ) );
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view pricing analytics.', 'pricing-manager' ) );
		}

		$filters = $this->get_filters();
		$summary = $this->dashboard_repository->get_summary( $filters );
		$results = $this->dashboard_repository->get_rows( $filters );
		?>
		<div class="wrap pricing-manager-dashboard">
			<h1><?php echo esc_html__( 'Pricing Dashboard', 'pricing-manager' ); ?></h1>
			<?php $this->render_summary_widgets( $summary ); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php wp_nonce_field( self::FILTER_NONCE_ACTION, self::FILTER_NONCE_NAME ); ?>
				<?php $this->render_table_controls( 'top', $filters, $results ); ?>
				<?php $this->render_table( $results ); ?>
				<?php $this->render_table_controls( 'bottom', $filters, $results ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render dashboard summary widgets.
	 *
	 * @param array $summary Summary values.
	 * @return void
	 */
	private function render_summary_widgets( array $summary ): void {
		$widgets = array(
			array(
				'label' => __( 'Managed Orders', 'pricing-manager' ),
				'value' => number_format_i18n( (int) $summary['order_count'] ),
			),
			array(
				'label' => __( 'Managed Line Items', 'pricing-manager' ),
				'value' => number_format_i18n( (int) $summary['line_count'] ),
			),
			array(
				'label' => __( 'Calculated Total', 'pricing-manager' ),
				'value' => wc_price( (float) $summary['calculated_total_egp'], array( 'currency' => 'EGP' ) ),
			),
			array(
				'label' => __( 'Average Margin', 'pricing-manager' ),
				'value' => number_format_i18n( (float) $summary['average_margin'], 2 ) . '%',
			),
		);
		?>
		<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0;">
			<?php foreach ( $widgets as $widget ) : ?>
				<div style="background:#fff;border:1px solid #c3c4c7;padding:16px;">
					<p style="margin:0 0 8px;color:#646970;"><?php echo esc_html( $widget['label'] ); ?></p>
					<strong style="font-size:22px;"><?php echo wp_kses_post( $widget['value'] ); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render table controls.
	 *
	 * @param string $which   Control position.
	 * @param array  $filters Active filters.
	 * @param array  $results Paginated results.
	 * @return void
	 */
	private function render_table_controls( string $which, array $filters, array $results ): void {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php if ( 'top' === $which ) : ?>
				<div class="alignleft actions">
					<label class="screen-reader-text" for="pricing-manager-date-from"><?php echo esc_html__( 'From date', 'pricing-manager' ); ?></label>
					<input id="pricing-manager-date-from" type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
					<label class="screen-reader-text" for="pricing-manager-date-to"><?php echo esc_html__( 'To date', 'pricing-manager' ); ?></label>
					<input id="pricing-manager-date-to" type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
					<label class="screen-reader-text" for="pricing-manager-order-status"><?php echo esc_html__( 'Filter by order status', 'pricing-manager' ); ?></label>
					<select id="pricing-manager-order-status" name="status">
						<option value=""><?php echo esc_html__( 'All statuses', 'pricing-manager' ); ?></option>
						<?php foreach ( wc_get_order_statuses() as $status_key => $status_label ) : ?>
							<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['status'], $status_key ); ?>>
								<?php echo esc_html( $status_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'pricing-manager' ), 'secondary', 'filter_action', false ); ?>
				</div>
			<?php endif; ?>
			<?php $this->render_pagination( $results ); ?>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * Render dashboard table.
	 *
	 * @param array $results Paginated results.
	 * @return void
	 */
	private function render_table( array $results ): void {
		?>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Order', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Date', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Item', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Base USD', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Margin', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Rate', 'pricing-manager' ); ?></th>
					<th><?php echo esc_html__( 'Calculated EGP', 'pricing-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $results['rows'] ) ) : ?>
					<tr>
						<td colspan="8"><?php echo esc_html__( 'No managed pricing order items found.', 'pricing-manager' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $results['rows'] as $row ) : ?>
					<tr>
						<td><?php echo wp_kses_post( $this->get_order_link( (int) $row['order_id'] ) ); ?></td>
						<td><?php echo esc_html( $this->format_date( $row['order_date_gmt'] ) ); ?></td>
						<td><?php echo esc_html( wc_get_order_status_name( $row['order_status'] ) ); ?></td>
						<td><?php echo esc_html( $row['order_item_name'] ); ?></td>
						<td><?php echo esc_html( wc_format_decimal( (float) $row['base_price_usd'] ) ); ?></td>
						<td><?php echo esc_html( wc_format_decimal( (float) $row['profit_margin'] ) ); ?>%</td>
						<td><?php echo esc_html( wc_format_decimal( (float) $row['exchange_rate'], 4 ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( (float) $row['calculated_price_egp'], array( 'currency' => 'EGP' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render pagination links.
	 *
	 * @param array $results Paginated results.
	 * @return void
	 */
	private function render_pagination( array $results ): void {
		$page        = max( 1, (int) $results['page'] );
		$total_pages = max( 1, (int) $results['total_pages'] );
		$query_args  = $this->get_filter_query_args();
		?>
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: item count. */
					esc_html__( '%s items', 'pricing-manager' ),
					esc_html( number_format_i18n( (int) $results['total'] ) )
				);
				?>
			</span>
			<span class="pagination-links">
				<?php echo wp_kses_post( $this->get_pagination_link( 1, '&laquo;', __( 'First page', 'pricing-manager' ), $page <= 1, $query_args ) ); ?>
				<?php echo wp_kses_post( $this->get_pagination_link( max( 1, $page - 1 ), '&lsaquo;', __( 'Previous page', 'pricing-manager' ), $page <= 1, $query_args ) ); ?>
				<span class="paging-input">
					<label for="current-page-selector" class="screen-reader-text"><?php echo esc_html__( 'Current Page', 'pricing-manager' ); ?></label>
					<input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( (string) $page ); ?>" size="1" aria-describedby="table-paging" />
					<span class="tablenav-paging-text">
						<?php
						printf(
							/* translators: %s: total pages. */
							esc_html__( 'of %s', 'pricing-manager' ),
							'<span class="total-pages">' . esc_html( number_format_i18n( $total_pages ) ) . '</span>'
						);
						?>
					</span>
				</span>
				<?php echo wp_kses_post( $this->get_pagination_link( min( $total_pages, $page + 1 ), '&rsaquo;', __( 'Next page', 'pricing-manager' ), $page >= $total_pages, $query_args ) ); ?>
				<?php echo wp_kses_post( $this->get_pagination_link( $total_pages, '&raquo;', __( 'Last page', 'pricing-manager' ), $page >= $total_pages, $query_args ) ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Read and sanitize filters.
	 *
	 * @return array
	 */
	private function get_filters(): array {
		$order_statuses = wc_get_order_statuses();
		$has_nonce      = isset( $_GET[ self::FILTER_NONCE_NAME ] );
		$nonce_value    = $has_nonce ? sanitize_text_field( wp_unslash( $_GET[ self::FILTER_NONCE_NAME ] ) ) : '';
		$nonce_valid    = $has_nonce && wp_verify_nonce( $nonce_value, self::FILTER_NONCE_ACTION );
		$status         = $nonce_valid && isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$date_from      = $nonce_valid && isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to        = $nonce_valid && isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$page           = $nonce_valid && isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page       = $this->get_items_per_page();

		return array(
			'date_from' => $this->sanitize_date_filter( $date_from ),
			'date_to'   => $this->sanitize_date_filter( $date_to ),
			'status'    => isset( $order_statuses[ $status ] ) ? $status : '',
			'page'      => max( 1, $page ),
			'per_page'  => max( 1, min( 100, $per_page ) ),
		);
	}

	/**
	 * Build current filter query args.
	 *
	 * @return array
	 */
	private function get_filter_query_args(): array {
		$filters = $this->get_filters();

		return array(
			'page'                  => self::PAGE_SLUG,
			'date_from'             => $filters['date_from'],
			'date_to'               => $filters['date_to'],
			'status'                => $filters['status'],
			self::FILTER_NONCE_NAME => wp_create_nonce( self::FILTER_NONCE_ACTION ),
		);
	}

	/**
	 * Sanitize a date filter.
	 *
	 * @param mixed $value Date value.
	 * @return string
	 */
	private function sanitize_date_filter( $value ): string {
		$date = sanitize_text_field( (string) $value );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}

	/**
	 * Format an order date.
	 *
	 * @param string $date_gmt GMT date.
	 * @return string
	 */
	private function format_date( string $date_gmt ): string {
		$timestamp = strtotime( $date_gmt );

		return $timestamp ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '';
	}

	/**
	 * Get an admin order link.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function get_order_link( int $order_id ): string {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return '#' . $order_id;
		}

		return sprintf(
			'<a href="%1$s">#%2$s</a>',
			esc_url( $order->get_edit_order_url() ),
			esc_html( (string) $order->get_order_number() )
		);
	}

	/**
	 * Get a pagination link or disabled button.
	 *
	 * @param int    $page       Target page.
	 * @param string $text       Link text.
	 * @param string $label      Screen reader label.
	 * @param bool   $disabled   Whether the link is disabled.
	 * @param array  $query_args Query args.
	 * @return string
	 */
	private function get_pagination_link( int $page, string $text, string $label, bool $disabled, array $query_args ): string {
		if ( $disabled ) {
			return sprintf(
				'<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>',
				esc_html( html_entity_decode( $text ) )
			);
		}

		return sprintf(
			'<a class="button" href="%1$s"><span class="screen-reader-text">%2$s</span><span aria-hidden="true">%3$s</span></a>',
			esc_url( add_query_arg( array_merge( $query_args, array( 'paged' => $page ) ), admin_url( 'admin.php' ) ) ),
			esc_html( $label ),
			esc_html( html_entity_decode( $text ) )
		);
	}

	/**
	 * Get the capability required to view the dashboard.
	 *
	 * @return string
	 */
	private function get_required_capability(): string {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Add Screen Options controls.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Pricing records per page', 'pricing-manager' ),
				'default' => 20,
				'option'  => self::PER_PAGE_OPTION,
			)
		);
	}

	/**
	 * Persist the dashboard per-page screen option.
	 *
	 * @param mixed  $status Screen option status.
	 * @param string $option Screen option name.
	 * @param int    $value  Submitted value.
	 * @return mixed
	 */
	public function set_screen_option( $status, string $option, int $value ) {
		if ( self::PER_PAGE_OPTION !== $option ) {
			return $status;
		}

		return max( 1, min( 100, $value ) );
	}

	/**
	 * Get the current per-page setting.
	 *
	 * @return int
	 */
	private function get_items_per_page(): int {
		$per_page = (int) get_user_option( self::PER_PAGE_OPTION );

		if ( $per_page <= 0 ) {
			$per_page = 20;
		}

		return max( 1, min( 100, $per_page ) );
	}
}
