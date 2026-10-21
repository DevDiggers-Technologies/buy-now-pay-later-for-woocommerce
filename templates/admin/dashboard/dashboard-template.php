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
				'change'      => $stat['change'],
				'is_positive' => $stat['is_positive'],
				'value_type'  => $money ? 'html' : 'number',
				'icon'        => DDFW_SVG::get_svg_icon( $icon, true ),
			];
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
						'welcome'  => esc_html__( 'Welcome back, %s!', 'buy-now-pay-later-for-woocommerce' ),
						'subtitle' => esc_html__( 'Credit issued, installments coming due and payments coming in across your Pay Later customers.', 'buy-now-pay-later-for-woocommerce' ),
					],
					'summary_cards' => [
						[
							'title'      => esc_html__( 'Outstanding Dues', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['outstanding'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'outstanding', true ),
						],
						[
							'title'      => esc_html__( 'Available Credit', 'buy-now-pay-later-for-woocommerce' ),
							'value'      => wc_price( $summary['available'] ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'credit', true ),
						],
						$this->ddwcpp_card( esc_html__( 'Credit Used', 'buy-now-pay-later-for-woocommerce' ), $summary['used'], 'used' ),
						$this->ddwcpp_card( esc_html__( 'Repayments Received', 'buy-now-pay-later-for-woocommerce' ), $summary['repaid'], 'repaid' ),
						[
							'title'       => esc_html__( 'Repayment Rate', 'buy-now-pay-later-for-woocommerce' ),
							'value'       => round( $summary['repayment_rate']['value'], 1 ) . '%',
							'change'      => $summary['repayment_rate']['change'],
							'is_positive' => $summary['repayment_rate']['is_positive'],
							'value_type'  => 'text',
							'icon'        => DDFW_SVG::get_svg_icon( 'repayment', true ),
						],
						[
							'title' => esc_html__( 'Active Customers', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $summary['active'],
							'icon'  => DDFW_SVG::get_svg_icon( 'customers', true ),
						],
						[
							'title'      => esc_html__( 'Due in Next 7 Days', 'buy-now-pay-later-for-woocommerce' ),
							/* translators: %1$s: amount, %2$s: number of payments. */
							'value'      => sprintf( esc_html__( '%1$s (%2$s)', 'buy-now-pay-later-for-woocommerce' ), wc_price( $summary['due_soon'] ), number_format_i18n( $summary['due_soon_count'] ) ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'installments', true ),
						],
						[
							'title'      => esc_html__( 'Overdue Payments', 'buy-now-pay-later-for-woocommerce' ),
							/* translators: %1$s: amount, %2$s: number of payments. */
							'value'      => sprintf( esc_html__( '%1$s (%2$s)', 'buy-now-pay-later-for-woocommerce' ), wc_price( $summary['late_amount'] ), number_format_i18n( $summary['late_count'] ) ),
							'value_type' => 'html',
							'icon'       => DDFW_SVG::get_svg_icon( 'overdue', true ),
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
		 * Empty widget message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		protected function ddwcpp_render_no_data( $message ) {
			?>
			<div class="ddfw-dash-no-data"><?php echo esc_html( $message ); ?></div>
			<?php
		}

		/**
		 * Next payments due across all customers.
		 *
		 * @return void
		 */
		public function ddwcpp_render_upcoming() {
			$rows = $this->data['tables']['upcoming'];

			if ( empty( $rows ) ) {
				$this->ddwcpp_render_no_data( esc_html__( 'No payments are scheduled.', 'buy-now-pay-later-for-woocommerce' ) );
				return;
			}

			$now = current_time( 'mysql' );
			?>
			<div class="ddfw-dash-list">
				<?php
				foreach ( $rows as $row ) :
					$user = get_userdata( $row['user_id'] );
					$late = $row['due_date'] && $row['due_date'] < $now;
					?>
					<div class="ddfw-dash-list-item">
						<div class="ddfw-dash-list-avatar"><?php echo get_avatar( $row['user_id'], 32 ); ?></div>
						<div class="ddfw-dash-list-info">
							<div class="ddfw-dash-list-title"><strong><?php echo esc_html( $user ? $user->display_name : __( 'Deleted customer', 'buy-now-pay-later-for-woocommerce' ) ); ?></strong></div>
							<div class="ddfw-dash-list-meta">
								<?php
								if ( $late ) {
									echo '<span class="ddwcpp-overdue">' . esc_html__( 'Overdue', 'buy-now-pay-later-for-woocommerce' ) . '</span> &bull; ';
								}

								echo esc_html( $row['plan_title'] ) . ' &bull; ';
								echo $row['due_date'] ? esc_html( date_i18n( wc_date_format(), strtotime( $row['due_date'] ) ) ) : esc_html__( 'No due date', 'buy-now-pay-later-for-woocommerce' );
								?>
							</div>
						</div>
						<div class="ddfw-dash-list-value<?php echo $late ? ' negative' : ''; ?>"><?php echo wp_kses_post( wc_price( \DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ) ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="ddfw-dash-list-footer"><a href="<?php echo esc_url( ddwcpp_get_dashboard_url( [ 'menu' => 'installments' ] ) ); ?>"><?php esc_html_e( 'View all installments', 'buy-now-pay-later-for-woocommerce' ); ?></a></p>
			<?php
		}

		/**
		 * Top outstanding balances.
		 *
		 * @return void
		 */
		public function ddwcpp_render_top_debtors() {
			$debtors = $this->data['tables']['top_debtors'];

			if ( empty( $debtors ) ) {
				$this->ddwcpp_render_no_data( esc_html__( 'Nobody owes anything right now.', 'buy-now-pay-later-for-woocommerce' ) );
				return;
			}
			?>
			<div class="ddfw-dash-list">
				<?php
				foreach ( $debtors as $debtor ) :
					$user = get_userdata( $debtor['user_id'] );

					if ( ! $user ) {
						continue;
					}
					?>
					<div class="ddfw-dash-list-item">
						<div class="ddfw-dash-list-avatar"><?php echo get_avatar( $user->ID, 32 ); ?></div>
						<div class="ddfw-dash-list-info">
							<div class="ddfw-dash-list-title"><strong><a href="
							<?php
							echo esc_url(
								ddwcpp_get_dashboard_url(
									[
										'menu'        => 'transactions',
										'customer-id' => $user->ID,
									]
								)
							);
							?>
																				"><?php echo esc_html( $user->display_name ); ?></a></strong></div>
							<div class="ddfw-dash-list-meta">
								<?php
								if ( $debtor['overdue'] ) {
									echo '<span class="ddwcpp-overdue">' . esc_html__( 'Overdue', 'buy-now-pay-later-for-woocommerce' ) . '</span> &bull; ';
								}

								if ( $debtor['due_date'] ) {
									/* translators: %s: due date. */
									printf( esc_html__( 'Due %s', 'buy-now-pay-later-for-woocommerce' ), esc_html( date_i18n( wc_date_format(), strtotime( $debtor['due_date'] ) ) ) );
								} else {
									/* translators: %s: credit limit. */
									printf( esc_html__( 'Limit %s', 'buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $debtor['limit'] ) ) );
								}
								?>
							</div>
						</div>
						<div class="ddfw-dash-list-value negative"><?php echo wp_kses_post( wc_price( $debtor['dues'] ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Applications waiting for a decision.
		 *
		 * @return void
		 */
		public function ddwcpp_render_applications() {
			$users = $this->data['tables']['applications'];

			if ( empty( $users ) ) {
				$this->ddwcpp_render_no_data( esc_html__( 'No applications waiting.', 'buy-now-pay-later-for-woocommerce' ) );
				return;
			}
			?>
			<div class="ddfw-dash-list">
				<?php
				foreach ( $users as $user ) :
					$applied = get_user_meta( $user->ID, '_ddwcpp_applied_on', true );
					?>
					<div class="ddfw-dash-list-item">
						<div class="ddfw-dash-list-avatar"><?php echo get_avatar( $user->ID, 32 ); ?></div>
						<div class="ddfw-dash-list-info">
							<div class="ddfw-dash-list-title"><strong><?php echo esc_html( $user->display_name ); ?></strong></div>
							<div class="ddfw-dash-list-meta">
								<?php
								echo esc_html( $user->user_email );

								if ( $applied ) {
									/* translators: %s: time since the application. */
									echo ' &bull; ' . esc_html( sprintf( __( '%s ago', 'buy-now-pay-later-for-woocommerce' ), human_time_diff( strtotime( $applied ), current_time( 'timestamp' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Compared with a site time value.
								}
								?>
							</div>
						</div>
						<div class="ddfw-dash-list-value"><a class="button button-small" href="
						<?php
						echo esc_url(
							ddwcpp_get_dashboard_url(
								[
									'menu'   => 'customers',
									'status' => 'pending',
								]
							)
						);
						?>
																								"><?php esc_html_e( 'Review', 'buy-now-pay-later-for-woocommerce' ); ?></a></div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Latest transactions.
		 *
		 * @return void
		 */
		public function ddwcpp_render_recent_transactions() {
			$transactions = $this->data['tables']['recent'];

			if ( empty( $transactions ) ) {
				$this->ddwcpp_render_no_data( esc_html__( 'No transactions yet.', 'buy-now-pay-later-for-woocommerce' ) );
				return;
			}
			?>
			<div class="ddfw-dash-list">
				<?php
				foreach ( $transactions as $transaction ) :
					$user = get_userdata( $transaction['user_id'] );
					?>
					<div class="ddfw-dash-list-item">
						<div class="ddfw-dash-list-info">
							<div class="ddfw-dash-list-title">
								<strong><a href="
								<?php
								echo esc_url(
									ddwcpp_get_dashboard_url(
										[
											'menu'   => 'transactions',
											'action' => 'view',
											'transaction_id' => $transaction['id'],
										]
									)
								);
								?>
													"><?php echo esc_html( $user ? $user->display_name : '#' . $transaction['user_id'] ); ?></a></strong>
							</div>
							<div class="ddfw-dash-list-meta">
								<?php echo esc_html( ddwcpp_get_translation( $transaction['reference'] ) ); ?> &bull; <?php echo esc_html( date_i18n( wc_date_format(), strtotime( $transaction['date'] ) ) ); ?>
							</div>
						</div>
						<div class="ddfw-dash-list-value <?php echo 'credit' === $transaction['type'] ? 'positive' : 'negative'; ?>">
							<?php echo ( 'credit' === $transaction['type'] ? '+' : '&minus;' ) . wp_kses_post( wc_price( $transaction['amount'] ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
}
