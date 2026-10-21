<?php
/**
 * Installments helper.
 *
 * Every amount a customer owes is an installment row: the parts of a payment plan, a late fee,
 * a manual charge. Repayments settle the oldest rows first, refunds and cancellations shrink the
 * rows of their own order. The customer's dues always equal the sum of what is left on the rows.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Installments;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Installments_Helper' ) ) {
	/**
	 * Installments helper class
	 */
	class DDWCPP_Installments_Helper {
		/**
		 * Table name.
		 *
		 * @var string
		 */
		protected $table;

		/**
		 * Construct
		 */
		public function __construct() {
			global $wpdb;
			$this->table = $wpdb->prefix . 'ddwcpp_installments';
		}

		/**
		 * Amount still owed on a row, late fee included.
		 *
		 * @param array $row Installment.
		 * @return float
		 */
		public static function ddwcpp_get_remaining( $row ) {
			return max( 0.0, round( (float) $row['amount'] + (float) $row['late_fee'] - (float) $row['paid_amount'], wc_get_price_decimals() ) );
		}

		/**
		 * Create installment rows.
		 *
		 * @param int   $user_id  Customer.
		 * @param int   $order_id Order, 0 for a charge without an order.
		 * @param array $plan     Plan, or [ 'id' => '', 'title' => '...' ] for a one-off charge.
		 * @param array $schedule Rows from DDWCPP_Plans_Helper::ddwcpp_build_schedule().
		 * @return void
		 */
		public function ddwcpp_create( $user_id, $order_id, $plan, $schedule ) {
			global $wpdb;

			$count = count( $schedule );

			foreach ( array_values( $schedule ) as $index => $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
				$wpdb->insert(
					$this->table,
					[
						'user_id'     => absint( $user_id ),
						'order_id'    => absint( $order_id ),
						'plan_id'     => isset( $plan['id'] ) ? sanitize_title( $plan['id'] ) : '',
						'plan_title'  => isset( $plan['title'] ) ? mb_substr( sanitize_text_field( $plan['title'] ), 0, 200 ) : '',
						'sequence'    => $index + 1,
						'total_count' => $count,
						'amount'      => wc_format_decimal( $row['amount'], wc_get_price_decimals() ),
						'due_date'    => ! empty( $row['due_date'] ) ? $row['due_date'] : null,
						'status'      => 'pending',
						'created'     => current_time( 'mysql' ),
					]
				);
			}
		}

		/**
		 * One installment.
		 *
		 * @param int $id Installment ID.
		 * @return array|null
		 */
		public function ddwcpp_get( $id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix.
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		}

		/**
		 * WHERE clause for a filtered list.
		 *
		 * @param array $args {
		 *     Arguments.
		 *
		 *     @type int    $user_id  Customer.
		 *     @type int    $order_id Order.
		 *     @type string $view     pending, upcoming, due_soon, overdue, paid or cancelled.
		 *     @type int    $days     Window for due_soon.
		 *     @type string $search   Order ID or installment ID.
		 * }
		 * @return string
		 */
		protected function ddwcpp_get_where( $args ) {
			global $wpdb;

			$where = [ '1=1' ];
			$now   = current_time( 'mysql' );

			if ( ! empty( $args['user_id'] ) ) {
				$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
			}

			if ( isset( $args['order_id'] ) && '' !== $args['order_id'] && null !== $args['order_id'] ) {
				$where[] = $wpdb->prepare( 'order_id = %d', $args['order_id'] );
			}

			switch ( isset( $args['view'] ) ? $args['view'] : '' ) {
				case 'pending':
					$where[] = "status = 'pending'";
					break;
				case 'upcoming':
					$where[] = $wpdb->prepare( "status = 'pending' AND ( due_date IS NULL OR due_date >= %s )", $now );
					break;
				case 'due_soon':
					$where[] = $wpdb->prepare( "status = 'pending' AND due_date >= %s AND due_date <= %s", $now, wp_date( 'Y-m-d 23:59:59', strtotime( '+' . ( isset( $args['days'] ) ? absint( $args['days'] ) : 7 ) . ' days' ) ) );
					break;
				case 'overdue':
					$where[] = $wpdb->prepare( "status = 'pending' AND due_date < %s", $now );
					break;
				case 'paid':
				case 'cancelled':
					$where[] = $wpdb->prepare( 'status = %s', $args['view'] );
					break;
			}

			if ( ! empty( $args['search'] ) ) {
				$where[] = $wpdb->prepare( '( id = %d OR order_id = %d )', absint( $args['search'] ), absint( $args['search'] ) );
			}

			return 'WHERE ' . implode( ' AND ', $where );
		}

		/**
		 * Filtered installments.
		 *
		 * @param array $args Filters, see ddwcpp_get_where(), plus per_page, offset, orderby, order.
		 * @return array
		 */
		public function ddwcpp_get_installments( $args = [] ) {
			global $wpdb;

			$args = wp_parse_args(
				$args,
				[
					'per_page' => 0,
					'offset'   => 0,
					'orderby'  => 'due_date',
					'order'    => 'ASC',
				]
			);

			$orderby = in_array( $args['orderby'], [ 'id', 'due_date', 'amount', 'order_id', 'paid_date' ], true ) ? $args['orderby'] : 'due_date';
			$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
			$limit   = $args['per_page'] ? $wpdb->prepare( 'LIMIT %d OFFSET %d', $args['per_page'], $args['offset'] ) : '';

			// Rows without a due date sort last when ascending.
			$sort = 'due_date' === $orderby ? "due_date IS NULL {$order}, due_date {$order}, sequence ASC, id ASC" : "{$orderby} {$order}, id {$order}";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, WHERE and LIMIT prepared, ORDER BY whitelisted.
			return $wpdb->get_results( "SELECT * FROM {$this->table} {$this->ddwcpp_get_where( $args )} ORDER BY {$sort} {$limit}", ARRAY_A );
		}

		/**
		 * Count filtered installments.
		 *
		 * @param array $args Filters.
		 * @return int
		 */
		public function ddwcpp_count( $args = [] ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, WHERE prepared.
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} {$this->ddwcpp_get_where( $args )}" );
		}

		/**
		 * Sum still owed on filtered installments.
		 *
		 * @param array $args Filters.
		 * @return float
		 */
		public function ddwcpp_sum_remaining( $args = [] ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, WHERE prepared.
			return (float) $wpdb->get_var( "SELECT SUM( amount + late_fee - paid_amount ) FROM {$this->table} {$this->ddwcpp_get_where( $args )}" );
		}

		/**
		 * Pending rows of a customer, in the order repayments settle them.
		 *
		 * @param int $user_id Customer.
		 * @return array
		 */
		public function ddwcpp_get_pending( $user_id ) {
			return $this->ddwcpp_get_installments(
				[
					'user_id' => $user_id,
					'view'    => 'pending',
				]
			);
		}

		/**
		 * Earliest pending due date of a customer.
		 *
		 * @param int $user_id Customer.
		 * @return string Empty when nothing pending has a due date.
		 */
		public function ddwcpp_get_next_due_date( $user_id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix.
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT MIN( due_date ) FROM {$this->table} WHERE user_id = %d AND status = 'pending' AND due_date IS NOT NULL", $user_id ) );
		}

		/**
		 * Update one row.
		 *
		 * @param int   $id   Installment ID.
		 * @param array $data Columns.
		 * @return void
		 */
		public function ddwcpp_update( $id, $data ) {
			global $wpdb;

			$wpdb->update( $this->table, $data, [ 'id' => absint( $id ) ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		}

		/**
		 * Settle a repayment against pending rows, oldest due date first.
		 *
		 * @param int   $user_id      Customer.
		 * @param float $amount       Amount paid.
		 * @param int   $preferred_id Row to settle before the others, e.g. one the store marked paid.
		 * @param int   $payment_id   Order the money came in with.
		 * @return float What was left over after every pending row was settled.
		 */
		public function ddwcpp_allocate( $user_id, $amount, $preferred_id = 0, $payment_id = 0 ) {
			$rows = $this->ddwcpp_get_pending( $user_id );

			if ( $preferred_id ) {
				usort(
					$rows,
					function ( $a, $b ) use ( $preferred_id ) {
						return (int) ( (int) $b['id'] === (int) $preferred_id ) - (int) ( (int) $a['id'] === (int) $preferred_id );
					}
				);
			}

			$amount = round( (float) $amount, wc_get_price_decimals() );

			foreach ( $rows as $row ) {
				if ( $amount <= 0 ) {
					break;
				}

				$remaining = self::ddwcpp_get_remaining( $row );
				$applied   = min( $remaining, $amount );
				$amount    = round( $amount - $applied, wc_get_price_decimals() );
				$data      = [ 'paid_amount' => wc_format_decimal( (float) $row['paid_amount'] + $applied, wc_get_price_decimals() ) ];

				if ( $applied >= $remaining ) {
					$data['status']    = 'paid';
					$data['paid_date'] = current_time( 'mysql' );

					if ( $payment_id ) {
						$data['payment_order_id'] = absint( $payment_id );
					}
				}

				$this->ddwcpp_update( $row['id'], $data );

				if ( isset( $data['status'] ) ) {
					do_action( 'ddwcpp_installment_paid', array_merge( $row, $data ) );
				}
			}

			return max( 0.0, $amount );
		}

		/**
		 * Shrink an order's pending rows after a refund or cancellation, last row first.
		 *
		 * @param int   $order_id Order.
		 * @param float $amount   Amount the order no longer owes.
		 * @return float What could not be taken off the order's rows.
		 */
		public function ddwcpp_reduce_order( $order_id, $amount ) {
			$rows   = array_reverse( $this->ddwcpp_get_installments( [ 'order_id' => $order_id ] ) );
			$amount = round( (float) $amount, wc_get_price_decimals() );

			foreach ( $rows as $row ) {
				if ( $amount <= 0 ) {
					break;
				}

				if ( 'pending' !== $row['status'] ) {
					continue;
				}

				// Late fees stay owed: they were earned by the missed date, not by the order.
				$open    = max( 0.0, (float) $row['amount'] - (float) $row['paid_amount'] );
				$applied = min( $open, $amount );
				$amount  = round( $amount - $applied, wc_get_price_decimals() );
				$data    = [ 'amount' => wc_format_decimal( (float) $row['amount'] - $applied, wc_get_price_decimals() ) ];

				if ( self::ddwcpp_get_remaining( array_merge( $row, $data ) ) <= 0 ) {
					$data['status'] = (float) $row['paid_amount'] > 0 ? 'paid' : 'cancelled';
				}

				$this->ddwcpp_update( $row['id'], $data );
			}

			return max( 0.0, $amount );
		}

		/**
		 * Paid so far towards an order's own amount; payments count towards the amount before any late fee.
		 *
		 * @param int $order_id Order.
		 * @return float
		 */
		public function ddwcpp_get_order_paid( $order_id ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix.
			return (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM( LEAST( paid_amount, amount ) ) FROM {$this->table} WHERE order_id = %d", $order_id ) );
		}

		/**
		 * Orders with a plan of a customer, newest first, with progress.
		 *
		 * @param int $user_id Customer.
		 * @return array order_id => [ plan_title, total, remaining, paid_count, count, next_due, overdue ].
		 */
		public function ddwcpp_get_plans_summary( $user_id ) {
			$plans = [];
			$now   = current_time( 'mysql' );

			foreach ( $this->ddwcpp_get_installments( [ 'user_id' => $user_id ] ) as $row ) {
				$key = (int) $row['order_id'] ? (int) $row['order_id'] : 'charge-' . $row['id'];

				if ( ! isset( $plans[ $key ] ) ) {
					$plans[ $key ] = [
						'order_id'    => (int) $row['order_id'],
						'plan_title'  => $row['plan_title'],
						'created'     => $row['created'],
						'total'       => 0.0,
						'remaining'   => 0.0,
						'paid_count'  => 0,
						'count'       => 0,
						'cancelled'   => 0,
						'next_due'    => '',
						'next_amount' => 0.0,
						'overdue'     => false,
						'status'      => 'cancelled',
					];
				}

				$plan = &$plans[ $key ];

				if ( 'cancelled' !== $row['status'] ) {
					++$plan['count'];
					$plan['total'] += (float) $row['amount'] + (float) $row['late_fee'];
				} else {
					++$plan['cancelled'];
				}

				if ( 'paid' === $row['status'] ) {
					++$plan['paid_count'];
					$plan['status'] = 'pending' === $plan['status'] ? 'pending' : 'paid';
				}

				if ( 'pending' === $row['status'] ) {
					$plan['status']     = 'pending';
					$plan['remaining'] += self::ddwcpp_get_remaining( $row );

					if ( ! $plan['next_due'] ) {
						$plan['next_due']    = (string) $row['due_date'];
						$plan['next_amount'] = self::ddwcpp_get_remaining( $row );
					}

					if ( $row['due_date'] && $row['due_date'] < $now ) {
						$plan['overdue'] = true;
					}
				}

				unset( $plan );
			}

			// A plan whose open payments were cancelled (order cancelled or refunded) ended early.
			foreach ( $plans as &$plan ) {
				if ( 'paid' === $plan['status'] && $plan['cancelled'] ) {
					$plan['status'] = 'cancelled';
				}
			}
			unset( $plan );

			uasort(
				$plans,
				function ( $a, $b ) {
					return strcmp( $b['created'], $a['created'] );
				}
			);

			return $plans;
		}
	}
}
