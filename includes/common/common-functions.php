<?php
/**
 * Callbacks for hooks that run on both admin and front end requests.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Common;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;
use DDWCPostPaid\Includes\DDWCPP_Email_Notification_Handler;
use DDWCPostPaid\Includes\DDWCPP_PostPaid;
use DDWCPostPaid\Includes\Blocks\DDWCPP_PostPaid_Blocks;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Common_Functions' ) ) {
	/**
	 * Common functions class
	 */
	class DDWCPP_Common_Functions {
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
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->users_helper         = new DDWCPP_Users_Helper( $ddwcpp_configuration );
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param array $methods Payment methods.
		 * @return array
		 */
		public function ddwcpp_add_payment_gateway( $methods ) {
			$methods[] = DDWCPP_PostPaid::class;
			return $methods;
		}

		/**
		 * Make the gateway available in the Checkout block.
		 *
		 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Registry.
		 * @return void
		 */
		public function ddwcpp_register_block_payment_method( $registry ) {
			if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				$registry->register( new DDWCPP_PostPaid_Blocks( $this->ddwcpp_configuration ) );
			}
		}

		/**
		 * Add Email Notification function
		 *
		 * @param array $email_classes Email classes.
		 * @return array
		 */
		public function ddwcpp_add_new_email_notification( $email_classes ) {
			$email_classes['WC_Email_PostPaid_Management_Notification'] = new DDWCPP_Email_Notification_Handler();
			return $email_classes;
		}

		/**
		 * Add Email Notification Action function
		 *
		 * @param array $actions Actions.
		 * @return array
		 */
		public function ddwcpp_add_notification_actions( $actions ) {
			$actions[] = 'ddwcpp_mail';
			return $actions;
		}

		/**
		 * Register the My Account endpoint.
		 *
		 * @return void
		 */
		public function ddwcpp_add_endpoints() {
			add_rewrite_endpoint( DDWCPP_POSTPAID_ENDPOINT, EP_ROOT | EP_PAGES );

			if ( get_transient( 'ddwcpp_flush_rewrite_rules' ) ) {
				delete_transient( 'ddwcpp_flush_rewrite_rules' );
				flush_rewrite_rules();
			}
		}

		/**
		 * Keep the repayment amount on the order line, so the credit does not depend on the product price.
		 *
		 * @param \WC_Order_Item_Product $item          Order item.
		 * @param string                 $cart_item_key Cart item key.
		 * @param array                  $values        Cart item.
		 * @return void
		 */
		public function ddwcpp_save_dues_amount_on_item( $item, $cart_item_key, $values ) {
			if ( isset( $values['ddwcpp_dues_amount'] ) ) {
				$item->add_meta_data( '_ddwcpp_dues_amount', wc_format_decimal( $values['ddwcpp_dues_amount'] ), true );
			}
		}

		/**
		 * Hide the internal item meta in the admin order screen.
		 *
		 * @param array $meta_keys Hidden keys.
		 * @return array
		 */
		public function ddwcpp_hide_order_item_meta( $meta_keys ) {
			$meta_keys[] = '_ddwcpp_dues_amount';
			return $meta_keys;
		}

		/**
		 * Credit a customer once their dues repayment order is paid.
		 *
		 * @param int $order_id Order ID.
		 * @return void
		 */
		public function ddwcpp_credit_paid_dues( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order || $order->get_meta( '_ddwcpp_order_completion_handled' ) || ! $order->get_customer_id() ) {
				return;
			}

			$dues_product_id = ddwcpp_get_dues_product_id();
			$amount          = 0;

			foreach ( $order->get_items() as $item ) {
				if ( $item->get_product_id() === $dues_product_id ) {
					$saved   = $item->get_meta( '_ddwcpp_dues_amount' );
					$amount += '' !== $saved ? (float) $saved : (float) $item->get_subtotal();
				}
			}

			if ( $amount <= 0 ) {
				return;
			}

			// Flag first, so a second status change in the same request cannot credit twice.
			$order->update_meta_data( '_ddwcpp_order_completion_handled', 'yes' );
			$order->save();

			$this->users_helper->ddwcpp_record(
				$order->get_customer_id(),
				[
					'type'      => 'credit',
					'amount'    => $amount,
					'reference' => 'cleared_dues',
					'order_id'  => $order_id,
					'note'      => esc_html__( 'Pay Later dues repayment.', 'buy-now-pay-later-for-woocommerce' ),
					'email'     => 'payment_received',
				]
			);

			$order->add_order_note( esc_html__( 'Pay Later dues credited to the customer.', 'buy-now-pay-later-for-woocommerce' ) );
		}

		/**
		 * Give the credit back when a PostPaid order is cancelled or fails.
		 *
		 * @param int $order_id Order ID.
		 * @return void
		 */
		public function ddwcpp_restore_credit_on_cancel( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( empty( $this->ddwcpp_configuration['restore_on_cancel'] ) || ! $order || 'ddwcpp_postpaid' !== $order->get_payment_method() ) {
				return;
			}

			$debited = (float) $order->get_meta( '_ddwcpp_debited_amount' );

			if ( $debited <= 0 || $order->get_meta( '_ddwcpp_credit_restored' ) ) {
				return;
			}

			// Refunds went back through process_refund(), and paid installments already returned
			// their credit; only what is still owed on the order comes back now.
			$amount = $debited - (float) $order->get_total_refunded() - ( new DDWCPP_Installments_Helper() )->ddwcpp_get_order_paid( $order_id );

			$order->update_meta_data( '_ddwcpp_credit_restored', 'yes' );
			$order->save();

			if ( $amount <= 0 ) {
				return;
			}

			$this->users_helper->ddwcpp_record(
				$order->get_customer_id(),
				[
					'type'       => 'credit',
					'amount'     => $amount,
					'reference'  => 'order_cancelled',
					'order_id'   => $order_id,
					'note'       => esc_html__( 'Credit restored after the order was cancelled.', 'buy-now-pay-later-for-woocommerce' ),
					'email'      => 'credit_restored',
					'email_vars' => [ 'reason' => esc_html__( 'order cancelled', 'buy-now-pay-later-for-woocommerce' ) ],
				]
			);

			/* translators: %s: amount. */
			$order->add_order_note( sprintf( esc_html__( 'Pay Later credit of %s restored to the customer.', 'buy-now-pay-later-for-woocommerce' ), wc_price( $amount ) ) );
		}

		/**
		 * Daily: remind customers before an installment is due, then notify them once it is
		 * overdue. Each row is reminded and notified once, so a missed run catches up.
		 *
		 * @return void
		 */
		public function ddwcpp_process_payment_terms() {
			$config = $this->ddwcpp_configuration;

			if ( empty( $config['enabled'] ) ) {
				return;
			}

			$helper = new DDWCPP_Installments_Helper();

			if ( $config['reminder_days'] ) {
				$rows = $helper->ddwcpp_get_installments(
					[
						'view' => 'due_soon',
						'days' => $config['reminder_days'],
					]
				);

				foreach ( $rows as $row ) {
					if ( (int) $row['reminder_sent'] ) {
						continue;
					}

					$helper->ddwcpp_update( $row['id'], [ 'reminder_sent' => 1 ] );
					$this->ddwcpp_notify_installment( $row, 'reminder' );
				}
			}

			foreach ( $helper->ddwcpp_get_installments( [ 'view' => 'overdue' ] ) as $row ) {
				if ( (int) $row['overdue_notified'] ) {
					continue;
				}

				$helper->ddwcpp_update( $row['id'], [ 'overdue_notified' => 1 ] );

				$this->ddwcpp_notify_installment( $row, 'overdue' );

				do_action( 'ddwcpp_installment_overdue', $row );
			}
		}

		/**
		 * Email the customer about one installment.
		 *
		 * @param array  $row  Installment.
		 * @param string $type reminder or overdue.
		 * @return void
		 */
		protected function ddwcpp_notify_installment( $row, $type ) {
			$order = $row['order_id'] ? wc_get_order( $row['order_id'] ) : null;

			\DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_send(
				'reminder' === $type ? 'payment_reminder' : 'payment_overdue',
				$row['user_id'],
				[
					'amount'        => wc_price( DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ) ),
					'due_date'      => $row['due_date'] ? date_i18n( wc_date_format(), strtotime( $row['due_date'] ) ) : '',
					'payment_label' => ucfirst( $this->ddwcpp_describe_installment( $row ) ),
					'order_number'  => $order ? $order->get_order_number() : '',
					'plan_title'    => $row['plan_title'],
				]
			);
		}

		/**
		 * "payment 2 of 4 for order #123" style text.
		 *
		 * @param array $row Installment.
		 * @return string
		 */
		protected function ddwcpp_describe_installment( $row ) {
			$order = $row['order_id'] ? wc_get_order( $row['order_id'] ) : null;

			if ( $order && (int) $row['total_count'] > 1 ) {
				/* translators: %1$d: payment number, %2$d: number of payments, %3$s: order number. */
				return sprintf( esc_html__( 'payment %1$d of %2$d for order #%3$s', 'buy-now-pay-later-for-woocommerce' ), $row['sequence'], $row['total_count'], $order->get_order_number() );
			}

			if ( $order ) {
				/* translators: %s: order number. */
				return sprintf( esc_html__( 'the payment for order #%s', 'buy-now-pay-later-for-woocommerce' ), $order->get_order_number() );
			}

			return esc_html__( 'your Pay Later payment', 'buy-now-pay-later-for-woocommerce' );
		}

		/**
		 * Release an order waiting on its first payment, and close finished plans.
		 *
		 * @param array $row Installment that was just paid.
		 * @return void
		 */
		public function ddwcpp_on_installment_paid( $row ) {
			$order = $row['order_id'] ? wc_get_order( $row['order_id'] ) : null;

			if ( ! $order ) {
				return;
			}

			if ( $order->get_meta( '_ddwcpp_awaiting_upfront' ) ) {
				$order->delete_meta_data( '_ddwcpp_awaiting_upfront' );
				$order->add_order_note( esc_html__( 'First Pay Later installment received.', 'buy-now-pay-later-for-woocommerce' ) );
				$order->payment_complete();
			}

			$open = ( new DDWCPP_Installments_Helper() )->ddwcpp_count(
				[
					'order_id' => $row['order_id'],
					'view'     => 'pending',
				]
			);

			if ( ! $open && (int) $row['total_count'] > 1 ) {
				$order->add_order_note( esc_html__( 'Pay Later payment plan completed. Every installment is paid.', 'buy-now-pay-later-for-woocommerce' ) );
				do_action( 'ddwcpp_plan_completed', $order, $row );
			}
		}

		/**
		 * Add the Pay Later account to Tools > Export Personal Data.
		 *
		 * @param array $exporters Exporters.
		 * @return array
		 */
		public function ddwcpp_register_privacy_exporter( $exporters ) {
			$exporters['buy-now-pay-later-for-woocommerce'] = [
				'exporter_friendly_name' => esc_html__( 'Pay Later Account', 'buy-now-pay-later-for-woocommerce' ),
				'callback'               => [ $this, 'ddwcpp_export_personal_data' ],
			];

			return $exporters;
		}

		/**
		 * Credit line and transactions of a customer, 100 transactions per page.
		 *
		 * Erasure is left to the store: these are records of money owed, like orders.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page, from 1.
		 * @return array
		 */
		public function ddwcpp_export_personal_data( $email, $page = 1 ) {
			$user  = get_user_by( 'email', $email );
			$items = [];

			if ( ! $user ) {
				return [
					'data' => [],
					'done' => true,
				];
			}

			$page = max( 1, absint( $page ) );

			if ( 1 === $page && $this->users_helper->ddwcpp_is_allotted( $user->ID ) ) {
				$account = $this->users_helper->ddwcpp_get_account( $user->ID );
				$items[] = [
					'group_id'    => 'ddwcpp-account',
					'group_label' => esc_html__( 'Pay Later Account', 'buy-now-pay-later-for-woocommerce' ),
					'item_id'     => 'ddwcpp-account-' . $user->ID,
					'data'        => [
						[
							'name'  => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $account['status'],
						],
						[
							'name'  => esc_html__( 'Credit limit', 'buy-now-pay-later-for-woocommerce' ),
							'value' => wc_format_decimal( $account['limit'], wc_get_price_decimals() ),
						],
						[
							'name'  => esc_html__( 'Available credit', 'buy-now-pay-later-for-woocommerce' ),
							'value' => wc_format_decimal( $account['balance'], wc_get_price_decimals() ),
						],
						[
							'name'  => esc_html__( 'Owed', 'buy-now-pay-later-for-woocommerce' ),
							'value' => wc_format_decimal( $account['dues'], wc_get_price_decimals() ),
						],
					],
				];
			}

			$rows = ( new DDWCPP_Transactions_Helper() )->ddwcpp_get_transactions(
				[
					'user_id'  => $user->ID,
					'per_page' => 100,
					'offset'   => ( $page - 1 ) * 100,
				]
			);

			foreach ( $rows as $row ) {
				$items[] = [
					'group_id'    => 'ddwcpp-transactions',
					'group_label' => esc_html__( 'Pay Later Transactions', 'buy-now-pay-later-for-woocommerce' ),
					'item_id'     => 'ddwcpp-transaction-' . $row['id'],
					'data'        => [
						[
							'name'  => esc_html__( 'Date', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $row['date'],
						],
						[
							'name'  => esc_html__( 'Type', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $row['type'],
						],
						[
							'name'  => esc_html__( 'Amount', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $row['amount'],
						],
						[
							'name'  => esc_html__( 'Order', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $row['order_id'],
						],
						[
							'name'  => esc_html__( 'Note', 'buy-now-pay-later-for-woocommerce' ),
							'value' => $row['note'],
						],
					],
				];
			}

			return [
				'data' => $items,
				'done' => count( $rows ) < 100,
			];
		}
	}
}
