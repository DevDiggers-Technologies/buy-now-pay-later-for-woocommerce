<?php
/**
 * Users helper.
 *
 * Owns a customer's PostPaid account: limit, available credit, dues, due date and
 * verification. Every balance change goes through ddwcpp_record() so the balance, the
 * transaction log, the due date and the customer email can never drift apart.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Users;

use DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper;
use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Users_Helper' ) ) {
	/**
	 * Users helper class
	 */
	class DDWCPP_Users_Helper {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Transactions Helper Variable
		 *
		 * @var DDWCPP_Transactions_Helper
		 */
		protected $transactions_helper;

		/**
		 * Installments Helper Variable
		 *
		 * @var DDWCPP_Installments_Helper
		 */
		protected $installments_helper;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->transactions_helper  = new DDWCPP_Transactions_Helper();
			$this->installments_helper  = new DDWCPP_Installments_Helper();
		}

		/**
		 * Credit limit of a customer, falling back to the store default.
		 *
		 * @param int $user_id User ID.
		 * @return float
		 */
		public function ddwcpp_get_limit( $user_id ) {
			$limit = get_user_meta( $user_id, '_ddwcpp_postpaid_limit', true );

			return (float) ( '' !== $limit && false !== $limit ? $limit : $this->ddwcpp_configuration['limit'] );
		}

		/**
		 * Available credit.
		 *
		 * @param int $user_id User ID.
		 * @return float
		 */
		public function ddwcpp_get_balance( $user_id ) {
			return (float) get_user_meta( $user_id, '_ddwcpp_postpaid_balance', true );
		}

		/**
		 * Store available credit.
		 *
		 * @param int   $user_id User ID.
		 * @param float $balance Balance.
		 * @return void
		 */
		protected function ddwcpp_set_balance( $user_id, $balance ) {
			update_user_meta( $user_id, '_ddwcpp_postpaid_balance', wc_format_decimal( $balance ) );
		}

		/**
		 * Amount owed.
		 *
		 * @param int $user_id User ID.
		 * @return float
		 */
		public function ddwcpp_get_dues( $user_id ) {
			if ( ! $this->ddwcpp_is_allotted( $user_id ) ) {
				return 0.0;
			}

			return max( 0.0, (float) wc_format_decimal( $this->ddwcpp_get_limit( $user_id ) - $this->ddwcpp_get_balance( $user_id ), wc_get_price_decimals() ) );
		}

		/**
		 * Whether the customer has activated their credit line.
		 *
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public function ddwcpp_is_allotted( $user_id ) {
			return ! empty( get_user_meta( $user_id, '_ddwcpp_postpaid_allotted', true ) );
		}

		/**
		 * Activate the credit line with the full limit available.
		 *
		 * @param int $user_id User ID.
		 * @return void
		 */
		public function ddwcpp_allot( $user_id ) {
			if ( $this->ddwcpp_is_allotted( $user_id ) ) {
				return;
			}

			$limit = $this->ddwcpp_get_limit( $user_id );

			// The store default is what a new customer starts with; saving it here keeps a later
			// change of the default from moving what this customer owes.
			update_user_meta( $user_id, '_ddwcpp_postpaid_limit', wc_format_decimal( $limit ) );
			update_user_meta( $user_id, '_ddwcpp_postpaid_allotted', 'yes' );
			$this->ddwcpp_set_balance( $user_id, $limit );
		}

		/**
		 * Without verification every customer is approved, so with Activate on Approval the
		 * credit line starts the first time it is needed, at checkout or in My Account.
		 *
		 * @param int $user_id User ID.
		 * @return void
		 */
		public function ddwcpp_maybe_auto_activate( $user_id ) {
			if ( $user_id && 'none' === $this->ddwcpp_configuration['verification_mode'] && ! empty( $this->ddwcpp_configuration['auto_activate'] ) ) {
				$this->ddwcpp_allot( $user_id );
			}
		}

		/**
		 * Verification status: pending, eligible, rejected or not_applied.
		 *
		 * @param int $user_id User ID.
		 * @return string
		 */
		public function ddwcpp_get_verification_status( $user_id ) {
			if ( 'none' === $this->ddwcpp_configuration['verification_mode'] ) {
				return 'eligible';
			}

			$status = get_user_meta( $user_id, '_ddwcpp_verification_status', true );

			return in_array( $status, [ 'pending', 'eligible', 'rejected' ], true ) ? $status : 'not_applied';
		}

		/**
		 * Change verification status and tell the customer.
		 *
		 * @param int    $user_id User ID.
		 * @param string $status  pending, eligible or rejected.
		 * @return bool Whether the status changed.
		 */
		public function ddwcpp_update_verification_status( $user_id, $status ) {
			$user = get_userdata( $user_id );

			if ( ! $user || ! in_array( $status, [ 'pending', 'eligible', 'rejected' ], true ) || get_user_meta( $user_id, '_ddwcpp_verification_status', true ) === $status ) {
				return false;
			}

			update_user_meta( $user_id, '_ddwcpp_verification_status', $status );

			if ( 'eligible' === $status && 'none' !== $this->ddwcpp_configuration['verification_mode'] && ! empty( $this->ddwcpp_configuration['auto_activate'] ) ) {
				$this->ddwcpp_allot( $user_id );
			}

			if ( 'pending' !== $status ) {
				DDWCPP_Emails_Helper::ddwcpp_send( 'eligible' === $status ? 'application_approved' : 'application_rejected', $user_id );
			}

			do_action( 'ddwcpp_verification_status_updated', $user_id, $status );

			return true;
		}

		/**
		 * Earliest due date among the installments still owed, as a Y-m-d H:i:s string in site time.
		 *
		 * Mirrored in user meta so customer lists can filter and sort on it.
		 *
		 * @param int $user_id User ID.
		 * @return string Empty when nothing owed has a due date.
		 */
		public function ddwcpp_get_due_date( $user_id ) {
			return (string) get_user_meta( $user_id, '_ddwcpp_due_date', true );
		}

		/**
		 * Whether dues are past their due date.
		 *
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public function ddwcpp_is_overdue( $user_id ) {
			$due_date = $this->ddwcpp_get_due_date( $user_id );

			return $due_date && $this->ddwcpp_get_dues( $user_id ) > 0 && strtotime( $due_date ) < current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Due dates are stored in site time.
		}

		/**
		 * Everything shown about one customer's account.
		 *
		 * @param int $user_id User ID.
		 * @return array
		 */
		public function ddwcpp_get_account( $user_id ) {
			$limit = $this->ddwcpp_get_limit( $user_id );

			return [
				'limit'       => $limit,
				'balance'     => $this->ddwcpp_is_allotted( $user_id ) ? $this->ddwcpp_get_balance( $user_id ) : $limit,
				'dues'        => $this->ddwcpp_get_dues( $user_id ),
				'allotted'    => $this->ddwcpp_is_allotted( $user_id ),
				'status'      => $this->ddwcpp_get_verification_status( $user_id ),
				'due_date'    => $this->ddwcpp_get_due_date( $user_id ),
				'overdue'     => $this->ddwcpp_is_overdue( $user_id ),
				'next_amount' => $this->ddwcpp_get_amount_due_by( $user_id, $this->ddwcpp_get_due_date( $user_id ) ),
			];
		}

		/**
		 * Everything owed on or before a date, i.e. what the customer must pay to be up to date.
		 *
		 * @param int    $user_id  User ID.
		 * @param string $due_date Y-m-d H:i:s.
		 * @return float
		 */
		public function ddwcpp_get_amount_due_by( $user_id, $due_date ) {
			if ( ! $due_date ) {
				return 0.0;
			}

			$amount = 0.0;

			foreach ( $this->installments_helper->ddwcpp_get_pending( $user_id ) as $row ) {
				if ( $row['due_date'] && $row['due_date'] <= $due_date ) {
					$amount += DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row );
				}
			}

			return round( $amount, wc_get_price_decimals() );
		}

		/**
		 * Whether a customer may pay an amount with PostPaid right now.
		 *
		 * @param int   $user_id User ID.
		 * @param float $amount  Order total.
		 * @return true|\WP_Error
		 */
		public function ddwcpp_can_use( $user_id, $amount ) {
			$config = $this->ddwcpp_configuration;

			if ( ! $user_id ) {
				return new \WP_Error( 'ddwcpp_guest', esc_html__( 'Log in to pay with Pay Later.', 'buy-now-pay-later-for-woocommerce' ) );
			}

			$this->ddwcpp_maybe_auto_activate( $user_id );

			if ( 'eligible' !== $this->ddwcpp_get_verification_status( $user_id ) || ! $this->ddwcpp_is_allotted( $user_id ) ) {
				return new \WP_Error( 'ddwcpp_not_active', esc_html__( 'Activate Pay Later from your account before using it.', 'buy-now-pay-later-for-woocommerce' ) );
			}

			if ( ! empty( $config['block_overdue'] ) && $this->ddwcpp_is_overdue( $user_id ) ) {
				return new \WP_Error( 'ddwcpp_overdue', esc_html__( 'Your Pay Later dues are overdue. Clear them to use Pay Later again.', 'buy-now-pay-later-for-woocommerce' ) );
			}

			if ( (float) $config['min_order_amount'] > 0 && $amount < (float) $config['min_order_amount'] ) {
				/* translators: %s: minimum order amount. */
				return new \WP_Error( 'ddwcpp_min', sprintf( esc_html__( 'Pay Later needs an order total of at least %s.', 'buy-now-pay-later-for-woocommerce' ), wc_price( $config['min_order_amount'] ) ) );
			}

			if ( (float) $config['max_order_amount'] > 0 && $amount > (float) $config['max_order_amount'] ) {
				/* translators: %s: maximum order amount. */
				return new \WP_Error( 'ddwcpp_max', sprintf( esc_html__( 'Pay Later can be used for orders up to %s.', 'buy-now-pay-later-for-woocommerce' ), wc_price( $config['max_order_amount'] ) ) );
			}

			if ( $amount > $this->ddwcpp_get_balance( $user_id ) ) {
				/* translators: %s: available credit. */
				return new \WP_Error( 'ddwcpp_balance', sprintf( esc_html__( 'Your available Pay Later credit is %s, which does not cover this order.', 'buy-now-pay-later-for-woocommerce' ), wc_price( $this->ddwcpp_get_balance( $user_id ) ) ) );
			}

			return apply_filters( 'ddwcpp_can_use_postpaid', true, $user_id, $amount );
		}

		/**
		 * Move a customer's balance and log it.
		 *
		 * Ponytail: read-modify-write on user meta, so two checkouts by the same customer in the
		 * same instant could both pass the balance check. Add a per-user lock if that shows up.
		 *
		 * @param int   $user_id User ID.
		 * @param array $args {
		 *     Arguments.
		 *
		 *     @type string $type      credit (raises available credit) or debit (lowers it).
		 *     @type float  $amount    Positive amount.
		 *     @type string $reference Reference key, see ddwcpp_get_translation().
		 *     @type int    $order_id  Related order.
		 *     @type string $note      Note shown on the transaction.
		 *     @type string $email     Email event to send afterwards, see DDWCPP_Emails_Helper. Empty skips the email.
		 *     @type array  $email_vars Extra placeholders for that email.
		 *     @type array  $plan      Debit: plan the amount is paid back with. Default a single charge.
		 *     @type array  $schedule  Debit: installment rows, see DDWCPP_Plans_Helper::ddwcpp_build_schedule().
		 *                             False records the debit without creating rows.
		 *     @type int    $installment_id Credit: row to settle first.
		 *     @type int    $due_days  Debit without a schedule: days until it is due. Default from settings.
		 * }
		 * @return float New available credit.
		 */
		public function ddwcpp_record( $user_id, $args ) {
			$args = wp_parse_args(
				$args,
				[
					'type'           => 'debit',
					'amount'         => 0,
					'reference'      => '',
					'order_id'       => null,
					'note'           => '',
					'email'          => '',
					'email_vars'     => [],
					'plan'           => [],
					'schedule'       => [],
					'installment_id' => 0,
					'due_days'       => null,
				]
			);

			$amount  = abs( (float) $args['amount'] );
			$balance = $this->ddwcpp_get_balance( $user_id ) + ( 'credit' === $args['type'] ? $amount : -$amount );

			$this->ddwcpp_set_balance( $user_id, $balance );

			$this->transactions_helper->ddwcpp_save_transaction(
				[
					'type'      => $args['type'],
					'amount'    => wc_format_decimal( $amount ),
					'user_id'   => $user_id,
					'order_id'  => $args['order_id'],
					'note'      => $args['note'],
					'reference' => $args['reference'],
				]
			);

			$this->ddwcpp_update_installments( $user_id, $amount, $args );

			if ( ! empty( $args['email'] ) ) {
				$order = $args['order_id'] ? wc_get_order( $args['order_id'] ) : null;

				DDWCPP_Emails_Helper::ddwcpp_send(
					$args['email'],
					$user_id,
					array_merge(
						[
							'amount'       => wc_price( $amount ),
							'order_number' => $order ? $order->get_order_number() : '',
							'note'         => $args['note'],
						],
						(array) $args['email_vars']
					)
				);
			}

			do_action( 'ddwcpp_balance_recorded', $user_id, $args, $balance );

			return $balance;
		}

		/**
		 * Keep the installment rows in step with a balance change.
		 *
		 * Debits add rows (a plan's schedule, or one charge due later),
		 * refunds and cancellations shrink their order's rows, every other credit settles the
		 * oldest rows first.
		 *
		 * @param int   $user_id User ID.
		 * @param float $amount  Amount.
		 * @param array $args    Arguments of ddwcpp_record().
		 * @return void
		 */
		protected function ddwcpp_update_installments( $user_id, $amount, $args ) {
			$helper = $this->installments_helper;

			if ( 'debit' === $args['type'] ) {
				if ( ! empty( $args['schedule'] ) ) {
					$helper->ddwcpp_create( $user_id, $args['order_id'], $args['plan'], $args['schedule'] );
				} elseif ( false !== $args['schedule'] ) {
					$days = null === $args['due_days'] ? absint( isset( $this->ddwcpp_configuration['charge_due_days'] ) ? $this->ddwcpp_configuration['charge_due_days'] : 30 ) : absint( $args['due_days'] );
					$plan = ! empty( $args['plan'] ) ? $args['plan'] : [ 'title' => ddwcpp_get_translation( $args['reference'] ) ];

					$helper->ddwcpp_create(
						$user_id,
						$args['order_id'],
						$plan,
						[
							[
								'amount'   => $amount,
								'due_date' => $days ? wp_date( 'Y-m-d 23:59:59', strtotime( '+' . $days . ' days' ) ) : '',
							],
						]
					);
				}
			} else {
				$left = $amount;

				if ( in_array( $args['reference'], [ 'order_refund', 'order_cancelled' ], true ) && $args['order_id'] ) {
					$left = $helper->ddwcpp_reduce_order( $args['order_id'], $amount );
				}

				if ( $left > 0 ) {
					$helper->ddwcpp_allocate( $user_id, $left, $args['installment_id'], 'cleared_dues' === $args['reference'] ? $args['order_id'] : 0 );
				}
			}

			$this->ddwcpp_sync_due_date( $user_id );
		}

		/**
		 * Mirror the earliest pending due date into user meta.
		 *
		 * @param int $user_id User ID.
		 * @return void
		 */
		public function ddwcpp_sync_due_date( $user_id ) {
			$due_date = $this->installments_helper->ddwcpp_get_next_due_date( $user_id );

			if ( $due_date ) {
				update_user_meta( $user_id, '_ddwcpp_due_date', $due_date );
			} else {
				delete_user_meta( $user_id, '_ddwcpp_due_date' );
			}
		}
	}
}
