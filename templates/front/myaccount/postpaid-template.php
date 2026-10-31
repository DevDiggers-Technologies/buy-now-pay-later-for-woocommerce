<?php
/**
 * My Account PostPaid screen.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Front\MyAccount;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;
use DDWCPostPaid\Templates\Front\DDWCPP_Plan_Schedule_Template;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Postpaid_Template' ) ) {
	/**
	 * PostPaid Template Class
	 */
	class DDWCPP_Postpaid_Template {
		/**
		 * Transactions per page.
		 */
		const PER_PAGE = 10;

		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Installments helper.
		 *
		 * @var DDWCPP_Installments_Helper
		 */
		protected $installments_helper;

		/**
		 * Construct
		 *
		 * @param array  $ddwcpp_configuration Configuration.
		 * @param string $section              overview, plans or history.
		 * @param int    $arg                  Order ID for plans, page for history.
		 */
		public function __construct( $ddwcpp_configuration, $section = 'overview', $arg = 0 ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->installments_helper  = new DDWCPP_Installments_Helper();
			$this->ddwcpp_render( $section, $arg );
		}

		/**
		 * Hidden fields every PostPaid form posts.
		 *
		 * @param string $action Form action.
		 * @return void
		 */
		protected function ddwcpp_form_fields( $action ) {
			wp_nonce_field( 'ddwcpp_front_action', 'ddwcpp_nonce' );
			?>
			<input type="hidden" name="ddwcpp_action" value="<?php echo esc_attr( $action ); ?>" />
			<?php
		}

		/**
		 * Render the screen.
		 *
		 * @param string $section Section.
		 * @param int    $arg     Section argument.
		 * @return void
		 */
		protected function ddwcpp_render( $section, $arg ) {
			$user_id = get_current_user_id();
			$users   = new DDWCPP_Users_Helper( $this->ddwcpp_configuration );

			$users->ddwcpp_maybe_auto_activate( $user_id );

			$account = $users->ddwcpp_get_account( $user_id );
			$active  = $account['allotted'] && 'eligible' === $account['status'];
			?>
			<div class="ddwcpp-account ddwcpp-tabs-segmented">
				<?php
				// A customer who still owes keeps the section, whatever happened to their approval, so they can pay.
				if ( ! $active && $account['dues'] <= 0 ) {
					$this->ddwcpp_render_status_panel( $account );
					echo '</div>';
					return;
				}

				if ( ! $active ) {
					?>
					<div class="ddwcpp-alert" role="alert">
						<?php $this->ddwcpp_print_icon( 'alert' ); ?>
						<p>
							<strong><?php esc_html_e( 'Pay Later is not available for new orders.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></strong>
							<?php esc_html_e( 'You can still pay what you owe here.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>
						</p>
					</div>
					<?php
				}

				$this->ddwcpp_render_tabs( $section, $user_id );

				switch ( $section ) {
					case 'plans':
						$arg ? $this->ddwcpp_render_plan( $arg, $user_id ) : $this->ddwcpp_render_plans( $user_id );
						break;
					case 'history':
						$this->ddwcpp_render_transactions( $user_id, max( 1, $arg ) );
						break;
					default:
						$this->ddwcpp_render_overview( $account, $user_id );
				}
				?>
			</div>
			<?php
		}

		/**
		 * Line icon, drawn at 1.5 stroke like SF Symbols and Material Symbols.
		 *
		 * @param string $name Icon name.
		 * @return string
		 */
		protected function ddwcpp_icon( $name ) {
			$paths = [
				'calendar' => '<rect x="3" y="4" width="18" height="18" rx="3"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path>',
				'check'    => '<circle cx="12" cy="12" r="9"></circle><path d="m8.5 12.5 2.5 2.5 5-5.5"></path>',
				'alert'    => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7.5v5"></path><path d="M12 16h.01"></path>',
				'bag'      => '<path d="M6 7h12l1 13H5L6 7Z"></path><path d="M9 7a3 3 0 0 1 6 0"></path>',
				'chevron'  => '<path d="m9 6 6 6-6 6"></path>',
				'split'    => '<rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M9 5v14"></path><path d="M15 5v14"></path>',
				'card'     => '<rect x="3" y="5" width="18" height="14" rx="3"></rect><path d="M3 10h18"></path><path d="m4 20 16-16"></path>',
				'bell'     => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16Z"></path><path d="M10 21h4"></path>',
				'clock'    => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
				'spark'    => '<path d="M12 3v4"></path><path d="M12 17v4"></path><path d="M3 12h4"></path><path d="M17 12h4"></path><path d="m6 6 2.5 2.5"></path><path d="m15.5 15.5 2.5 2.5"></path><path d="m18 6-2.5 2.5"></path><path d="m8.5 15.5-2.5 2.5"></path>',
			];

			return '<svg class="ddwcpp-icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
		}

		/**
		 * Echo an icon.
		 *
		 * @param string $name Icon name.
		 * @return void
		 */
		protected function ddwcpp_print_icon( $name ) {
			echo wp_kses( $this->ddwcpp_icon( $name ), ddwcpp_get_allowed_html() );
		}

		/**
		 * Section tabs.
		 *
		 * @param string $section Current section.
		 * @param int    $user_id User ID.
		 * @return void
		 */
		protected function ddwcpp_render_tabs( $section, $user_id ) {
			$open = count( wp_list_filter( $this->installments_helper->ddwcpp_get_plans_summary( $user_id ), [ 'status' => 'pending' ] ) );
			$tabs = [
				'overview' => [ esc_html__( 'Overview', 'devdiggers-buy-now-pay-later-for-woocommerce' ), ddwcpp_get_account_url() ],
				'plans'    => [ esc_html__( 'Payment Plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ), ddwcpp_get_account_url( 'plans' ) ],
				'history'  => [ esc_html__( 'History', 'devdiggers-buy-now-pay-later-for-woocommerce' ), ddwcpp_get_account_url( 'history' ) ],
			];
			?>
			<nav class="ddwcpp-nav" aria-label="<?php esc_attr_e( 'Pay Later sections', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<a href="<?php echo esc_url( $tab[1] ); ?>" class="ddwcpp-nav-item<?php echo $key === $section ? ' is-active' : ''; ?>" <?php echo $key === $section ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $tab[0] ); ?>
						<?php if ( 'plans' === $key && $open ) : ?>
							<span class="ddwcpp-nav-count"><?php echo esc_html( number_format_i18n( $open ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
		}

		/**
		 * Overview: what the customer can spend, what is due next and what follows.
		 *
		 * @param array $account Account.
		 * @param int   $user_id User ID.
		 * @return void
		 */
		protected function ddwcpp_render_overview( $account, $user_id ) {
			$used     = $account['limit'] > 0 ? min( 100, max( 0, round( $account['dues'] * 100 / $account['limit'] ) ) ) : 0;
			$pending  = $this->installments_helper->ddwcpp_get_pending( $user_id );
			$next     = $pending ? $pending[0] : null;
			$next_pay = $next ? DDWCPP_Plan_Schedule_Template::ddwcpp_get_amount_to_settle( $next ) : 0;

			if ( $account['overdue'] ) {
				?>
				<div class="ddwcpp-alert" role="alert">
					<?php $this->ddwcpp_print_icon( 'alert' ); ?>
					<p>
						<strong><?php esc_html_e( 'A payment is overdue.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></strong>
						<?php echo ! empty( $this->ddwcpp_configuration['block_overdue'] ) ? esc_html__( 'Pay Later is paused until it is paid.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html__( 'Please pay it as soon as you can.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>
					</p>
				</div>
				<?php
			}
			?>
			<div class="ddwcpp-hero">
				<?php
				$this->ddwcpp_render_balance( $account, $used );

				$this->ddwcpp_render_next_payment( $next, $next_pay, $account );
				?>
			</div>
			<?php
			if ( count( $pending ) > 1 ) {
				?>
				<section class="ddwcpp-section">
					<div class="ddwcpp-section-head">
						<h3><?php esc_html_e( 'Coming up', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
						<a href="<?php echo esc_url( ddwcpp_get_account_url( 'plans' ) ); ?>"><?php esc_html_e( 'All plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
					</div>
					<?php $this->ddwcpp_render_installments_list( array_slice( $pending, 1, 5 ) ); ?>
				</section>
				<?php
			}
		}

		/**
		 * Balance panel: one large figure, a meter and the numbers behind it.
		 *
		 * @param array $account Account.
		 * @param int   $used    Percentage in use.
		 * @return void
		 */
		protected function ddwcpp_render_balance( $account, $used ) {
			?>
			<section class="ddwcpp-balance" aria-label="<?php esc_attr_e( 'Your balance', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>">
				<span class="ddwcpp-eyebrow"><?php esc_html_e( 'Available to spend', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
				<strong class="ddwcpp-figure"><?php echo wp_kses_post( wc_price( max( 0, $account['balance'] ) ) ); ?></strong>
				<div class="ddwcpp-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $used ); ?>" aria-label="<?php esc_attr_e( 'Spending limit in use', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>">
					<span class="ddwcpp-meter-used" style="--ddwcpp-used: <?php echo esc_attr( $used ); ?>%;"></span>
				</div>
				<dl class="ddwcpp-legend">
					<div class="ddwcpp-legend-owed">
						<dt><?php esc_html_e( 'Owed', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></dt>
						<dd><?php echo wp_kses_post( wc_price( $account['dues'] ) ); ?></dd>
					</div>
					<div class="ddwcpp-legend-free">
						<dt><?php esc_html_e( 'Available', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></dt>
						<dd><?php echo wp_kses_post( wc_price( max( 0, $account['balance'] ) ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Limit', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></dt>
						<dd><?php echo wp_kses_post( wc_price( $account['limit'] ) ); ?></dd>
					</div>
				</dl>
			</section>
			<?php
		}

		/**
		 * Days until a due date, negative when late; null without a date.
		 *
		 * @param array $row Installment.
		 * @return int|null
		 */
		protected function ddwcpp_get_days_left( $row ) {
			if ( ! $row['due_date'] ) {
				return null;
			}

			// Both dates are site time, so compare the calendar days as written.
			return (int) round( ( strtotime( substr( $row['due_date'], 0, 10 ) ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
		}

		/**
		 * Next payment tile: a day countdown, the amount and the action.
		 *
		 * @param array|null $next     Next installment.
		 * @param float      $next_pay Amount that settles it.
		 * @param array      $account  Account.
		 * @return void
		 */
		protected function ddwcpp_render_next_payment( $next, $next_pay, $account ) {
			if ( ! $next ) {
				?>
				<section class="ddwcpp-next is-clear">
					<?php $this->ddwcpp_print_icon( 'check' ); ?>
					<span class="ddwcpp-eyebrow"><?php esc_html_e( 'Next payment', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
					<strong class="ddwcpp-next-title"><?php esc_html_e( 'You are all caught up', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></strong>
					<p class="ddwcpp-muted"><?php esc_html_e( 'Choose Pay Later at checkout to split your next order.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></p>
					<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Start shopping', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
				</section>
				<?php
				return;
			}

			$days = $this->ddwcpp_get_days_left( $next );
			?>
			<section class="ddwcpp-next<?php echo $account['overdue'] ? ' is-overdue' : ''; ?>">
				<div class="ddwcpp-next-top">
					<span class="ddwcpp-eyebrow"><?php echo $account['overdue'] ? esc_html__( 'Overdue', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html__( 'Next payment', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
					<?php if ( null !== $days ) : ?>
						<span class="ddwcpp-countdown">
							<?php
							if ( $days < 0 ) {
								/* translators: %s: number of days late. */
								printf( esc_html( _n( '%s day late', '%s days late', abs( $days ), 'devdiggers-buy-now-pay-later-for-woocommerce' ) ), '<b>' . esc_html( number_format_i18n( abs( $days ) ) ) . '</b>' );
							} elseif ( 0 === $days ) {
								echo '<b>' . esc_html__( 'Today', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</b>';
							} else {
								/* translators: %s: number of days left. */
								printf( esc_html( _n( 'in %s day', 'in %s days', $days, 'devdiggers-buy-now-pay-later-for-woocommerce' ) ), '<b>' . esc_html( number_format_i18n( $days ) ) . '</b>' );
							}
							?>
						</span>
					<?php endif; ?>
				</div>
				<strong class="ddwcpp-figure"><?php echo wp_kses_post( wc_price( $next_pay ) ); ?></strong>
				<p class="ddwcpp-muted">
					<?php
					echo esc_html(
						$next['due_date']
							/* translators: %s: due date. */
							? sprintf( __( 'Due %s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), date_i18n( wc_date_format(), strtotime( $next['due_date'] ) ) )
							: __( 'No due date. Pay whenever you like.', 'devdiggers-buy-now-pay-later-for-woocommerce' )
					);
					echo ' &middot; ' . esc_html( $next['plan_title'] );
					?>
				</p>
				<?php DDWCPP_Plan_Schedule_Template::ddwcpp_render_pay_button( [ $next ] ); ?>
			</section>
			<?php
		}

		/**
		 * Installments as a list with calendar date tiles.
		 *
		 * @param array $rows Installments.
		 * @return void
		 */
		protected function ddwcpp_render_installments_list( $rows ) {
			?>
			<ul class="ddwcpp-list">
				<?php
				foreach ( $rows as $row ) :
					$status = DDWCPP_Plan_Schedule_Template::ddwcpp_get_row_status( $row, false );
					$order  = $row['order_id'] ? wc_get_order( $row['order_id'] ) : null;
					$time   = $row['due_date'] ? strtotime( $row['due_date'] ) : 0;
					?>
					<li class="ddwcpp-list-item ddwcpp-list-<?php echo esc_attr( $status ); ?>">
						<span class="ddwcpp-date-tile" aria-hidden="true">
							<?php if ( $time ) : ?>
								<small><?php echo esc_html( date_i18n( 'M', $time ) ); ?></small>
								<b><?php echo esc_html( date_i18n( 'j', $time ) ); ?></b>
							<?php else : ?>
								<b>&ndash;</b>
							<?php endif; ?>
						</span>
						<span class="ddwcpp-list-body">
							<strong><?php echo esc_html( $row['plan_title'] ); ?></strong>
							<span class="ddwcpp-cell-meta">
								<?php
								/* translators: %1$d: payment number, %2$d: number of payments. */
								printf( esc_html__( 'Payment %1$d of %2$d', 'devdiggers-buy-now-pay-later-for-woocommerce' ), absint( $row['sequence'] ), absint( $row['total_count'] ) );

								if ( $order ) {
									echo ' &middot; <a href="' . esc_url( ddwcpp_get_account_url( 'plans/' . $order->get_id() ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
								}
								?>
							</span>
						</span>
						<span class="ddwcpp-list-end">
							<strong class="ddwcpp-amount"><?php echo wp_kses_post( wc_price( DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ) ) ); ?></strong>
							<?php if ( 'overdue' === $status ) : ?>
								<span class="ddwcpp-badge ddwcpp-badge-overdue"><?php echo esc_html( ddwcpp_get_translation( 'overdue' ) ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		}

		/**
		 * Every order paid with a plan, one card each with a segment per installment.
		 *
		 * @param int $user_id User ID.
		 * @return void
		 */
		protected function ddwcpp_render_plans( $user_id ) {
			$plans = array_filter(
				$this->installments_helper->ddwcpp_get_plans_summary( $user_id ),
				function ( $plan ) {
					// Order plans, plus one-off charges while they are still owed; paid charges live in History.
					return $plan['count'] > 0 && ( $plan['order_id'] || 'pending' === $plan['status'] );
				}
			);

			if ( ! $plans ) {
				?>
				<div class="ddwcpp-empty">
					<?php $this->ddwcpp_print_icon( 'bag' ); ?>
					<h3><?php esc_html_e( 'No payment plans yet', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'Choose Pay Later at checkout and your payment plan will show up here.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></p>
					<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Start shopping', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
				</div>
				<?php
				return;
			}
			?>
			<div class="ddwcpp-plan-cards">
				<?php
				foreach ( $plans as $plan ) :
					$order  = $plan['order_id'] ? wc_get_order( $plan['order_id'] ) : null;
					$status = $plan['overdue'] ? 'overdue' : $plan['status'];
					$tag    = $order ? 'a' : 'div';
					?>
					<<?php echo esc_attr( $tag ); ?> class="ddwcpp-plan-card ddwcpp-plan-<?php echo esc_attr( $status ); ?>"<?php echo $order ? ' href="' . esc_url( ddwcpp_get_account_url( 'plans/' . $plan['order_id'] ) ) . '"' : ''; ?>>
						<span class="ddwcpp-plan-card-head">
							<span>
								<strong><?php echo esc_html( $plan['plan_title'] ); ?></strong>
								<?php if ( $order ) : ?>
									<span class="ddwcpp-cell-meta">
										<?php
										/* translators: %1$s: order number, %2$s: date. */
										printf( esc_html__( 'Order #%1$s, %2$s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), esc_html( $order->get_order_number() ), esc_html( wc_format_datetime( $order->get_date_created() ) ) );
										?>
									</span>
								<?php endif; ?>
							</span>
							<span class="ddwcpp-badge ddwcpp-badge-<?php echo esc_attr( $status ); ?>"><?php echo 'paid' === $status ? esc_html__( 'Completed', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html( ddwcpp_get_translation( 'pending' === $status ? 'active' : $status ) ); ?></span>
						</span>
						<span class="ddwcpp-segments" role="img" aria-label="<?php /* translators: %1$d: payments made, %2$d: payments in total. */ echo esc_attr( sprintf( __( '%1$d of %2$d payments made', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $plan['paid_count'], $plan['count'] ) ); ?>">
							<?php
							for ( $i = 0; $i < $plan['count'] + $plan['cancelled']; $i++ ) {
								if ( $i >= $plan['count'] ) {
									$segment = 'cancelled';
								} else {
									$segment = $i < $plan['paid_count'] ? 'paid' : ( $i === (int) $plan['paid_count'] ? ( $plan['overdue'] ? 'overdue' : 'next' ) : 'todo' );
								}
								echo '<i class="is-' . esc_attr( $segment ) . '"></i>';
							}
							?>
						</span>
						<span class="ddwcpp-plan-card-foot">
							<span>
								<span class="ddwcpp-eyebrow"><?php esc_html_e( 'Left to pay', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
								<strong class="ddwcpp-amount"><?php echo wp_kses_post( wc_price( $plan['remaining'] ) ); ?></strong>
							</span>
							<span class="ddwcpp-plan-card-next">
								<?php
								if ( 'pending' === $plan['status'] && $plan['next_amount'] > 0 ) {
									/* translators: %1$s: amount, %2$s: date. */
									printf( esc_html__( 'Next %1$s on %2$s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $plan['next_amount'] ) ), esc_html( $plan['next_due'] ? date_i18n( 'M j', strtotime( $plan['next_due'] ) ) : __( 'any day', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ) );
								} elseif ( 'cancelled' === $plan['status'] ) {
									/* translators: %d: number of payments made before the plan ended. */
									printf( esc_html( _n( 'Ended after %d payment', 'Ended after %d payments', $plan['paid_count'], 'devdiggers-buy-now-pay-later-for-woocommerce' ) ), absint( $plan['paid_count'] ) );
								} else {
									/* translators: %d: number of payments. */
									printf( esc_html( _n( '%d payment made', 'All %d payments made', $plan['count'], 'devdiggers-buy-now-pay-later-for-woocommerce' ) ), absint( $plan['count'] ) );
								}
								$this->ddwcpp_print_icon( 'chevron' );
								?>
							</span>
						</span>
					</<?php echo esc_attr( $tag ); ?>>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * One order's schedule.
		 *
		 * @param int $order_id Order ID.
		 * @param int $user_id  User ID.
		 * @return void
		 */
		protected function ddwcpp_render_plan( $order_id, $user_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order || $order->get_customer_id() !== $user_id || ! DDWCPP_Plan_Schedule_Template::ddwcpp_get_rows( $order_id ) ) {
				wc_print_notice( esc_html__( 'Payment plan not found.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'error' );
				return;
			}
			?>
			<div class="ddwcpp-back">
				<a href="<?php echo esc_url( ddwcpp_get_account_url( 'plans' ) ); ?>">&larr; <?php esc_html_e( 'All payment plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
				<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
					<?php
					/* translators: %s: order number. */
					printf( esc_html__( 'View order #%s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), esc_html( $order->get_order_number() ) );
					?>
				</a>
			</div>
			<?php
			new DDWCPP_Plan_Schedule_Template( $order_id );
		}

		/**
		 * The panel for the customer's current step.
		 *
		 * @param array $account Account data.
		 * @return void
		 */
		protected function ddwcpp_render_status_panel( $account ) {
			$status = $account['status'];
			$plans  = DDWCPP_Plans_Helper::ddwcpp_get_active_plans();
			?>
			<div class="ddwcpp-onboard">
				<div class="ddwcpp-onboard-head">
					<span class="ddwcpp-welcome-mark" aria-hidden="true"><?php echo wp_kses( str_replace( [ 'width="32"', 'height="32"' ], [ 'width="44"', 'height="44"' ], ddwcpp_get_brand_mark() ), ddwcpp_get_allowed_html() ); ?></span>
					<div>
						<h2><?php esc_html_e( 'Shop now, pay over time', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Your own credit line with this store. No bank card, no extra app.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></p>
					</div>
				</div>
				<ul class="ddwcpp-benefits">
					<li><?php $this->ddwcpp_print_icon( 'split' ); ?><span><?php esc_html_e( 'Split purchases into easy installments', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span></li>
					<li><?php $this->ddwcpp_print_icon( 'card' ); ?><span><?php esc_html_e( 'No card needed at checkout', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span></li>
					<li><?php $this->ddwcpp_print_icon( 'bell' ); ?><span><?php esc_html_e( 'Reminders before every due date', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span></li>
				</ul>
				<?php if ( $plans ) : ?>
					<div class="ddwcpp-chips">
						<span class="ddwcpp-chips-label"><?php esc_html_e( 'Plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
						<?php foreach ( $plans as $plan ) : ?>
							<span class="ddwcpp-chip" title="<?php echo esc_attr( DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan ) ); ?>"><?php echo esc_html( $plan['title'] ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php
				$tones = [
					'eligible' => [ 'spark', 'is-good' ],
					'pending'  => [ 'clock', 'is-wait' ],
					'rejected' => [ 'alert', 'is-bad' ],
				];
				$tone  = isset( $tones[ $status ] ) ? $tones[ $status ] : [ 'spark', '' ];
				?>
				<div class="ddwcpp-onboard-status <?php echo esc_attr( $tone[1] ); ?>">
				<span class="ddwcpp-onboard-status-icon" aria-hidden="true"><?php $this->ddwcpp_print_icon( $tone[0] ); ?></span>
				<div class="ddwcpp-onboard-status-body">
				<?php
				switch ( $status ) {
					case 'eligible':
						?>
						<h3><?php esc_html_e( 'You are approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
						<p>
							<?php
							/* translators: %s: credit limit. */
							printf( esc_html__( 'Activate Pay Later to shop now and pay later, with a credit limit of %s.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $account['limit'] ) ) );
							?>
						</p>
						<form method="post">
							<?php $this->ddwcpp_form_fields( 'apply' ); ?>
							<button type="submit" class="button alt"><?php esc_html_e( 'Activate Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></button>
						</form>
						<?php
						break;

					case 'pending':
						?>
						<h3><?php esc_html_e( 'Application under review', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'We are reviewing your application and will email you once it has been decided.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></p>
						<?php
						break;

					case 'rejected':
						?>
						<h3><?php esc_html_e( 'Application not approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
						<p><?php esc_html_e( 'Your Pay Later application was not approved. Contact us if you have any questions.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></p>
						<?php
						break;

					default:
						?>
						<h3><?php esc_html_e( 'Apply in a minute', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h3>
						<p>
							<?php
							/* translators: %s: credit limit. */
							printf( esc_html__( 'Get a spending limit of up to %s and pay for your orders in installments.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $account['limit'] ) ) );
							?>
						</p>
						<form method="post">
							<?php $this->ddwcpp_form_fields( 'apply' ); ?>
							<button type="submit" class="button alt"><?php esc_html_e( 'Apply for Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></button>
						</form>
						<?php
				}
				?>
				</div>
				</div>
			</div>
			<?php
		}

		/**
		 * The customer's transactions.
		 *
		 * @param int $user_id      User ID.
		 * @param int $current_page Page.
		 * @return void
		 */
		protected function ddwcpp_render_transactions( $user_id, $current_page ) {
			$helper       = new DDWCPP_Transactions_Helper();
			$args         = [ 'user_id' => $user_id ];
			$total        = $helper->ddwcpp_get_transactions_count( $args );
			$transactions = $helper->ddwcpp_get_transactions(
				array_merge(
					$args,
					[
						'per_page' => self::PER_PAGE,
						'offset'   => ( $current_page - 1 ) * self::PER_PAGE,
					]
				)
			);
			?>

			<div class="ddwcpp-table-wrapper">
			<table class="shop_table shop_table_responsive ddwcpp-table ddwcpp-transactions-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Description', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Order', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $transactions ) ) : ?>
						<tr><td colspan="4" class="ddwcpp-table-empty"><?php esc_html_e( 'No activity yet.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></td></tr>
					<?php endif; ?>
					<?php
					foreach ( $transactions as $transaction ) :
						$order = $transaction['order_id'] ? wc_get_order( $transaction['order_id'] ) : null;
						?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Date', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>"><a href="<?php echo esc_url( ddwcpp_get_account_url( 'view/' . $transaction['id'] ) ); ?>"><?php echo esc_html( date_i18n( wc_date_format(), strtotime( $transaction['date'] ) ) ); ?></a></td>
							<td data-title="<?php esc_attr_e( 'Description', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>">
								<strong><?php echo esc_html( ddwcpp_get_translation( $transaction['reference'] ) ); ?></strong>
								<?php if ( $transaction['note'] ) : ?>
									<span class="ddwcpp-cell-meta"><?php echo esc_html( $transaction['note'] ); ?></span>
								<?php endif; ?>
							</td>
							<td data-title="<?php esc_attr_e( 'Order', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>">
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
								<?php else : ?>
									<span class="ddwcpp-muted">&ndash;</span>
								<?php endif; ?>
							</td>
							<td data-title="<?php esc_attr_e( 'Amount', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>" class="<?php echo 'credit' === $transaction['type'] ? 'ddwcpp-positive' : 'ddwcpp-negative'; ?>">
								<?php echo ( 'credit' === $transaction['type'] ? '+' : '&minus;' ) . wp_kses_post( wc_price( $transaction['amount'], [ 'currency' => $order ? $order->get_currency() : '' ] ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php if ( $total > self::PER_PAGE ) : ?>
				<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
					<?php if ( $current_page > 1 ) : ?>
						<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( ddwcpp_get_account_url( 'history/' . ( $current_page - 1 ) ) ); ?>"><?php esc_html_e( 'Previous', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
					<?php endif; ?>
					<?php if ( ceil( $total / self::PER_PAGE ) > $current_page ) : ?>
						<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( ddwcpp_get_account_url( 'history/' . ( $current_page + 1 ) ) ); ?>"><?php esc_html_e( 'Next', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php
		}
	}
}
