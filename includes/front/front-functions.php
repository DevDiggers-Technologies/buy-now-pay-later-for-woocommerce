<?php
/**
 * Front end callbacks.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Front;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;
use DDWCPostPaid\Templates\Front\MyAccount\DDWCPP_Postpaid_Template;
use DDWCPostPaid\Templates\Admin\Transactions\DDWCPP_Transaction_View_Template;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Front_Functions' ) ) {
	/**
	 * Front functions class
	 */
	class DDWCPP_Front_Functions {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Users Helper Variable
		 *
		 * @var DDWCPP_Users_Helper
		 */
		protected $users_helper;

		/**
		 * Whether a message was printed on this page, so the explainer dialog is needed.
		 *
		 * @var bool
		 */
		protected $ddwcpp_print_dialog = false;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->users_helper         = new DDWCPP_Users_Helper( $ddwcpp_configuration );
		}

		/**
		 * Whether the current user can see PostPaid at all.
		 *
		 * @return bool
		 */
		protected function ddwcpp_user_has_access() {
			return is_user_logged_in();
		}

		/**
		 * Add PostPaid Menu in my account page function
		 *
		 * @param array $items Menu items.
		 * @return array
		 */
		public function ddwcpp_add_woocommerce_menu( $items ) {
			if ( ! $this->ddwcpp_user_has_access() ) {
				return $items;
			}

			$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : '';
			unset( $items['customer-logout'] );

			$items[ DDWCPP_POSTPAID_ENDPOINT ] = $this->ddwcpp_configuration['menu_title'];

			if ( $logout ) {
				$items['customer-logout'] = $logout;
			}

			return $items;
		}

		/**
		 * Register the endpoint with WooCommerce, so is_wc_endpoint_url() and the account
		 * endpoint title know about it.
		 *
		 * @param array $vars WooCommerce query vars, key => endpoint slug.
		 * @return array
		 */
		public function ddwcpp_add_query_vars( $vars ) {
			$vars[ DDWCPP_POSTPAID_ENDPOINT ] = DDWCPP_POSTPAID_ENDPOINT;
			return $vars;
		}

		/**
		 * Hide the theme sidebar on the PostPaid section unless the store wants it.
		 *
		 * @param array $sidebars_widgets Widgets per sidebar.
		 * @return array
		 */
		public function ddwcpp_hide_sidebar_on_endpoint( $sidebars_widgets ) {
			if ( ! is_admin() && empty( $this->ddwcpp_configuration['sidebar_widgets'] ) && did_action( 'wp' ) && is_wc_endpoint_url( DDWCPP_POSTPAID_ENDPOINT ) ) {
				return [ false ];
			}

			return $sidebars_widgets;
		}

		/**
		 * Endpoint title.
		 *
		 * @return string
		 */
		public function ddwcpp_change_endpoint_title() {
			return $this->ddwcpp_configuration['menu_title'];
		}

		/**
		 * My Account PostPaid screen, routed by the endpoint value.
		 *
		 * Values: '' overview, 'plans', 'plans/<order id>', 'history', 'history/<page>', 'view/<transaction id>'.
		 *
		 * @param string $value Endpoint value.
		 * @return void
		 */
		public function ddwcpp_add_postpaid_content_on_my_account_page( $value = '' ) {
			if ( ! $this->ddwcpp_user_has_access() ) {
				wc_print_notice( esc_html__( 'Pay Later is not available for your account.', 'buy-now-pay-later-for-woocommerce' ), 'notice' );
				return;
			}

			$parts   = explode( '/', trim( (string) $value, '/' ) );
			$section = sanitize_key( $parts[0] );
			$arg     = isset( $parts[1] ) ? absint( $parts[1] ) : 0;

			if ( 'view' === $section ) {
				$transaction = ( new DDWCPP_Transactions_Helper() )->ddwcpp_get_transaction( $arg );

				// Customers only ever see their own transactions.
				if ( $transaction && get_current_user_id() === (int) $transaction['user_id'] ) {
					new DDWCPP_Transaction_View_Template( $transaction );
				} else {
					wc_print_notice( esc_html__( 'Invalid transaction.', 'buy-now-pay-later-for-woocommerce' ), 'error' );
				}

				return;
			}

			// Links from before 2.0 paginated the overview with a bare number.
			if ( is_numeric( $section ) ) {
				$arg     = absint( $section );
				$section = 'history';
			}

			new DDWCPP_Postpaid_Template( $this->ddwcpp_configuration, in_array( $section, [ 'plans', 'history' ], true ) ? $section : 'overview', $arg );
		}

		/**
		 * Plan to advertise: the active plan with the most installments.
		 *
		 * @return array|null
		 */
		public function ddwcpp_get_message_plan() {
			$plans = DDWCPP_Plans_Helper::ddwcpp_get_active_plans();

			uasort(
				$plans,
				function ( $a, $b ) {
					return (int) $b['count'] - (int) $a['count'];
				}
			);

			return $plans ? reset( $plans ) : null;
		}

		/**
		 * Whether the installment message should show to the current visitor.
		 *
		 * @return bool
		 */
		protected function ddwcpp_message_is_visible() {
			return ! empty( $this->ddwcpp_configuration['message_enabled'] );
		}

		/**
		 * "or 4 payments of $25.00 every 2 weeks" message for an amount.
		 *
		 * @param float $amount     Price.
		 * @param int   $product_id Product, 0 for the cart.
		 * @return string HTML, empty when nothing fits.
		 */
		public function ddwcpp_get_message_html( $amount, $product_id = 0 ) {
			if ( $amount <= 0 || ! $this->ddwcpp_message_is_visible() ) {
				return '';
			}

			$plan = $this->ddwcpp_get_message_plan();

			if ( ! $plan ) {
				return '';
			}

			$single_text = 1 === (int) $plan['count'];
			$text        = $this->ddwcpp_configuration[ $single_text ? 'message_text_single' : 'message_text' ];
			$text        = $text ? $text : ddwcpp_get_default_message_text( $single_text );

			$total    = $amount;
			$schedule = DDWCPP_Plans_Helper::ddwcpp_build_schedule( $plan, $total );
			$single   = 1 === (int) $plan['count'];
			$data     = [
				'count' => (int) $plan['count'],
			];

			$html = strtr(
				esc_html( $text ),
				[
					'{count}'    => '<strong>' . absint( $plan['count'] ) . '</strong>',
					'{amount}'   => '<strong class="ddwcpp-message-amount">' . wc_price( $single ? $total : end( $schedule )['amount'] ) . '</strong>',
					'{interval}' => esc_html( $single ? DDWCPP_Plans_Helper::ddwcpp_get_term_label( $plan ) : DDWCPP_Plans_Helper::ddwcpp_get_interval_label( $plan ) ),
					'{plan}'     => esc_html( $plan['title'] ),
					'{total}'    => wc_price( $total ),
				]
			);

			$this->ddwcpp_print_dialog = true;

			return '<div class="ddwcpp-message" data-ddwcpp-plan="' . esc_attr( wp_json_encode( $data ) ) . '">'
				. '<span class="ddwcpp-message-mark" aria-hidden="true">' . ddwcpp_get_brand_mark() . '</span>'
				. '<span class="ddwcpp-message-text">' . $html . '</span>'
				. ' <button type="button" class="ddwcpp-message-more">' . esc_html__( 'Learn more', 'buy-now-pay-later-for-woocommerce' ) . '</button>'
				. '</div>';
		}

		/**
		 * Message under the price on the product page.
		 *
		 * @return void
		 */
		public function ddwcpp_product_message() {
			global $product;

			if ( $product instanceof \WC_Product ) {
				echo wp_kses( $this->ddwcpp_get_message_html( (float) wc_get_price_to_display( $product ), $product->get_id() ), ddwcpp_get_allowed_html() );
			}
		}

		/**
		 * Message under the price in product lists.
		 *
		 * @return void
		 */
		public function ddwcpp_loop_message() {
			global $product;

			if ( $product instanceof \WC_Product ) {
				echo wp_kses( str_replace( 'ddwcpp-message"', 'ddwcpp-message ddwcpp-message-compact"', $this->ddwcpp_get_message_html( (float) wc_get_price_to_display( $product ), $product->get_id() ) ), ddwcpp_get_allowed_html() );
			}
		}

		/**
		 * Message under the cart total.
		 *
		 * @return void
		 */
		public function ddwcpp_cart_message() {
			if ( ! WC()->cart ) {
				return;
			}

			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['ddwcpp_dues_amount'] ) ) {
					return;
				}
			}

			$html = $this->ddwcpp_get_message_html( (float) WC()->cart->get_total( 'edit' ) );

			if ( $html ) {
				echo '<tr class="ddwcpp-cart-message"><td colspan="2">' . wp_kses( $html, ddwcpp_get_allowed_html() ) . '</td></tr>';
			}
		}

		/**
		 * [ddwcpp_installments_message] shortcode, for page builders and custom templates.
		 *
		 * @param array $atts { product_id, amount }.
		 * @return string
		 */
		public function ddwcpp_message_shortcode( $atts ) {
			$atts = shortcode_atts(
				[
					'product_id' => 0,
					'amount'     => 0,
				],
				$atts,
				'ddwcpp_installments_message'
			);

			$product = $atts['product_id'] ? wc_get_product( absint( $atts['product_id'] ) ) : ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ? $GLOBALS['product'] : null );
			$amount  = (float) $atts['amount'] ? (float) $atts['amount'] : ( $product ? (float) wc_get_price_to_display( $product ) : 0 );

			return $this->ddwcpp_get_message_html( $amount, $product ? $product->get_id() : 0 );
		}

		/**
		 * "How it works" dialog the Learn more buttons open.
		 *
		 * @return void
		 */
		public function ddwcpp_print_message_dialog() {
			if ( ! $this->ddwcpp_print_dialog ) {
				return;
			}

			$plans = DDWCPP_Plans_Helper::ddwcpp_get_active_plans();
			$steps = ddwcpp_get_default_dialog_steps();
			$title = __( 'Buy now, pay later', 'buy-now-pay-later-for-woocommerce' );
			$note  = __( 'Approval and credit limits apply.', 'buy-now-pay-later-for-woocommerce' );
			?>
			<dialog class="ddwcpp-dialog" id="ddwcpp-message-dialog" aria-labelledby="ddwcpp-message-dialog-title">
				<form method="dialog" class="ddwcpp-dialog-inner">
					<button type="submit" class="ddwcpp-dialog-close" aria-label="<?php esc_attr_e( 'Close', 'buy-now-pay-later-for-woocommerce' ); ?>">&times;</button>
					<span class="ddwcpp-dialog-mark" aria-hidden="true"><?php echo wp_kses( ddwcpp_get_brand_mark(), ddwcpp_get_allowed_html() ); ?></span>
					<h2 id="ddwcpp-message-dialog-title"><?php echo esc_html( $title ); ?></h2>
					<ol class="ddwcpp-dialog-steps">
						<?php foreach ( $steps as $step ) : ?>
							<li><?php echo esc_html( $step ); ?></li>
						<?php endforeach; ?>
					</ol>
					<?php if ( $plans ) : ?>
						<h3><?php esc_html_e( 'Ways to pay', 'buy-now-pay-later-for-woocommerce' ); ?></h3>
						<ul class="ddwcpp-dialog-plans">
							<?php foreach ( $plans as $plan ) : ?>
								<li>
									<strong><?php echo esc_html( $plan['title'] ); ?></strong>
									<span><?php echo esc_html( DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="ddwcpp-dialog-note">
						<?php
						echo esc_html( $note ) . ' ';

						if ( is_user_logged_in() ) {
							echo '<a href="' . esc_url( ddwcpp_get_account_url() ) . '">' . esc_html__( 'See your Pay Later account', 'buy-now-pay-later-for-woocommerce' ) . '</a>';
						} else {
							echo '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'Log in or create an account to apply', 'buy-now-pay-later-for-woocommerce' ) . '</a>';
						}
						?>
					</p>
				</form>
			</dialog>
			<?php
		}

		/**
		 * Front scripts enqueue function
		 *
		 * @return void
		 */
		public function ddwcpp_front_scripts() {
			$account  = is_account_page();
			$messages = ! empty( $this->ddwcpp_configuration['message_enabled'] ) && ( is_woocommerce() || is_cart() || is_front_page() );

			if ( $account || $messages || is_checkout() ) {
				wp_enqueue_style( 'ddwcpp-front-style', DDWCPP_PLUGIN_URL . 'assets/css/front.css', [], filemtime( DDWCPP_PLUGIN_FILE . 'assets/css/front.css' ) );
			}

			if ( $account || $messages ) {
				wp_enqueue_script( 'ddwcpp-front-script', DDWCPP_PLUGIN_URL . 'assets/js/front.js', [], filemtime( DDWCPP_PLUGIN_FILE . 'assets/js/front.js' ), true );
				wp_localize_script(
					'ddwcpp-front-script',
					'ddwcppFrontObj',
					[
						'currency'   => [
							'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
							'format'   => html_entity_decode( get_woocommerce_price_format() ),
							'decimals' => wc_get_price_decimals(),
							'decimal'  => wc_get_price_decimal_separator(),
							'thousand' => wc_get_price_thousand_separator(),
						],
						// Section links under this URL swap in place instead of reloading.
						'accountUrl' => ddwcpp_get_account_url(),
					]
				);
			}
		}

		/**
		 * Handle the activation and the repayment forms.
		 *
		 * @return void
		 */
		public function ddwcpp_handle_form_submissions() {
			$user_id = get_current_user_id();

			if ( ! $user_id || empty( $_POST['ddwcpp_action'] ) || ! $this->ddwcpp_user_has_access() ) {
				return;
			}

			if ( ! isset( $_POST['ddwcpp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddwcpp_nonce'] ) ), 'ddwcpp_front_action' ) ) {
				wc_add_notice( esc_html__( 'Your session expired. Please try again.', 'buy-now-pay-later-for-woocommerce' ), 'error' );
				return;
			}

			$action = sanitize_key( wp_unslash( $_POST['ddwcpp_action'] ) );
			$status = $this->users_helper->ddwcpp_get_verification_status( $user_id );

			switch ( $action ) {
				case 'apply':
					$this->ddwcpp_handle_apply( $user_id, $status );
					break;

				case 'repay':
					// An installment amount from the plans tab, empty for the full dues.
					$amount = isset( $_POST['ddwcpp_amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['ddwcpp_amount'] ) ) ) : '';
					$this->ddwcpp_handle_repayment( $user_id, $amount );
					break;
			}

			wp_safe_redirect( ddwcpp_get_account_url() );
			exit;
		}

		/**
		 * Apply for PostPaid, or activate an approved credit line.
		 *
		 * @param int    $user_id User ID.
		 * @param string $status  Current verification status.
		 * @return void
		 */
		protected function ddwcpp_handle_apply( $user_id, $status ) {
			if ( 'eligible' === $status ) {
				$this->users_helper->ddwcpp_allot( $user_id );
				wc_add_notice( esc_html__( 'Pay Later is active. You can now choose it at checkout.', 'buy-now-pay-later-for-woocommerce' ) );
				return;
			}

			if ( 'manual' === $this->ddwcpp_configuration['verification_mode'] && 'not_applied' === $status ) {
				update_user_meta( $user_id, '_ddwcpp_verification_status', 'pending' );
				update_user_meta( $user_id, '_ddwcpp_applied_on', current_time( 'mysql' ) );
				$this->ddwcpp_notify_admin_of_application( $user_id );
				wc_add_notice( esc_html__( 'Your application was sent. We will email you once it has been reviewed.', 'buy-now-pay-later-for-woocommerce' ) );
			}
		}

		/**
		 * Put the chosen repayment in a fresh cart and go to checkout.
		 *
		 * @param int    $user_id User ID.
		 * @param string $amount  Requested amount, empty for the full dues.
		 * @return void
		 */
		protected function ddwcpp_handle_repayment( $user_id, $amount ) {
			$dues = $this->users_helper->ddwcpp_get_dues( $user_id );

			if ( $dues <= 0 ) {
				wc_add_notice( esc_html__( 'You have no dues to pay.', 'buy-now-pay-later-for-woocommerce' ), 'notice' );
				return;
			}

			$amount = '' === $amount ? $dues : round( (float) $amount, wc_get_price_decimals() );

			// Installments are paid in order; any other amount clears the full dues.
			if ( ! in_array( $amount, $this->ddwcpp_get_settle_amounts( $user_id ), true ) ) {
				$amount = $dues;
			}

			WC()->cart->empty_cart();
			WC()->cart->add_to_cart( ddwcpp_get_dues_product_id(), 1, 0, [], [ 'ddwcpp_dues_amount' => wc_format_decimal( $amount, wc_get_price_decimals() ) ] );

			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		/**
		 * Amounts that settle the pending installments in order: the first, the first two, and so on.
		 *
		 * @param int $user_id User ID.
		 * @return float[]
		 */
		protected function ddwcpp_get_settle_amounts( $user_id ) {
			$amounts = [];
			$sum     = 0.0;

			foreach ( ( new DDWCPP_Installments_Helper() )->ddwcpp_get_pending( $user_id ) as $row ) {
				$sum      += DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row );
				$amounts[] = round( $sum, wc_get_price_decimals() );
			}

			return $amounts;
		}

		/**
		 * Tell the store about a new application.
		 *
		 * @param int $user_id User ID.
		 * @return void
		 */
		protected function ddwcpp_notify_admin_of_application( $user_id ) {
			\DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_send(
				'new_application',
				$user_id,
				[
					'review_url' => esc_url(
						ddwcpp_get_dashboard_url(
							[
								'menu'   => 'customers',
								'status' => 'pending',
							]
						)
					),
				]
			);
		}

		/**
		 * Keep the dues product and other products in separate carts.
		 *
		 * @param bool $passed     Validation result.
		 * @param int  $product_id Product being added.
		 * @return bool
		 */
		public function ddwcpp_validate_add_to_cart( $passed, $product_id ) {
			$dues_product_id = ddwcpp_get_dues_product_id();

			if ( (int) $product_id === $dues_product_id ) {
				// Only the repayment form adds it, with an amount.
				return $passed && doing_action( 'wp_loaded' );
			}

			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === $dues_product_id ) {
					wc_add_notice( esc_html__( 'Your cart holds a Pay Later repayment. Complete or remove it before adding products.', 'buy-now-pay-later-for-woocommerce' ), 'error' );
					return false;
				}
			}

			return $passed;
		}

		/**
		 * Carry the repayment amount through the cart session.
		 *
		 * @param array $item   Cart item.
		 * @param array $values Session values.
		 * @return array
		 */
		public function ddwcpp_restore_dues_cart_item( $item, $values ) {
			if ( isset( $values['ddwcpp_dues_amount'] ) ) {
				$item['ddwcpp_dues_amount'] = $values['ddwcpp_dues_amount'];
			}

			return $item;
		}

		/**
		 * Price the dues item at the customer's own amount, never above what they owe.
		 *
		 * @param \WC_Cart $cart Cart.
		 * @return void
		 */
		public function ddwcpp_set_dues_price( $cart ) {
			$dues_product_id = ddwcpp_get_dues_product_id();

			foreach ( $cart->get_cart() as $key => $item ) {
				if ( (int) $item['product_id'] !== $dues_product_id ) {
					continue;
				}

				$amount = isset( $item['ddwcpp_dues_amount'] ) ? min( (float) $item['ddwcpp_dues_amount'], $this->users_helper->ddwcpp_get_dues( get_current_user_id() ) ) : 0;

				if ( $amount <= 0 ) {
					$cart->remove_cart_item( $key );
					continue;
				}

				$item['data']->set_price( $amount );
				$item['data']->set_regular_price( $amount );
			}
		}

		/**
		 * Lock the dues line to a quantity of one.
		 *
		 * @param string $quantity_html Quantity field.
		 * @param string $cart_item_key Cart item key.
		 * @param array  $cart_item     Cart item.
		 * @return string
		 */
		public function ddwcpp_lock_dues_quantity( $quantity_html, $cart_item_key, $cart_item ) {
			return isset( $cart_item['ddwcpp_dues_amount'] ) ? '1' : $quantity_html;
		}

		/**
		 * Repayments need real money, so cash on delivery and cheque are hidden for them.
		 *
		 * @param array $gateways Available gateways.
		 * @return array
		 */
		public function ddwcpp_filter_gateways_for_dues( $gateways ) {
			if ( ! WC()->cart || is_admin() ) {
				return $gateways;
			}

			foreach ( WC()->cart->get_cart() as $item ) {
				if ( isset( $item['ddwcpp_dues_amount'] ) ) {
					foreach ( apply_filters( 'ddwcpp_gateways_excluded_for_dues', [ 'ddwcpp_postpaid', 'cod', 'cheque' ] ) as $gateway_id ) {
						unset( $gateways[ $gateway_id ] );
					}
					break;
				}
			}

			return $gateways;
		}

		/**
		 * Explain at checkout why an active PostPaid customer does not see the method.
		 *
		 * @return void
		 */
		public function ddwcpp_explain_unavailable_postpaid() {
			$user_id = get_current_user_id();

			if ( ! $user_id || ! $this->users_helper->ddwcpp_is_allotted( $user_id ) ) {
				return;
			}

			$result = $this->users_helper->ddwcpp_can_use( $user_id, (float) WC()->cart->get_total( 'edit' ) );

			if ( is_wp_error( $result ) && in_array( $result->get_error_code(), [ 'ddwcpp_overdue', 'ddwcpp_balance', 'ddwcpp_min', 'ddwcpp_max' ], true ) ) {
				echo '<div class="ddwcpp-checkout-unavailable woocommerce-info">' . wp_kses_post( $result->get_error_message() ) . '</div>';
			}
		}
	}
}
