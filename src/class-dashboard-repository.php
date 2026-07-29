<?php
/**
 * Dashboard data access.
 *
 * @package Yallacoins\PricingManager
 */

namespace Yallacoins\PricingManager;

defined( 'ABSPATH' ) || exit;

/**
 * Runs optimized dashboard queries against WooCommerce order tables.
 */
class Dashboard_Repository {

	private const DEFAULT_PER_PAGE = 20;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Get dashboard summary values.
	 *
	 * @param array $filters Dashboard filters.
	 * @return array
	 */
	public function get_summary( array $filters ): array {
		global $wpdb;

		$sql_parts = $this->build_pricing_snapshot_query_parts( $filters );
		$query     = "
			SELECT
				COUNT(DISTINCT oi.order_id) AS order_count,
				COUNT(DISTINCT oi.order_item_id) AS line_count,
				COALESCE(SUM({$this->get_calculated_price_sql()}), 0) AS calculated_total_egp,
				COALESCE(AVG(CAST(COALESCE(margin_meta.meta_value, 0) AS DECIMAL(20,6))), 0) AS average_margin
			{$sql_parts['from']}
			{$sql_parts['where']}
		";

		$row = $wpdb->get_row( $wpdb->prepare( $query, $sql_parts['params'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'order_count'          => isset( $row['order_count'] ) ? (int) $row['order_count'] : 0,
			'line_count'           => isset( $row['line_count'] ) ? (int) $row['line_count'] : 0,
			'calculated_total_egp' => isset( $row['calculated_total_egp'] ) ? (float) $row['calculated_total_egp'] : 0,
			'average_margin'       => isset( $row['average_margin'] ) ? (float) $row['average_margin'] : 0,
		);
	}

	/**
	 * Get paginated dashboard rows.
	 *
	 * @param array $filters Dashboard filters.
	 * @return array
	 */
	public function get_rows( array $filters ): array {
		global $wpdb;

		$page      = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$per_page  = isset( $filters['per_page'] ) ? (int) $filters['per_page'] : self::DEFAULT_PER_PAGE;
		$per_page  = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$offset    = ( $page - 1 ) * $per_page;
		$sql_parts = $this->build_pricing_snapshot_query_parts( $filters );
		$query     = "
			SELECT
				oi.order_item_id,
				oi.order_id,
				oi.order_item_name,
				order_data.order_status,
				order_data.order_date_gmt,
				base_meta.meta_value AS base_price_usd,
				margin_meta.meta_value AS profit_margin,
				rate_meta.meta_value AS exchange_rate,
				{$this->get_calculated_price_sql()} AS calculated_price_egp
			{$sql_parts['from']}
			{$sql_parts['where']}
			ORDER BY order_data.order_date_gmt DESC, oi.order_id DESC, oi.order_item_id DESC
			LIMIT %d OFFSET %d
		";

		$params    = array_merge( $sql_parts['params'], array( $per_page, $offset ) );
		$rows      = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "
			SELECT COUNT(DISTINCT oi.order_item_id)
			{$sql_parts['from']}
			{$sql_parts['where']}
		";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $sql_parts['params'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'rows'        => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Build reusable SQL parts for pricing snapshot queries.
	 *
	 * @param array $filters Dashboard filters.
	 * @return array
	 */
	private function build_pricing_snapshot_query_parts( array $filters ): array {
		global $wpdb;

		$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$order_items_table    = $wpdb->prefix . 'woocommerce_order_items';
		$order_source         = $this->get_order_source_sql();
		$where                = array(
			'oi.order_item_type = %s',
			'base_meta.meta_key = %s',
		);
		$params               = array(
			Order_Pricing_Metadata::META_PROFIT_MARGIN,
			Order_Pricing_Metadata::META_EXCHANGE_RATE,
			'line_item',
			Order_Pricing_Metadata::META_BASE_PRICE_USD,
		);

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'order_data.order_status = %s';
			$params[] = $filters['status'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'order_data.order_date_gmt >= %s';
			$params[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'order_data.order_date_gmt <= %s';
			$params[] = $filters['date_to'] . ' 23:59:59';
		}

		return array(
			'from'   => "
				FROM {$order_items_table} oi
				INNER JOIN {$order_itemmeta_table} base_meta
					ON base_meta.order_item_id = oi.order_item_id
				LEFT JOIN {$order_itemmeta_table} margin_meta
					ON margin_meta.order_item_id = oi.order_item_id
					AND margin_meta.meta_key = %s
				LEFT JOIN {$order_itemmeta_table} rate_meta
					ON rate_meta.order_item_id = oi.order_item_id
					AND rate_meta.meta_key = %s
				INNER JOIN {$order_source['table']} order_data
					ON order_data.order_id = oi.order_id
			",
			'where'  => 'WHERE ' . implode( ' AND ', $where ),
			'params' => $params,
		);
	}

	/**
	 * Get the order source SQL for HPOS or post storage.
	 *
	 * @return array
	 */
	private function get_order_source_sql(): array {
		global $wpdb;

		if ( $this->table_exists( $wpdb->prefix . 'wc_orders' ) ) {
			return array(
				'table' => "(SELECT id AS order_id, status AS order_status, date_created_gmt AS order_date_gmt FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order')",
			);
		}

		return array(
			'table' => "(SELECT ID AS order_id, post_status AS order_status, post_date_gmt AS order_date_gmt FROM {$wpdb->posts} WHERE post_type = 'shop_order')",
		);
	}

	/**
	 * Check whether a database table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		$like = $wpdb->esc_like( $table_name );

		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	/**
	 * Get SQL expression for calculated EGP price.
	 *
	 * @return string
	 */
	private function get_calculated_price_sql(): string {
		return 'CAST(base_meta.meta_value AS DECIMAL(20,6)) * (1 + (CAST(COALESCE(margin_meta.meta_value, 0) AS DECIMAL(20,6)) / 100)) * CAST(COALESCE(rate_meta.meta_value, 0) AS DECIMAL(20,6))';
	}
}
