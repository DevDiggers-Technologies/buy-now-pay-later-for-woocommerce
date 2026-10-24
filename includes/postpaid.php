<?php
/**
 * PostPaid payment gateway.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes;

use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;
use DDWCPostPaid\Templates\Front\DDWCPP_Plan_Schedule_Template;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_PostPaid' ) ) {
	/**
	 * PostPaid gateway class
	 */
	class DDWCPP_PostPaid extends \WC_Payment_Gateway {
		/**
		 * Instructions shown on the thank you page and emails.
		 *
		 * @var string
		 */
		public $instructions;

		/**
		 * Shipping methods the gateway is enabled for.
		 *
		 * @var array
		 */
		public $enable_for_methods;

		/**
		 * Whether the gateway is enabled for virtual orders.
		 *
		 * @var bool
		 */
		public $enable_for_virtual;

		/**
		 * Users Helper Variable
		 *
		 * @var DDWCPP_Users_Helper
		 */
		protected $users_helper;

		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			global $ddwcpp_configuration;

			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->users_helper         = new DDWCPP_Users_Helper( $ddwcpp_configuration );

			$this->id                 = 'ddwcpp_postpaid';
			$this->icon               = apply_filters( 'ddwcpp_gateway_icon', '' );
			$this->method_title       = esc_html__( 'Pay Later', 'buy-now-pay-later-for-woocommerce' );
			$this->method_description = esc_html__( 'Let approved customers buy now and pay later in installments or on payment terms, on a store credit line you control.', 'buy-now-pay-later-for-woocommerce' );
			$this->has_fields         = true;
			$this->supports           = [ 'products', 'refunds' ];

			$this->init_form_fields();
			$this->init_settings();

			$this->title              = $this->get_option( 'title' );
			$this->description        = $this->get_option( 'description' );
			$this->instructions       = $this->get_option( 'instructions' );
			$this->enable_for_methods = (array) $this->get_option( 'enable_for_methods', [] );
			$this->enable_for_virtual = 'yes' === $this->get_option( 'enable_for_virtual', 'yes' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
			add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'ddwcpp_add_instructions_in_thankyou_page' ] );
			add_action( 'woocommerce_order_details_after_order_table', [ $this, 'ddwcpp_add_schedule_to_order_details' ] );
			add_action( 'woocommerce_email_before_order_table', [ $this, 'ddwcpp_add_email_instructions' ], 10, 3 );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$shipping_methods = [];

			if ( is_admin() && WC()->shipping() ) {
				foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
					$shipping_methods[ $method->id ] = $method->get_method_title();
				}
			}

			$this->form_fields = [
				'enabled'            => [
					'title'   => esc_html__( 'Enable/Disable', 'buy-now-pay-later-for-woocommerce' ),
					'label'   => esc_html__( 'Enable Pay Later', 'buy-now-pay-later-for-woocommerce' ),
					'type'    => 'checkbox',
					'default' => 'yes',
				],
				'title'              => [
					'title'       => esc_html__( 'Title', 'buy-now-pay-later-for-woocommerce' ),
					'type'        => 'text',
					'description' => esc_html__( 'Payment method title that the customer will see on your checkout.', 'buy-now-pay-later-for-woocommerce' ),
					'default'     => esc_html__( 'Buy Now, Pay Later', 'buy-now-pay-later-for-woocommerce' ),
					'desc_tip'    => true,
				],
				'description'        => [
					'title'       => esc_html__( 'Description', 'buy-now-pay-later-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => esc_html__( 'Payment method description that the customer will see on your website.', 'buy-now-pay-later-for-woocommerce' ),
					'default'     => esc_html__( 'Choose a payment plan and pay over time from your account. No card needed today.', 'buy-now-pay-later-for-woocommerce' ),
					'desc_tip'    => true,
				],
				'instructions'       => [
					'title'       => esc_html__( 'Instructions', 'buy-now-pay-later-for-woocommerce' ),
					'type'        => 'textarea',
					'description' => esc_html__( 'Instructions that will be added to the thank you page and emails.', 'buy-now-pay-later-for-woocommerce' ),
					'default'     => esc_html__( 'Your order was placed with Pay Later. Your payment schedule is below, and you can pay any installment early from your account.', 'buy-now-pay-later-for-woocommerce' ),
					'desc_tip'    => true,
				],
				'enable_for_methods' => [
					'title'             => esc_html__( 'Enable for shipping methods', 'buy-now-pay-later-for-woocommerce' ),
					'type'              => 'multiselect',
					'class'             => 'wc-enhanced-select',
					'default'           => '',
					'description'       => esc_html__( 'If Pay Later is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'buy-now-pay-later-for-woocommerce' ),
					'options'           => $shipping_methods,
					'desc_tip'          => true,
					'custom_attributes' => [
						'data-placeholder' => esc_html__( 'Select shipping methods', 'buy-now-pay-later-for-woocommerce' ),
					],
				],
				'enable_for_virtual' => [
					'title'   => esc_html__( 'Accept for virtual orders', 'buy-now-pay-later-for-woocommerce' ),
					'label'   => esc_html__( 'Accept Pay Later if the order is virtual', 'buy-now-pay-later-for-woocommerce' ),
					'type'    => 'checkbox',
					'default' => 'yes',
				],
			];
		}

		/**
		 * Order being paid on the order-pay page, if any.
		 *
		 * @return \WC_Order|null
		 */
		protected function ddwcpp_get_pay_order() {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = $order_id ? wc_get_order( $order_id ) : null;

			return $order ? $order : null;
		}

		/**
		 * Whether the cart only holds the dues product, which must be paid with real money.
		 *
		 * @return bool
		 */
		protected function ddwcpp_cart_has_dues() {
			if ( ! WC()->cart ) {
				return false;
			}

			$dues_product_id = ddwcpp_get_dues_product_id();

			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === $dues_product_id ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Total the customer is paying for: the order on the order-pay page, otherwise the cart.
		 *
		 * @return float
		 */
		protected function ddwcpp_get_payable_total() {
			$order = $this->ddwcpp_get_pay_order();

			return $order ? (float) $order->get_total() : ( WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0 );
		}

		/**
		 * Plans the customer can choose for an amount: active, inside the plan's limits and
		 * covered by the customer's available credit.
		 *
		 * @param float $total   Amount.
		 * @param int   $user_id Customer.
		 * @return array
		 */
		public function ddwcpp_get_available_plans( $total, $user_id ) {
			$balance = $this->users_helper->ddwcpp_get_balance( $user_id );

			return array_filter(
				DDWCPP_Plans_Helper::ddwcpp_get_active_plans(),
				function () use ( $total, $balance ) {
					return $total <= $balance;
				}
			);
		}

		/**
		 * Check If The Gateway Is Available For Use.
		 *
		 * @return bool
		 */
		public function is_available() {
			if ( empty( $this->ddwcpp_configuration['enabled'] ) || ! parent::is_available() ) {
				return false;
			}

			// The admin payment settings screen lists the gateway without a customer.
			if ( is_admin() && ! wp_doing_ajax() ) {
				return true;
			}

			$order = $this->ddwcpp_get_pay_order();

			if ( ! $order && $this->ddwcpp_cart_has_dues() ) {
				return false;
			}

			$total = $this->ddwcpp_get_payable_total();

			if ( true !== $this->users_helper->ddwcpp_can_use( get_current_user_id(), $total ) || ! $this->ddwcpp_get_available_plans( $total, get_current_user_id() ) ) {
				return false;
			}

			return $this->ddwcpp_is_available_for_shipping( $order );
		}

		/**
		 * Respect the shipping method and virtual order settings.
		 *
		 * @param \WC_Order|null $order Order on the order-pay page.
		 * @return bool
		 */
		protected function ddwcpp_is_available_for_shipping( $order ) {
			$needs_shipping = false;

			if ( $order ) {
				foreach ( $order->get_items() as $item ) {
					$product = $item->get_product();
					if ( $product && $product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			} elseif ( WC()->cart && WC()->cart->needs_shipping() ) {
				$needs_shipping = true;
			}

			$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce hook.

			if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
				return false;
			}

			if ( empty( array_filter( $this->enable_for_methods ) ) || ! $needs_shipping ) {
				return true;
			}

			if ( $order ) {
				$chosen = array_map(
					function ( $method ) {
						return $method->get_method_id() . ':' . $method->get_instance_id();
					},
					$order->get_shipping_methods()
				);
			} else {
				$chosen = WC()->session ? array_unique( (array) WC()->session->get( 'chosen_shipping_methods' ) ) : [];
			}

			if ( 1 !== count( $chosen ) ) {
				return false;
			}

			$check_method = (string) reset( $chosen );

			foreach ( $this->enable_for_methods as $method_id ) {
				if ( 0 === strpos( $check_method, $method_id ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Description, available credit and the plan choice.
		 *
		 * @return void
		 */
		public function payment_fields() {
			if ( $this->description ) {
				echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
			}

			$notice = $this->ddwcpp_get_credit_notice();

			if ( $notice ) {
				echo '<p class="ddwcpp-checkout-credit">' . wp_kses_post( $notice ) . '</p>';
			}

			$total = $this->ddwcpp_get_payable_total();
			$plans = $this->ddwcpp_get_available_plans( $total, get_current_user_id() );

			if ( ! $plans ) {
				return;
			}
			?>
			<fieldset class="ddwcpp-plan-options">
				<legend><?php esc_html_e( 'Choose how to pay', 'buy-now-pay-later-for-woocommerce' ); ?></legend>
				<?php
				$first = true;

				foreach ( $plans as $plan ) {
					$schedule = DDWCPP_Plans_Helper::ddwcpp_build_schedule( $plan, $total );
					?>
					<label class="ddwcpp-plan-option">
						<input type="radio" name="ddwcpp_plan" value="<?php echo esc_attr( $plan['id'] ); ?>" <?php checked( $first ); ?> />
						<span class="ddwcpp-plan-option-body">
							<span class="ddwcpp-plan-option-title"><?php echo esc_html( $plan['title'] ); ?></span>
							<span class="ddwcpp-plan-option-summary"><?php echo wp_kses_post( DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan, $total ) ); ?></span>
							<span class="ddwcpp-plan-option-meta">
								<?php
								if ( ! empty( $plan['upfront'] ) && count( $schedule ) > 1 ) {
									/* translators: %s: first payment amount. */
									printf( esc_html__( '%s due today', 'buy-now-pay-later-for-woocommerce' ), wp_kses_post( wc_price( $schedule[0]['amount'] ) ) );
								} else {
									/* translators: %s: first due date. */
									printf( esc_html__( 'First payment %s', 'buy-now-pay-later-for-woocommerce' ), esc_html( date_i18n( wc_date_format(), strtotime( $schedule[0]['due_date'] ) ) ) );
								}

								echo ' &middot; ';
								esc_html_e( 'No fees', 'buy-now-pay-later-for-woocommerce' );
								?>
							</span>
						</span>
					</label>
					<?php
					$first = false;
				}
				?>
			</fieldset>
			<?php
		}

		/**
		 * Plan chosen at checkout, falling back to the first one available.
		 *
		 * @param float $total   Amount.
		 * @param int   $user_id Customer.
		 * @return array|null
		 */
		protected function ddwcpp_get_chosen_plan( $total, $user_id ) {
			$plans = $this->ddwcpp_get_available_plans( $total, $user_id );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce before calling the gateway.
			$id = isset( $_POST['ddwcpp_plan'] ) ? sanitize_title( wp_unslash( $_POST['ddwcpp_plan'] ) ) : '';

			if ( $id ) {
				return isset( $plans[ $id ] ) ? $plans[ $id ] : null;
			}

			return $plans ? reset( $plans ) : null;
		}

		/**
		 * Reject a plan that is not available for this order.
		 *
		 * @return bool
		 */
		public function validate_fields() {
			if ( ! $this->ddwcpp_get_chosen_plan( $this->ddwcpp_get_payable_total(), get_current_user_id() ) ) {
				wc_add_notice( esc_html__( 'Choose one of the Pay Later payment plans to continue.', 'buy-now-pay-later-for-woocommerce' ), 'error' );
				return false;
			}

			return true;
		}

		/**
		 * "Available credit" line for the checkout.
		 *
		 * @return string Empty when disabled.
		 */
		public function ddwcpp_get_credit_notice() {
			$user_id = get_current_user_id();

			if ( empty( $this->ddwcpp_configuration['show_credit_at_checkout'] ) || ! $user_id ) {
				return '';
			}

			return sprintf(
				/* translators: %s: available credit. */
				esc_html__( 'Available credit: %s', 'buy-now-pay-later-for-woocommerce' ),
				wc_price( $this->users_helper->ddwcpp_get_balance( $user_id ) )
			);
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order   = wc_get_order( $order_id );
			$user_id = $order->get_customer_id();
			$total   = (float) $order->get_total();
			$plan    = $this->ddwcpp_get_chosen_plan( $total, $user_id );

			// The availability check ran when the page loaded; the balance may have changed since.
			$allowed = $plan ? $this->users_helper->ddwcpp_can_use( $user_id, $total ) : new \WP_Error( 'ddwcpp_plan', esc_html__( 'Choose one of the Pay Later payment plans to continue.', 'buy-now-pay-later-for-woocommerce' ) );

			if ( is_wp_error( $allowed ) ) {
				wc_add_notice( $allowed->get_error_message(), 'error' );
				return [ 'result' => 'failure' ];
			}

			$schedule = DDWCPP_Plans_Helper::ddwcpp_build_schedule( $plan, $total );
			$summary  = wp_strip_all_tags( DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan, $total ) );

			$this->users_helper->ddwcpp_record(
				$user_id,
				[
					'type'      => 'debit',
					'amount'    => $total,
					'reference' => 'order_debit',
					'order_id'  => $order_id,
					/* translators: %s: plan title. */
					'note'      => sprintf( esc_html__( 'Order paid with Pay Later, %s.', 'buy-now-pay-later-for-woocommerce' ), $plan['title'] ),
					'plan'      => $plan,
					'schedule'  => $schedule,
				]
			);

			\DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_send(
				'plan_confirmation',
				$user_id,
				[
					'order_number' => $order->get_order_number(),
					'plan_title'   => $plan['title'],
					'plan_summary' => $summary,
					'amount'       => wc_price( $total, [ 'currency' => $order->get_currency() ] ),
					'schedule'     => DDWCPP_Plan_Schedule_Template::ddwcpp_get_schedule_html( $order_id ),
				]
			);

			$order->update_meta_data( '_ddwcpp_debited_amount', wc_format_decimal( $total ) );
			$order->update_meta_data( '_ddwcpp_plan_id', $plan['id'] );
			$order->update_meta_data( '_ddwcpp_plan_title', $plan['title'] );

			/* translators: %1$s: plan title, %2$s: plan summary. */
			$order->add_order_note( sprintf( esc_html__( 'Paid with Pay Later: %1$s, %2$s.', 'buy-now-pay-later-for-woocommerce' ), $plan['title'], $summary ) );

			if ( ! empty( $plan['upfront'] ) && count( $schedule ) > 1 ) {
				// Held until the first installment comes in, then released by ddwcpp_on_installment_paid().
				$order->update_meta_data( '_ddwcpp_awaiting_upfront', 'yes' );
				$order->update_status( 'on-hold', esc_html__( 'Awaiting the first Pay Later installment.', 'buy-now-pay-later-for-woocommerce' ) );
				wc_maybe_reduce_stock_levels( $order_id );
			} else {
				$order->payment_complete();
			}

			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];
		}

		/**
		 * Return refunded money to the customer's credit.
		 *
		 * @param int    $order_id Order ID.
		 * @param float  $amount   Amount.
		 * @param string $reason   Reason.
		 * @return bool|\WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );

			if ( ! $order || ! $order->get_customer_id() || (float) $amount <= 0 ) {
				return new \WP_Error( 'ddwcpp_refund', esc_html__( 'This order cannot be refunded to Pay Later credit.', 'buy-now-pay-later-for-woocommerce' ) );
			}

			$this->users_helper->ddwcpp_record(
				$order->get_customer_id(),
				[
					'type'       => 'credit',
					'amount'     => $amount,
					'reference'  => 'order_refund',
					'order_id'   => $order_id,
					'note'       => $reason ? $reason : esc_html__( 'Refund returned to Pay Later credit.', 'buy-now-pay-later-for-woocommerce' ),
					'email'      => 'credit_restored',
					'email_vars' => [ 'reason' => esc_html__( 'refund', 'buy-now-pay-later-for-woocommerce' ) ],
				]
			);

			return true;
		}

		/**
		 * Output for the order received page.
		 *
		 * @return void
		 */
		public function ddwcpp_add_instructions_in_thankyou_page() {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
			}
		}

		/**
		 * Payment schedule under the order details, on the thank you and view order pages.
		 *
		 * @param \WC_Order $order Order.
		 * @return void
		 */
		public function ddwcpp_add_schedule_to_order_details( $order ) {
			if ( $order instanceof \WC_Order && $this->id === $order->get_payment_method() && $order->get_customer_id() === get_current_user_id() ) {
				new DDWCPP_Plan_Schedule_Template( $order->get_id() );
			}
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param \WC_Order $order         Order.
		 * @param bool      $sent_to_admin Sent to admin.
		 * @param bool      $plain_text    Plain text.
		 * @return void
		 */
		public function ddwcpp_add_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
				echo $plain_text ? esc_html( wp_strip_all_tags( $this->instructions ) ) . PHP_EOL : wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
			}
		}
	}
}
