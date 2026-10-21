<?php
/**
 * Dashboard helper.
 *
 * Period figures come from the transactions log, so they always match the customer's history.
 * Point-in-time figures (outstanding dues, available credit) come from customer meta, which is
 * what checkout reads.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Dashboard;

use DevDiggers\Framework\Includes\DDFW_Dashboard_Data;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Dashboard_Helper' ) ) {
	/**
	 * Dashboard helper class
	 */
	class DDWCPP_Dashboard_Helper {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Transactions table.
		 *
		 * @var string
		 */
		protected $table;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			global $wpdb;

			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->table                = $wpdb->prefix . 'ddwcpp_transactions';
		}

		/**
		 * Everything the dashboard template renders.
		 *
		 * @return array
		 */
		public function ddwcpp_get_dashboard_data() {
			$date_range = DDFW_Dashboard_Data::get_date_range();
			$previous   = $this->ddwcpp_get_previous_range( $date_range );
			$current    = $this->ddwcpp_get_period_totals( $date_range['from'], $date_range['to'] );
			$before     = $this->ddwcpp_get_period_totals( $previous['from'], $previous['to'] );
			$accounts   = $this->ddwcpp_get_accounts();
			$schedule   = new \DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper();

			$repayment_rate          = $current['used'] > 0 ? min( 100, $current['repaid'] * 100 / $current['used'] ) : 0;
			$repayment_rate_previous = $before['used'] > 0 ? min( 100, $before['repaid'] * 100 / $before['used'] ) : 0;

			return [
				'date_range' => $date_range,
				'summary'    => [
					'outstanding'    => $accounts['outstanding'],
					'available'      => $accounts['available'],
					'utilization'    => $accounts['limit'] > 0 ? round( $accounts['outstanding'] * 100 / $accounts['limit'], 1 ) : 0,
					'active'         => $accounts['active'],
					'overdue'        => $accounts['overdue'],
					'overdue_amount' => $accounts['overdue_amount'],
					'due_soon'       => $schedule->ddwcpp_sum_remaining( [ 'view' => 'due_soon' ] ),
					'due_soon_count' => $schedule->ddwcpp_count( [ 'view' => 'due_soon' ] ),
					'late_amount'    => $schedule->ddwcpp_sum_remaining( [ 'view' => 'overdue' ] ),
					'late_count'     => $schedule->ddwcpp_count( [ 'view' => 'overdue' ] ),
					'used'           => $this->ddwcpp_make_summary( $current['used'], $before['used'] ),
					'repaid'         => $this->ddwcpp_make_summary( $current['repaid'], $before['repaid'] ),
					'orders'         => $this->ddwcpp_make_summary( $current['orders'], $before['orders'] ),
					'repayment_rate' => $this->ddwcpp_make_summary( $repayment_rate, $repayment_rate_previous ),
				],
				'charts'     => [
					'trend'    => DDFW_Dashboard_Data::build_time_series(
						$date_range['from'],
						$date_range['to'],
						[
							'used'   => $current['used_by_date'],
							'repaid' => $current['repaid_by_date'],
						]
					),
					'plans'    => $this->ddwcpp_get_plan_mix( $date_range['from'], $date_range['to'] ),
					'statuses' => array_values(
						array_filter(
							[
								[
									'label' => esc_html__( 'Active, nothing owed', 'buy-now-pay-later-for-woocommerce' ),
									'value' => max( 0, $accounts['active'] - $accounts['owing'] ),
								],
								[
									'label' => esc_html__( 'Owing, on time', 'buy-now-pay-later-for-woocommerce' ),
									'value' => max( 0, $accounts['owing'] - $accounts['overdue'] ),
								],
								[
									'label' => esc_html__( 'Overdue', 'buy-now-pay-later-for-woocommerce' ),
									'value' => $accounts['overdue'],
								],
							],
							function ( $row ) {
								return $row['value'] > 0;
							}
						)
					),
				],
				'tables'     => [
					'top_debtors'  => $accounts['top_debtors'],
					'recent'       => $this->ddwcpp_get_recent_transactions( 6 ),
					'applications' => $this->ddwcpp_get_pending_applications( 6 ),
					'upcoming'     => $schedule->ddwcpp_get_installments(
						[
							'view'     => 'pending',
							'per_page' => 6,
						]
					),
				],
			];
		}

		/**
		 * Orders placed on each plan in a period.
		 *
		 * @param string $from Y-m-d.
		 * @param string $to   Y-m-d.
		 * @return array
		 */
		protected function ddwcpp_get_plan_mix( $from, $to ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, dashboard aggregate.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT plan_title AS label, COUNT( DISTINCT order_id ) AS value FROM {$wpdb->prefix}ddwcpp_installments WHERE order_id > 0 AND created >= %s AND created <= %s GROUP BY plan_title ORDER BY value DESC", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A );

			return array_map(
				function ( $row ) {
					$row['value'] = (int) $row['value'];
					return $row;
				},
				(array) $rows
			);
		}

		/**
		 * Wrap a value with its period-over-period change.
		 *
		 * @param float $value         Current value.
		 * @param float $previous      Previous value.
		 * @param bool  $higher_better Whether a rise is good news.
		 * @return array
		 */
		protected function ddwcpp_make_summary( $value, $previous, $higher_better = true ) {
			$value    = (float) $value;
			$previous = (float) $previous;

			if ( $previous <= 0 ) {
				$change = $value > 0 ? 100 : 0;
			} else {
				$change = ( ( $value - $previous ) / $previous ) * 100;
			}

			return [
				'value'       => $value,
				'change'      => round( $change, 1 ),
				'is_positive' => $higher_better ? $value >= $previous : $value <= $previous,
			];
		}

		/**
		 * The period immediately before the selected one, of the same length.
		 *
		 * @param array $range Selected range.
		 * @return array
		 */
		protected function ddwcpp_get_previous_range( $range ) {
			$from = strtotime( $range['from'] );
			$to   = strtotime( $range['to'] );
			$days = max( 1, (int) ceil( ( $to - $from ) / DAY_IN_SECONDS ) + 1 );

			return [
				'from' => gmdate( 'Y-m-d', strtotime( '-' . $days . ' days', $from ) ),
				'to'   => gmdate( 'Y-m-d', strtotime( '-1 day', $from ) ),
			];
		}

		/**
		 * Totals of the transactions log over a date range.
		 *
		 * @param string $from Y-m-d.
		 * @param string $to   Y-m-d.
		 * @return array
		 */
		protected function ddwcpp_get_period_totals( $from, $to ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(date) AS day, reference, COUNT(*) AS total_count, SUM(CAST(amount AS DECIMAL(20,6))) AS total FROM {$this->table} WHERE date BETWEEN %s AND %s GROUP BY day, reference", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A );

			$totals = [
				'used'           => 0,
				'repaid'         => 0,
				'orders'         => 0,
				'used_by_date'   => [],
				'repaid_by_date' => [],
			];

			foreach ( $rows as $row ) {
				$amount = (float) $row['total'];

				if ( 'order_debit' === $row['reference'] ) {
					$totals['used']                       += $amount;
					$totals['orders']                     += (int) $row['total_count'];
					$totals['used_by_date'][ $row['day'] ] = $amount;
				} elseif ( in_array( $row['reference'], [ 'cleared_dues', 'manual_payment' ], true ) ) {
					// Payments through the store and payments the store recorded by hand are both money received.
					$totals['repaid'] += $amount;
					$totals['repaid_by_date'][ $row['day'] ] = ( isset( $totals['repaid_by_date'][ $row['day'] ] ) ? $totals['repaid_by_date'][ $row['day'] ] : 0 ) + $amount;
				}
			}

			return $totals;
		}

		/**
		 * Credit position of every active customer.
		 *
		 * Ponytail: one query over customer meta, summed in PHP. Fine into the tens of thousands of
		 * credit customers; past that, keep a running total in an option on each ddwcpp_record().
		 *
		 * @return array
		 */
		protected function ddwcpp_get_accounts() {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over user meta, no API equivalent.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT allotted.user_id, credit_limit.meta_value AS credit_limit, balance.meta_value AS balance, due.meta_value AS due_date
					FROM {$wpdb->usermeta} AS allotted
					LEFT JOIN {$wpdb->usermeta} AS credit_limit ON credit_limit.user_id = allotted.user_id AND credit_limit.meta_key = %s
					LEFT JOIN {$wpdb->usermeta} AS balance ON balance.user_id = allotted.user_id AND balance.meta_key = %s
					LEFT JOIN {$wpdb->usermeta} AS due ON due.user_id = allotted.user_id AND due.meta_key = %s
					WHERE allotted.meta_key = %s",
					'_ddwcpp_postpaid_limit',
					'_ddwcpp_postpaid_balance',
					'_ddwcpp_due_date',
					'_ddwcpp_postpaid_allotted'
				),
				ARRAY_A
			);

			$now      = current_time( 'mysql' );
			$accounts = [
				'outstanding'    => 0,
				'available'      => 0,
				'limit'          => 0,
				'active'         => count( $rows ),
				'owing'          => 0,
				'overdue'        => 0,
				'overdue_amount' => 0,
				'top_debtors'    => [],
			];

			$debtors = [];

			foreach ( $rows as $row ) {
				$limit   = '' !== (string) $row['credit_limit'] && null !== $row['credit_limit'] ? (float) $row['credit_limit'] : (float) $this->ddwcpp_configuration['limit'];
				$balance = (float) $row['balance'];
				$dues    = max( 0, $limit - $balance );

				$accounts['limit']     += $limit;
				$accounts['available'] += max( 0, $balance );

				if ( $dues <= 0 ) {
					continue;
				}

				$overdue = $row['due_date'] && $row['due_date'] < $now;

				$accounts['outstanding'] += $dues;
				++$accounts['owing'];

				if ( $overdue ) {
					++$accounts['overdue'];
					$accounts['overdue_amount'] += $dues;
				}

				$debtors[] = [
					'user_id'  => (int) $row['user_id'],
					'dues'     => $dues,
					'limit'    => $limit,
					'due_date' => (string) $row['due_date'],
					'overdue'  => $overdue,
				];
			}

			usort(
				$debtors,
				function ( $a, $b ) {
					return $b['dues'] <=> $a['dues'];
				}
			);

			$accounts['top_debtors'] = array_slice( $debtors, 0, 6 );

			return $accounts;
		}

		/**
		 * Latest transactions.
		 *
		 * @param int $limit Rows.
		 * @return array
		 */
		protected function ddwcpp_get_recent_transactions( $limit ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name from $wpdb->prefix.
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		}

		/**
		 * Oldest applications waiting for review first.
		 *
		 * @param int $limit Rows.
		 * @return \WP_User[]
		 */
		protected function ddwcpp_get_pending_applications( $limit ) {
			return get_users(
				[
					'meta_key'   => '_ddwcpp_verification_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small set of PostPaid customers.
					'meta_value' => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same small set.
					'number'     => $limit,
					'orderby'    => 'registered',
					'order'      => 'ASC',
				]
			);
		}
	}
}
