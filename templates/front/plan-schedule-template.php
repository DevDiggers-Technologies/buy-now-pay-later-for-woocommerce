<?php
/**
 * Payment schedule of one order: on the thank you page, the order view, My Account and emails.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Front;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Plan_Schedule_Template' ) ) {
	/**
	 * Plan schedule template class
	 */
	class DDWCPP_Plan_Schedule_Template {
		/**
		 * Construct
		 *
		 * @param int  $order_id  Order ID.
		 * @param bool $show_head Whether to print the heading and progress.
		 */
		public function __construct( $order_id, $show_head = true ) {
			$rows = self::ddwcpp_get_rows( $order_id );

			if ( $rows ) {
				$this->ddwcpp_render( $order_id, $rows, $show_head );
			}
		}

		/**
		 * Rows of an order that still count, i.e. not cancelled.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public static function ddwcpp_get_rows( $order_id ) {
			return array_values(
				array_filter(
					( new DDWCPP_Installments_Helper() )->ddwcpp_get_installments( [ 'order_id' => absint( $order_id ) ] ),
					function ( $row ) {
						return 'cancelled' !== $row['status'];
					}
				)
			);
		}

		/**
		 * Display status of a row: paid, overdue, due (the next one) or upcoming.
		 *
		 * @param array $row      Installment.
		 * @param bool  $is_next  Whether it is the next row to pay.
		 * @return string
		 */
		public static function ddwcpp_get_row_status( $row, $is_next ) {
			if ( 'pending' !== $row['status'] ) {
				return $row['status'];
			}

			if ( $row['due_date'] && $row['due_date'] < current_time( 'mysql' ) ) {
				return 'overdue';
			}

			return $is_next ? 'due_soon' : 'upcoming';
		}

		/**
		 * Render.
		 *
		 * @param int   $order_id  Order ID.
		 * @param array $rows      Installments.
		 * @param bool  $show_head Whether to print the heading and progress.
		 * @return void
		 */
		protected function ddwcpp_render( $order_id, $rows, $show_head ) {
			$paid = count( wp_list_filter( $rows, [ 'status' => 'paid' ] ) );
			$open = count( wp_list_filter( $rows, [ 'status' => 'pending' ] ) );
			// Cancelled rows are left out of $rows, so the plan's own count tells how many it had.
			$total     = max( count( $rows ), absint( $rows[0]['total_count'] ) );
			$ended     = ! $open && count( $rows ) < $total;
			$next_seen = false;
			$order     = wc_get_order( $order_id );
			$currency  = [ 'currency' => $order ? $order->get_currency() : '' ];
			?>
			<section class="ddwcpp-schedule">
				<?php if ( $show_head ) : ?>
					<div class="ddwcpp-schedule-head">
						<h2 class="ddwcpp-schedule-title"><?php esc_html_e( 'Payment schedule', 'buy-now-pay-later-for-woocommerce' ); ?></h2>
						<span class="ddwcpp-schedule-plan"><?php echo esc_html( $rows[0]['plan_title'] ); ?></span>
					</div>
					<span class="ddwcpp-segments" aria-hidden="true">
						<?php
						for ( $i = 0; $i < $total; $i++ ) {
							if ( $i < $paid ) {
								$segment = 'paid';
							} elseif ( $i >= count( $rows ) ) {
								$segment = 'cancelled';
							} else {
								$segment = $i === $paid ? 'next' : 'todo';
							}
							echo '<i class="is-' . esc_attr( $segment ) . '"></i>';
						}
						?>
					</span>
					<p class="ddwcpp-progress-label">
						<?php
						if ( $ended ) {
							/* translators: %1$d: payments made, %2$d: payments the plan had. */
							printf( esc_html__( 'Ended after %1$d of %2$d payments. The rest was cancelled with the order.', 'buy-now-pay-later-for-woocommerce' ), absint( $paid ), absint( $total ) );
						} else {
							/* translators: %1$d: payments made, %2$d: number of payments. */
							printf( esc_html__( '%1$d of %2$d payments made', 'buy-now-pay-later-for-woocommerce' ), absint( $paid ), absint( $total ) );
						}
						?>
					</p>
				<?php endif; ?>

				<ol class="ddwcpp-timeline">
					<?php
					foreach ( $rows as $row ) :
						$is_next = ! $next_seen && 'pending' === $row['status'];
						$status  = self::ddwcpp_get_row_status( $row, $is_next );

						if ( $is_next ) {
							$next_seen = true;
						}
						?>
						<li class="ddwcpp-timeline-item ddwcpp-timeline-<?php echo esc_attr( $status ); ?>">
							<span class="ddwcpp-timeline-dot" aria-hidden="true"></span>
							<span class="ddwcpp-timeline-body">
								<span class="ddwcpp-timeline-label">
									<?php
									/* translators: %1$d: payment number, %2$d: number of payments. */
									printf( esc_html__( 'Payment %1$d of %2$d', 'buy-now-pay-later-for-woocommerce' ), absint( $row['sequence'] ), absint( $row['total_count'] ) );
									?>
								</span>
								<span class="ddwcpp-timeline-date">
									<?php
									if ( 'paid' === $row['status'] && $row['paid_date'] ) {
										/* translators: %s: date. */
										printf( esc_html__( 'Paid %s', 'buy-now-pay-later-for-woocommerce' ), esc_html( date_i18n( wc_date_format(), strtotime( $row['paid_date'] ) ) ) );
									} elseif ( $row['due_date'] ) {
										/* translators: %s: date. */
										printf( esc_html__( 'Due %s', 'buy-now-pay-later-for-woocommerce' ), esc_html( date_i18n( wc_date_format(), strtotime( $row['due_date'] ) ) ) );
									} else {
										esc_html_e( 'No due date', 'buy-now-pay-later-for-woocommerce' );
									}
									?>
								</span>
							</span>
							<span class="ddwcpp-timeline-amount">
								<?php echo wp_kses_post( wc_price( (float) $row['amount'] + (float) $row['late_fee'], $currency ) ); ?>
								<?php if ( (float) $row['late_fee'] > 0 ) : ?>
									<small>
										<?php
										/* translators: %s: late fee. */
										printf( esc_html__( 'incl. %s late fee', 'buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $row['late_fee'], $currency ) ) );
										?>
									</small>
								<?php endif; ?>
							</span>
							<span class="ddwcpp-badge ddwcpp-badge-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ddwcpp_get_translation( 'due_soon' === $status ? 'pending' : $status ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>

				<?php
				if ( $next_seen && ! is_admin() && $order && get_current_user_id() === $order->get_customer_id() ) {
					self::ddwcpp_render_pay_button( $rows );
				}
				?>
			</section>
			<?php
		}

		/**
		 * "Pay next installment" button, settling the oldest rows first like every repayment.
		 *
		 * @param array $rows Installments of the order.
		 * @return void
		 */
		public static function ddwcpp_render_pay_button( $rows ) {
			foreach ( $rows as $row ) {
				if ( 'pending' === $row['status'] ) {
					$next = $row;
					break;
				}
			}

			if ( empty( $next ) ) {
				return;
			}

			$amount = self::ddwcpp_get_amount_to_settle( $next );
			?>
			<form method="post" action="<?php echo esc_url( ddwcpp_get_account_url() ); ?>" class="ddwcpp-pay-next">
				<?php wp_nonce_field( 'ddwcpp_front_action', 'ddwcpp_nonce' ); ?>
				<input type="hidden" name="ddwcpp_action" value="repay" />
				<input type="hidden" name="ddwcpp_amount" value="<?php echo esc_attr( wc_format_decimal( $amount, wc_get_price_decimals() ) ); ?>" />
				<button type="submit" class="button alt">
					<?php
					/* translators: %s: amount. */
					printf( esc_html__( 'Pay %s now', 'buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $amount ) ) );
					?>
				</button>
				<?php
				$earlier = $amount - DDWCPP_Installments_Helper::ddwcpp_get_remaining( $next );

				// Older installments are settled first, so say why the amount is higher than this one.
				if ( $earlier > 0.004 ) {
					?>
					<small class="ddwcpp-pay-note">
						<?php
						/* translators: %s: amount still owed on earlier installments. */
						printf( esc_html__( 'Includes %s still owed on earlier payments.', 'buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $earlier ) ) );
						?>
					</small>
					<?php
				}
				?>
			</form>
			<?php
		}

		/**
		 * What the customer pays to settle a row, given that older rows are settled first.
		 *
		 * @param array $target Installment.
		 * @return float
		 */
		public static function ddwcpp_get_amount_to_settle( $target ) {
			$amount = 0.0;

			foreach ( ( new DDWCPP_Installments_Helper() )->ddwcpp_get_pending( $target['user_id'] ) as $row ) {
				$amount += DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row );

				if ( (int) $row['id'] === (int) $target['id'] ) {
					break;
				}
			}

			return round( $amount, wc_get_price_decimals() );
		}

		/**
		 * Inline styled schedule table for emails.
		 *
		 * @param int $order_id Order ID.
		 * @return string
		 */
		public static function ddwcpp_get_schedule_html( $order_id ) {
			$rows = self::ddwcpp_get_rows( $order_id );

			if ( ! $rows ) {
				return '';
			}

			$cell = 'padding: 8px 12px; border-bottom: 1px solid #e5e5e5; text-align: left;';
			$html = '<table cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse; margin: 0 0 8px;"><tbody>';

			foreach ( $rows as $row ) {
				$html .= '<tr><td style="' . esc_attr( $cell ) . '">' . sprintf(
					/* translators: %1$d: payment number, %2$d: number of payments. */
					esc_html__( 'Payment %1$d of %2$d', 'buy-now-pay-later-for-woocommerce' ),
					absint( $row['sequence'] ),
					absint( $row['total_count'] )
				) . '</td><td style="' . esc_attr( $cell ) . '">' . ( $row['due_date'] ? esc_html( date_i18n( wc_date_format(), strtotime( $row['due_date'] ) ) ) : '&mdash;' ) . '</td><td style="' . esc_attr( $cell ) . ' text-align: right;">' . wc_price( (float) $row['amount'] + (float) $row['late_fee'] ) . '</td></tr>';
			}

			return $html . '</tbody></table>';
		}
	}
}
