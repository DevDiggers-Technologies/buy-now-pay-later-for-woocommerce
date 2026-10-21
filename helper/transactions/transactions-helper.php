<?php
/**
 * Transactions helper.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Transactions;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Transactions_Helper' ) ) {
	/**
	 * Transactions helper class
	 */
	class DDWCPP_Transactions_Helper {
		/**
		 * Transactions table.
		 *
		 * @var string
		 */
		protected $table;

		/**
		 * Construct
		 */
		public function __construct() {
			global $wpdb;
			$this->table = $wpdb->prefix . 'ddwcpp_transactions';
		}

		/**
		 * Save a transaction.
		 *
		 * @param array $data Transaction data.
		 * @return int Insert ID.
		 */
		public function ddwcpp_save_transaction( $data ) {
			global $wpdb;

			$data = wp_parse_args(
				$data,
				[
					'order_id'  => null,
					'reference' => '',
					'user_id'   => get_current_user_id(),
					'amount'    => 0,
					'type'      => '',
					'date'      => current_time( 'mysql' ),
					'note'      => '',
				]
			);

			$data = [
				'order_id'  => $data['order_id'] ? absint( $data['order_id'] ) : null,
				'reference' => sanitize_key( $data['reference'] ),
				'user_id'   => absint( $data['user_id'] ),
				'amount'    => wc_format_decimal( $data['amount'] ),
				'type'      => 'credit' === $data['type'] ? 'credit' : 'debit',
				'date'      => $data['date'],
				'note'      => mb_substr( sanitize_text_field( $data['note'] ), 0, 250 ),
			];

			$wpdb->insert( $this->table, $data, [ '%d', '%s', '%d', '%s', '%s', '%s', '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.

			return (int) $wpdb->insert_id;
		}

		/**
		 * One transaction.
		 *
		 * @param int $id Transaction ID.
		 * @return array|null
		 */
		public function ddwcpp_get_transaction( $id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name from $wpdb->prefix.
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		}

		/**
		 * Build the WHERE clause for a filtered list.
		 *
		 * @param array $args {
		 *     Arguments.
		 *
		 *     @type string $type      credit or debit.
		 *     @type string $reference Reference key.
		 *     @type int    $user_id   Customer.
		 *     @type string $from      Y-m-d.
		 *     @type string $to        Y-m-d.
		 *     @type string $search    Transaction ID, order ID or note.
		 * }
		 * @return string Prepared SQL, starting with WHERE.
		 */
		protected function ddwcpp_get_where( $args ) {
			global $wpdb;

			$where = [ '1=1' ];

			if ( ! empty( $args['type'] ) ) {
				$where[] = $wpdb->prepare( 'type = %s', $args['type'] );
			}

			if ( ! empty( $args['reference'] ) ) {
				$where[] = $wpdb->prepare( 'reference = %s', $args['reference'] );
			}

			if ( ! empty( $args['user_id'] ) ) {
				$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
			}

			if ( ! empty( $args['from'] ) ) {
				$where[] = $wpdb->prepare( 'date >= %s', $args['from'] . ' 00:00:00' );
			}

			if ( ! empty( $args['to'] ) ) {
				$where[] = $wpdb->prepare( 'date <= %s', $args['to'] . ' 23:59:59' );
			}

			if ( ! empty( $args['search'] ) ) {
				$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where[] = $wpdb->prepare( '( id = %d OR order_id = %d OR note LIKE %s )', absint( $args['search'] ), absint( $args['search'] ), $like );
			}

			return 'WHERE ' . implode( ' AND ', $where );
		}

		/**
		 * Filtered, paginated transactions.
		 *
		 * @param array $args Filters, see ddwcpp_get_where(), plus per_page, offset, orderby, order.
		 * @return array
		 */
		public function ddwcpp_get_transactions( $args = [] ) {
			global $wpdb;

			$args = wp_parse_args(
				$args,
				[
					'per_page' => 20,
					'offset'   => 0,
					'orderby'  => 'id',
					'order'    => 'DESC',
				]
			);

			$orderby = in_array( $args['orderby'], [ 'id', 'date', 'amount', 'type', 'reference', 'user_id' ], true ) ? $args['orderby'] : 'id';
			$orderby = 'amount' === $orderby ? 'CAST(amount AS DECIMAL(20,6))' : $orderby;
			$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
			$limit   = $args['per_page'] > 0 ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], $args['offset'] ) : '';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; the clauses are prepared above and the order column is whitelisted.
			$rows = $wpdb->get_results( "SELECT * FROM {$this->table} {$this->ddwcpp_get_where( $args )} ORDER BY {$orderby} {$order} {$limit}", ARRAY_A );

			return apply_filters( 'ddwcpp_modify_transactions_data', $rows, $args );
		}

		/**
		 * Count of filtered transactions.
		 *
		 * @param array $args Filters, see ddwcpp_get_where().
		 * @return int
		 */
		public function ddwcpp_get_transactions_count( $args = [] ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; the clause is prepared above.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} {$this->ddwcpp_get_where( $args )}" );
		}
	}
}
