<?php
/**
 * Admin end callbacks.
 *
 * @author DevDiggers
 * @version 2.0.0
 * @package WooCommerce PostPaid
 */

namespace DDWCPostPaid\Includes\Admin;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Admin_Functions' ) ) {
	/**
	 * Admin functions class
	 */
	class DDWCPP_Admin_Functions {
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
		 * Capability needed to manage PostPaid.
		 *
		 * @return string
		 */
		protected function ddwcpp_get_capability() {
			return apply_filters( 'ddwcpp_modify_menu_capability', 'manage_woocommerce' );
		}

		/**
		 * Register Option settings, grouped by the configuration tab that saves them.
		 *
		 * @return void
		 */
		public function ddwcpp_register_settings() {
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_enabled', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_menu_title', [ 'sanitize_callback' => 'sanitize_text_field' ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_my_account_endpoint', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_endpoint' ] ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_sidebar_widgets', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_show_credit_at_checkout', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_restore_on_cancel', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-general-configuration-fields', '_ddwcpp_plans', [ 'sanitize_callback' => [ DDWCPP_Plans_Helper::class, 'ddwcpp_sanitize_statuses' ] ] );

			register_setting( 'ddwcpp-approval-configuration-fields', '_ddwcpp_limit', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_limit' ] ] );
			register_setting( 'ddwcpp-approval-configuration-fields', '_ddwcpp_verification_mode', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_verification_mode' ] ] );
			register_setting( 'ddwcpp-approval-configuration-fields', '_ddwcpp_auto_activate', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-approval-configuration-fields', '_ddwcpp_min_order_amount', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_amount' ] ] );
			register_setting( 'ddwcpp-approval-configuration-fields', '_ddwcpp_max_order_amount', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_amount' ] ] );

			register_setting( 'ddwcpp-reminders-configuration-fields', '_ddwcpp_reminder_days', [ 'sanitize_callback' => 'absint' ] );
			register_setting( 'ddwcpp-reminders-configuration-fields', '_ddwcpp_charge_due_days', [ 'sanitize_callback' => 'absint' ] );
			register_setting( 'ddwcpp-reminders-configuration-fields', '_ddwcpp_block_overdue', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );

			register_setting( 'ddwcpp-emails-configuration-fields', '_ddwcpp_email_settings', [ 'sanitize_callback' => [ '\\DDWCPostPaid\\Helper\\Emails\\DDWCPP_Emails_Helper', 'ddwcpp_sanitize_settings' ] ] );

			register_setting( 'ddwcpp-messaging-configuration-fields', '_ddwcpp_message_enabled', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_checkbox' ] ] );
			register_setting( 'ddwcpp-messaging-configuration-fields', '_ddwcpp_message_locations', [ 'sanitize_callback' => [ $this, 'ddwcpp_sanitize_locations' ] ] );
			register_setting( 'ddwcpp-messaging-configuration-fields', '_ddwcpp_message_text', [ 'sanitize_callback' => 'sanitize_text_field' ] );
			register_setting( 'ddwcpp-messaging-configuration-fields', '_ddwcpp_message_text_single', [ 'sanitize_callback' => 'sanitize_text_field' ] );
		}

		/**
		 * Normalize checkbox values.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddwcpp_sanitize_checkbox( $value ) {
			return 'yes' === $value ? 'yes' : '';
		}

		/**
		 * Require a positive credit limit.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddwcpp_sanitize_limit( $value ) {
			$amount = wc_format_decimal( $value );
			return is_numeric( $amount ) && (float) $amount > 0 ? $amount : '1000';
		}

		/**
		 * A non-negative amount, empty when not set.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddwcpp_sanitize_amount( $value ) {
			$amount = wc_format_decimal( $value );
			return is_numeric( $amount ) && (float) $amount >= 0 ? $amount : '';
		}

		/**
		 * Supported verification modes.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddwcpp_sanitize_verification_mode( $value ) {
			return array_key_exists( $value, ddwcpp_get_verification_mode_options() ) ? $value : 'manual';
		}

		/**
		 * My Account endpoint slug; a change refreshes the permalinks once.
		 *
		 * @param mixed $value Submitted value.
		 * @return string
		 */
		public function ddwcpp_sanitize_endpoint( $value ) {
			$slug = sanitize_title( $value );
			$slug = $slug ? $slug : 'pay-later';

			if ( get_option( '_ddwcpp_my_account_endpoint', 'pay-later' ) !== $slug ) {
				set_transient( 'ddwcpp_flush_rewrite_rules', true, MINUTE_IN_SECONDS );
			}

			return $slug;
		}

		/**
		 * Known message locations only.
		 *
		 * @param mixed $value Submitted value.
		 * @return array
		 */
		public function ddwcpp_sanitize_locations( $value ) {
			return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array_keys( ddwcpp_get_message_location_options() ) ) );
		}

		/**
		 * Save the per page screen options.
		 *
		 * @param mixed  $status Default.
		 * @param string $option Option.
		 * @param mixed  $value  Value.
		 * @return mixed
		 */
		public function ddwcpp_set_screen_option( $status, $option, $value ) {
			return in_array( $option, [ 'ddwcpp_customers_per_page', 'ddwcpp_transactions_per_page', 'ddwcpp_installments_per_page' ], true ) ? absint( $value ) : $status;
		}

		/**
		 * Redirect back to a list with a notice.
		 *
		 * @param string $menu   Dashboard menu.
		 * @param string $notice Notice key.
		 * @param int    $count  Affected items.
		 * @return void
		 */
		protected function ddwcpp_redirect_with_notice( $menu, $notice, $count = 0 ) {
			$args = [
				'menu'          => $menu,
				'ddwcpp-notice' => $notice,
				'ddwcpp-count'  => $count,
			];

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only carrying list filters through the redirect.
			foreach ( [ 'status', 's', 'paged', 'view' ] as $key ) {
				if ( ! empty( $_GET[ $key ] ) ) {
					$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			wp_safe_redirect( ddwcpp_get_dashboard_url( $args ) );
			exit;
		}

		/**
		 * Approve, reject or reset a customer from the customers list.
		 *
		 * @return void
		 */
		public function ddwcpp_process_customer_actions() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check only; the nonce is verified below.
			if ( ! isset( $_GET['page'], $_GET['menu'] ) || DDWCPP_DASHBOARD_PAGE !== $_GET['page'] || 'customers' !== $_GET['menu'] ) {
				return;
			}

			$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; the nonce is verified below before anything changes.

			if ( ! in_array( $action, [ 'eligible', 'rejected', 'pending' ], true ) ) {
				return;
			}

			if ( ! current_user_can( $this->ddwcpp_get_capability() ) ) {
				wp_die( esc_html__( 'You are not allowed to manage Pay Later customers.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 403 );
			}

			$nonce = isset( $_GET['ddwcpp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ddwcpp_nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'ddwcpp_customers_bulk' ) ) {
				$this->ddwcpp_redirect_with_notice( 'customers', 'invalid-nonce' );
			}

			$user_id = isset( $_GET['ddwcpp-user-id'] ) ? absint( wp_unslash( $_GET['ddwcpp-user-id'] ) ) : 0;

			if ( ! $user_id ) {
				$this->ddwcpp_redirect_with_notice( 'customers', 'no-selection' );
			}

			$updated = (int) $this->users_helper->ddwcpp_update_verification_status( $user_id, $action );

			$this->ddwcpp_redirect_with_notice( 'customers', $updated ? $action : 'nothing-updated', $updated );
		}

		/**
		 * Record a payment for an installment from the installments list.
		 *
		 * @return void
		 */
		public function ddwcpp_process_installment_actions() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check only; the nonce is verified below.
			if ( ! isset( $_GET['page'], $_GET['menu'] ) || DDWCPP_DASHBOARD_PAGE !== $_GET['page'] || 'installments' !== $_GET['menu'] ) {
				return;
			}

			$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; the nonce is verified below before anything changes.

			if ( 'mark_paid' !== $action ) {
				return;
			}

			if ( ! current_user_can( $this->ddwcpp_get_capability() ) ) {
				wp_die( esc_html__( 'You are not allowed to manage Pay Later installments.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 403 );
			}

			$nonce = isset( $_GET['ddwcpp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ddwcpp_nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'ddwcpp_installments_bulk' ) ) {
				$this->ddwcpp_redirect_with_notice( 'installments', 'invalid-nonce' );
			}

			$helper = new DDWCPP_Installments_Helper();
			$row    = $helper->ddwcpp_get( isset( $_GET['ddwcpp-installment-id'] ) ? absint( wp_unslash( $_GET['ddwcpp-installment-id'] ) ) : 0 );

			if ( ! $row || 'pending' !== $row['status'] ) {
				$this->ddwcpp_redirect_with_notice( 'installments', 'nothing-updated' );
			}

			$this->users_helper->ddwcpp_record(
				$row['user_id'],
				[
					'type'           => 'credit',
					'amount'         => DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ),
					'reference'      => 'manual_payment',
					'order_id'       => $row['order_id'] ? $row['order_id'] : null,
					'installment_id' => $row['id'],
					/* translators: %1$d: payment number, %2$d: number of payments. */
					'note'           => sprintf( esc_html__( 'Payment %1$d of %2$d recorded by the store.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $row['sequence'], $row['total_count'] ),
					'email'          => 'payment_received',
				]
			);

			$this->ddwcpp_redirect_with_notice( 'installments', $action, 1 );
		}

		/**
		 * Installment list filters from the request.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_installment_filters() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
			$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'upcoming';

			$filters = [
				'view'    => in_array( $view, [ 'upcoming', 'due_soon', 'overdue', 'paid', 'cancelled', 'all' ], true ) ? $view : 'upcoming',
				'user_id' => isset( $_GET['customer-id'] ) ? absint( wp_unslash( $_GET['customer-id'] ) ) : 0,
				'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			];
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( 'all' === $filters['view'] ) {
				$filters['view'] = '';
			}

			return $filters;
		}

		/**
		 * Transaction list filters from the request.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_transaction_filters() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
			$date = function ( $key ) {
				$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			};

			$filters = [
				'type'      => isset( $_GET['ddwcpp-transaction-type'] ) && in_array( $_GET['ddwcpp-transaction-type'], [ 'credit', 'debit' ], true ) ? sanitize_key( wp_unslash( $_GET['ddwcpp-transaction-type'] ) ) : '',
				'reference' => isset( $_GET['ddwcpp-reference'] ) ? sanitize_key( wp_unslash( $_GET['ddwcpp-reference'] ) ) : '',
				'user_id'   => isset( $_GET['customer-id'] ) ? absint( wp_unslash( $_GET['customer-id'] ) ) : 0,
				'from'      => $date( 'ddwcpp-transaction-from-date' ),
				'to'        => $date( 'ddwcpp-transaction-to-date' ),
				'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			];
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			return $filters;
		}

		/**
		 * Customer search for the transactions filter.
		 *
		 * @return void
		 */
		public function ddwcpp_ajax_get_users_list() {
			if ( ! current_user_can( $this->ddwcpp_get_capability() ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ], 403 );
			}

			if ( ! check_ajax_referer( 'ddwcpp-nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Security check failed!', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ], 403 );
			}

			$term  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
			$users = get_users(
				[
					'search'         => '*' . $term . '*',
					'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
					'fields'         => [ 'ID', 'user_login', 'user_email' ],
					'number'         => 20,
				]
			);

			wp_send_json_success( [ 'users' => $users ] );
		}

		/**
		 * Credit or debit a customer by hand, with a logged note.
		 *
		 * @return void
		 */
		public function ddwcpp_ajax_adjust_balance() {
			if ( ! current_user_can( $this->ddwcpp_get_capability() ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ], 403 );
			}

			if ( ! check_ajax_referer( 'ddwcpp-nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Security check failed!', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ], 403 );
			}

			$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
			$type    = isset( $_POST['type'] ) && 'debit' === sanitize_key( wp_unslash( $_POST['type'] ) ) ? 'debit' : 'credit';
			$amount  = isset( $_POST['amount'] ) ? (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
			$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
			$notify  = isset( $_POST['notify'] ) && 'true' === sanitize_key( wp_unslash( $_POST['notify'] ) );
			$due     = isset( $_POST['due_days'] ) && '' !== sanitize_text_field( wp_unslash( $_POST['due_days'] ) ) ? absint( wp_unslash( $_POST['due_days'] ) ) : null;

			if ( ! $user_id || ! get_userdata( $user_id ) || $amount <= 0 ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Enter a valid customer and amount.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ] );
			}

			// An adjustment needs a credit line to adjust.
			$this->users_helper->ddwcpp_allot( $user_id );

			$note = $note ? $note : ( 'credit' === $type ? esc_html__( 'Dues reduced by the store.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html__( 'Dues added by the store.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) );

			$this->users_helper->ddwcpp_record(
				$user_id,
				[
					'type'       => $type,
					'amount'     => $amount,
					'reference'  => 'manual_' . $type,
					'note'       => $note,
					'email'      => $notify ? 'credit_adjusted' : '',
					'email_vars' => [ 'adjustment' => 'credit' === $type ? esc_html__( 'reduced your dues by', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html__( 'added to your dues', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ],
					'due_days'   => $due,
				]
			);

			wp_send_json_success( $this->ddwcpp_get_row_values( $user_id, esc_html__( 'Balance adjusted.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ) );
		}

		/**
		 * Payment schedule box on the order screen, classic and HPOS.
		 *
		 * @param string             $screen_id Screen ID.
		 * @param \WP_Post|\WC_Order $order_object Order or its post.
		 * @return void
		 */
		public function ddwcpp_add_order_meta_box( $screen_id, $order_object = null ) {
			$order = $order_object instanceof \WC_Order ? $order_object : ( $order_object instanceof \WP_Post ? wc_get_order( $order_object->ID ) : null );

			if ( ! $order || 'ddwcpp_postpaid' !== $order->get_payment_method() ) {
				return;
			}

			add_meta_box(
				'ddwcpp-order-schedule',
				esc_html__( 'Pay Later Payment Plan', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				function () use ( $order ) {
					$rows = \DDWCPostPaid\Templates\Front\DDWCPP_Plan_Schedule_Template::ddwcpp_get_rows( $order->get_id() );

					if ( ! $rows ) {
						echo '<p>' . esc_html__( 'No installments for this order.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>';
						return;
					}

					new \DDWCPostPaid\Templates\Front\DDWCPP_Plan_Schedule_Template( $order->get_id() );

					echo '<p><a class="button" href="' . esc_url(
						ddwcpp_get_dashboard_url(
							[
								'menu' => 'installments',
								'view' => 'all',
								's'    => $order->get_id(),
							]
						)
					) . '">' . esc_html__( 'Manage installments', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a></p>';
				},
				$screen_id,
				'side',
				'default'
			);
		}

		/**
		 * Fresh values for a customers list row after an inline change.
		 *
		 * @param int    $user_id User ID.
		 * @param string $message Message.
		 * @return array
		 */
		protected function ddwcpp_get_row_values( $user_id, $message ) {
			$account = $this->users_helper->ddwcpp_get_account( $user_id );

			return [
				'message' => $message,
				'balance' => wc_price( max( 0, $account['balance'] ) ),
				'dues'    => wc_price( $account['dues'] ),
			];
		}
	}
}
