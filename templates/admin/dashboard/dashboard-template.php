<?php
/**
 * Dashboard Template
 *
 * Supplies PostPaid data to the shared devdiggers-framework dashboard builder.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Dashboard;

use DDWCPostPaid\Helper\Dashboard\DDWCPP_Dashboard_Helper;
use DevDiggers\Framework\Includes\DDFW_Dashboard;
use DevDiggers\Framework\Includes\DDFW_SVG;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Dashboard_Template' ) ) {
	/**
	 * Dashboard template class
	 */
	class DDWCPP_Dashboard_Template {
		/**
		 * Dashboard Data Variable
		 *
		 * @var array
		 */
		protected $data;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			if ( ! class_exists( '\\DevDiggers\\Framework\\Includes\\DDFW_Dashboard' ) ) {
				return;
			}

			$this->data = ( new DDWCPP_Dashboard_Helper( $ddwcpp_configuration ) )->ddwcpp_get_dashboard_data();

			if ( empty( $ddwcpp_configuration['enabled'] ) ) {
				/* translators: %s: configuration link. */
				ddfw_print_notification( sprintf( esc_html__( 'Pay Later is turned off, so customers cannot use it. %s', 'buy-now-pay-later-for-woocommerce' ), '<a href="' . esc_url( ddwcpp_get_dashboard_url( [ 'menu' => 'configuration' ] ) ) . '">' . esc_html__( 'Turn it on', 'buy-now-pay-later-for-woocommerce' ) . '</a>' ), 'warning' );
			}

			$this->ddwcpp_render();
		}

		/**
		 * A card for a period figure with its change.
		 *
		 * @param string $title Title.
		 * @param array  $stat  Summary from the helper.
		 * @param string $icon  Icon key.
		 * @param bool   $money Whether the value is an amount.
		 * @return array
		 */
		protected function ddwcpp_card( $title, $stat, $icon, $money = true ) {
			return [
				'title'       => $title,
				'value'       => $money ? wc_price( $stat['value'] ) : $stat['value'],
				'previous'    => $money ? wc_price( $stat['previous'] ) : number_format_i18n( $stat['previous'] ),
				'change'      => $stat['change'],
				'note'        => $this->ddwcpp_no_change_note( $stat ),
				'is_positive' => $stat['is_positive'],
				'value_type'  => $money ? 'html' : 'number',
				'icon'        => DDFW_SVG::get_svg_icon( $icon, true ),
			];
		}

		/**
		 * Note for a figure that did not move, which the change line would leave empty.
		 *
		 * @param array $stat Summary from the helper.
		 * @return string
		 */
		protected function ddwcpp_no_change_note( $stat ) {
			return 0.0 === $stat['change'] ? esc_html__( 'No change vs previous period', 'buy-now-pay-later-for-woocommerce' ) : '';
		}

		/**
		 * Render dashboard via the shared framework builder.
		 *
		 * @return void
		 */
		protected function ddwcpp_render() {
			$summary    = $this->data['summary'];
			$date_label = $this->data['date_range']['label'];

			new DDFW_Dashboard(
				[
					'columns'       => 4,
					'header'        => [
						/* translators: %s: current user display name. */
						'welcome'    => esc_html__( 'Welcome back, %s!', 'buy-now-pay-later-for-woocommerce' ),
						'subtitle'   => esc_html__( 'Credit issued, installments coming due and payments coming in across your Pay Later customers.', 'buy-now-pay-later-for-woocommerce' ),
						'date_range' => $this->data['date_range'],
					],
					'summary_cards' => [
						[
							'title'      => esc_html__( 'Outstanding Dues', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['outstanding'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'outstanding', true ),
							'note'       => esc_html__( 'Owed right now, from any date', 'buy-now-pay-later-for-woocommerce' ),
						],
						[
							'title'      => esc_html__( 'Available Credit', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['available'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'credit', true ),
							'note'       => esc_html__( 'Unused credit, right now', 'buy-now-pay-later-for-woocommerce' ),
						],
						$this->ddwcpp_card( esc_html__( 'Credit Used', 'buy-now-pay-later-for-woocommerce' ), $summary['used'], 'used' ),
						$this->ddwcpp_card( esc_html__( 'Repayments Received', 'buy-now-pay-later-for-woocommerce' ), $summary['repaid'], 'repaid' ),
						[
							'title'       => esc_html__( 'Repayment Rate', 'buy-now-pay-later-for-woocommerce' ),
							'value'       => wc_format_localized_decimal( round( $summary['repayment_rate']['value'], 1 ) ) . '%',
							'previous'    => wc_format_localized_decimal( round( $summary['repayment_rate']['previous'], 1 ) ) . '%',
							'change'      => $summary['repayment_rate']['change'],
							'note'        => $this->ddwcpp_no_change_note( $summary['repayment_rate'] ),
							'is_positive' => $summary['repayment_rate']['is_positive'],
							'value_type'  => 'text',
							'icon'        => DDFW_SVG::get_svg_icon( 'repayment', true ),
						],
						[
							'title' => esc_html__( 'Active Customers', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $summary['active'],
							'icon'  => DDFW_SVG::get_svg_icon( 'customers', true ),
							'note'  => esc_html__( 'With a credit line, right now', 'buy-now-pay-later-for-woocommerce' ),
						],
						[
							'title'      => esc_html__( 'Due in Next 7 Days', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['due_soon'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'installments', true ),
							/* translators: %s: number of payments. */
							'note'       => sprintf( esc_html( _n( '%s payment coming due', '%s payments coming due', $summary['due_soon_count'], 'buy-now-pay-later-for-woocommerce' ) ), number_format_i18n( $summary['due_soon_count'] ) ),
						],
						[
							'title'      => esc_html__( 'Overdue Payments', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['late_amount'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'overdue', true ),
							/* translators: %s: number of payments. */
							'note'       => sprintf( esc_html( _n( '%s payment past due, from any date', '%s payments past due, from any date', $summary['late_count'], 'buy-now-pay-later-for-woocommerce' ) ), number_format_i18n( $summary['late_count'] ) ),
						],
					],
					'charts'        => [
						[
							'id'           => 'ddwcpp-credit-trend',
							'title'        => esc_html__( 'Credit Used vs Repaid', 'buy-now-pay-later-for-woocommerce' ),
							'date_label'   => $date_label,
							'full_width'   => true,
							'type'         => 'line',
							'value_format' => 'currency',
							'data'         => $this->data['charts']['trend'],
							'x_key'        => 'date',
							'series'       => [
								[
									'key'   => 'used',
									'label' => esc_html__( 'Credit Used', 'buy-now-pay-later-for-woocommerce' ),
									'color' => '#0256ff',
								],
								[
									'key'   => 'repaid',
									'label' => esc_html__( 'Repaid', 'buy-now-pay-later-for-woocommerce' ),
									'color' => '#16a34a',
								],
							],
							'empty'        => [
								'title' => esc_html__( 'No credit activity yet', 'buy-now-pay-later-for-woocommerce' ),
								'desc'  => esc_html__( 'The trend appears once customers pay with Pay Later or clear their dues.', 'buy-now-pay-later-for-woocommerce' ),
							],
						],
						[
							'id'         => 'ddwcpp-plan-mix',
							'title'      => esc_html__( 'Orders by Plan', 'buy-now-pay-later-for-woocommerce' ),
							'date_label' => $date_label,
							'type'       => 'doughnut',
							'data'       => $this->data['charts']['plans'],
							'label_key'  => 'label',
							'value_key'  => 'value',
							'empty'      => [
								'title' => esc_html__( 'No plan orders in this period', 'buy-now-pay-later-for-woocommerce' ),
								'desc'  => esc_html__( 'See which payment plans your customers prefer once they check out with Pay Later.', 'buy-now-pay-later-for-woocommerce' ),
							],
						],
						[
							'id'        => 'ddwcpp-customer-health',
							'title'     => esc_html__( 'Customer Health', 'buy-now-pay-later-for-woocommerce' ),
							'type'      => 'doughnut',
							'data'      => $this->data['charts']['statuses'],
							'label_key' => 'label',
							'value_key' => 'value',
							'empty'     => [
								'title' => esc_html__( 'No active customers yet', 'buy-now-pay-later-for-woocommerce' ),
								'desc'  => esc_html__( 'Approve applications on the Customers screen to start a credit line.', 'buy-now-pay-later-for-woocommerce' ),
							],
						],
					],
					'widgets'       => [
						[
							'title'  => esc_html__( 'Upcoming Payments', 'buy-now-pay-later-for-woocommerce' ),
							'render' => [ $this, 'ddwcpp_render_upcoming' ],
						],
						[
							'title'  => esc_html__( 'Top Outstanding Balances', 'buy-now-pay-later-for-woocommerce' ),
							'render' => [ $this, 'ddwcpp_render_top_debtors' ],
						],
						[
							'title'  => esc_html__( 'Applications Awaiting Review', 'buy-now-pay-later-for-woocommerce' ),
							'render' => [ $this, 'ddwcpp_render_applications' ],
						],
						[
							'title'  => esc_html__( 'Recent Transactions', 'buy-now-pay-later-for-woocommerce' ),
							'render' => [ $this, 'ddwcpp_render_recent_transactions' ],
						],
					],
				]
			);
		}

		/**
		 * Next payments due across all customers.
		 *
		 * @return void
		 */
		public function ddwcpp_render_upcoming() {
			$now   = current_time( 'mysql' );
			$items = [];

			foreach ( $this->data['tables']['upcoming'] as $row ) {
				$user    = get_userdata( $row['user_id'] );
				$late    = $row['due_date'] && $row['due_date'] < $now;
				$meta    = array_filter(
					[
						$late ? __( 'Overdue', 'buy-now-pay-later-for-woocommerce' ) : '',
						$row['plan_title'],
						$row['due_date'] ? date_i18n( wc_date_format(), strtotime( $row['due_date'] ) ) : __( 'No due date', 'buy-now-pay-later-for-woocommerce' ),
					]
				);
				$items[] = [
					'url'         => $user ? ddwcpp_get_dashboard_url(
						[
							'menu'        => 'installments',
							'customer-id' => $user->ID,
						]
					) : '',
					'title'       => $user ? $user->display_name : __( 'Deleted customer', 'buy-now-pay-later-for-woocommerce' ),
					'meta'        => implode( ' · ', $meta ),
					'image'       => get_avatar_url( $row['user_id'], [ 'size' => 72 ] ),
					'shape'       => 'round',
					'value'       => wc_price( \DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ) ),
					'value_class' => $late ? 'negative' : '',
				];
			}

			DDFW_Dashboard::render_list( $items, __( 'No payments are scheduled.', 'buy-now-pay-later-for-woocommerce' ) );

			if ( $items ) {
				?>
				<p class="ddfw-dash-list-footer"><a href="<?php echo esc_url( ddwcpp_get_dashboard_url( [ 'menu' => 'installments' ] ) ); ?>"><?php esc_html_e( 'View all installments', 'buy-now-pay-later-for-woocommerce' ); ?></a></p>
				<?php
			}
		}

		/**
		 * Top outstanding balances.
		 *
		 * @return void
		 */
		public function ddwcpp_render_top_debtors() {
			$items = [];

			foreach ( $this->data['tables']['top_debtors'] as $debtor ) {
				$user = get_userdata( $debtor['user_id'] );

				if ( ! $user ) {
					continue;
				}

				/* translators: %s: due date. */
				$when    = $debtor['due_date'] ? sprintf( __( 'Due %s', 'buy-now-pay-later-for-woocommerce' ), date_i18n( wc_date_format(), strtotime( $debtor['due_date'] ) ) ) : sprintf( /* translators: %s: credit limit. */ __( 'Limit %s', 'buy-now-pay-later-for-woocommerce' ), wp_strip_all_tags( wc_price( $debtor['limit'] ) ) );
				$items[] = [
					'url'         => ddwcpp_get_dashboard_url(
						[
							'menu'        => 'transactions',
							'customer-id' => $user->ID,
						]
					),
					'title'       => $user->display_name,
					'meta'        => ( $debtor['overdue'] ? __( 'Overdue', 'buy-now-pay-later-for-woocommerce' ) . ' · ' : '' ) . $when,
					'image'       => get_avatar_url( $user->ID, [ 'size' => 72 ] ),
					'shape'       => 'round',
					'value'       => wc_price( $debtor['dues'] ),
					'value_class' => 'negative',
				];
			}

			DDFW_Dashboard::render_list( $items, __( 'Nobody owes anything right now.', 'buy-now-pay-later-for-woocommerce' ) );
		}

		/**
		 * Applications waiting for a decision.
		 *
		 * @return void
		 */
		public function ddwcpp_render_applications() {
			$items = [];

			foreach ( $this->data['tables']['applications'] as $user ) {
				$applied = get_user_meta( $user->ID, '_ddwcpp_applied_on', true );
				$items[] = [
					'title' => $user->display_name,
					/* translators: %s: time since the application. */
					'meta'  => $user->user_email . ( $applied ? ' · ' . sprintf( __( '%s ago', 'buy-now-pay-later-for-woocommerce' ), human_time_diff( strtotime( $applied ), current_time( 'timestamp' ) ) ) : '' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Compared with a site time value.
					'image' => get_avatar_url( $user->ID, [ 'size' => 72 ] ),
					'shape' => 'round',
					'value' => '<a class="button button-small" href="' . esc_url(
						ddwcpp_get_dashboard_url(
							[
								'menu'   => 'customers',
								'status' => 'pending',
							]
						)
					) . '">' . esc_html__( 'Review', 'buy-now-pay-later-for-woocommerce' ) . '</a>',
				];
			}

			DDFW_Dashboard::render_list( $items, __( 'No applications waiting.', 'buy-now-pay-later-for-woocommerce' ) );
		}

		/**
		 * Latest transactions.
		 *
		 * @return void
		 */
		public function ddwcpp_render_recent_transactions() {
			$items = [];

			foreach ( $this->data['tables']['recent'] as $transaction ) {
				$user    = get_userdata( $transaction['user_id'] );
				$credit  = 'credit' === $transaction['type'];
				$items[] = [
					'url'         => ddwcpp_get_dashboard_url(
						[
							'menu'           => 'transactions',
							'action'         => 'view',
							'transaction_id' => $transaction['id'],
						]
					),
					'title'       => $user ? $user->display_name : '#' . $transaction['user_id'],
					'meta'        => ddwcpp_get_translation( $transaction['reference'] ) . ' · ' . date_i18n( wc_date_format(), strtotime( $transaction['date'] ) ),
					'image'       => get_avatar_url( $transaction['user_id'], [ 'size' => 72 ] ),
					'shape'       => 'round',
					'value'       => ( $credit ? '+' : '&minus;' ) . wc_price( $transaction['amount'] ),
					'value_class' => $credit ? 'positive' : 'negative',
				];
			}

			DDFW_Dashboard::render_list( $items, __( 'No transactions yet.', 'buy-now-pay-later-for-woocommerce' ) );
		}
	}
}
