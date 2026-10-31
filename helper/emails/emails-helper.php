<?php
/**
 * Emails helper.
 *
 * Every PostPaid email is an event with its own on/off switch, subject, heading and message,
 * edited under Configuration > Emails. Messages use placeholders that are filled when the email
 * is sent, then delivered through the single WooCommerce email class so the store's email
 * header, footer and colours apply.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Emails;

use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Emails_Helper' ) ) {
	/**
	 * Emails helper class
	 */
	class DDWCPP_Emails_Helper {
		/**
		 * Option holding the edited templates.
		 */
		const OPTION = '_ddwcpp_email_settings';

		/**
		 * Email events with their defaults.
		 *
		 * @return array event => [ label, description, recipient, placeholders, enabled, subject, heading, message ].
		 */
		public static function ddwcpp_get_events() {
			$thanks  = '<p>' . esc_html__( 'Thanks,', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '<br>{site_title}</p>';
			$account = '<p><a href="{account_url}">' . esc_html__( 'Go to my Pay Later account', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a></p>';

			return [
				'plan_confirmation'    => [
					'label'        => esc_html__( 'Payment Plan Confirmation', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when a customer checks out with Pay Later, with the full payment schedule.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{order_number}', '{plan_title}', '{plan_summary}', '{amount}', '{schedule}' ],
					'subject'      => esc_html__( 'Your payment plan for order #{order_number}', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Your payment plan is set', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Thank you for order #{order_number}. You are paying {amount} with {plan_title}: {plan_summary}.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>{schedule}<p>' . esc_html__( 'We will remind you before every payment. You can also pay early at any time.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $account . $thanks,
				],
				'payment_reminder'     => [
					'label'        => esc_html__( 'Payment Reminder', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent before an installment is due. The timing is set under Reminders & Fees.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{amount}', '{due_date}', '{payment_label}', '{order_number}', '{plan_title}', '{pay_url}' ],
					'subject'      => esc_html__( 'Your payment of {amount} is due on {due_date}', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Your payment is due soon', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Friendly reminder: {payment_label} of {amount} is due on {due_date}.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p><a href="{pay_url}">' . esc_html__( 'Pay now', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a></p>' . $thanks,
				],
				'payment_overdue'      => [
					'label'        => esc_html__( 'Overdue Payment', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent once, the day after a missed installment was due.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{amount}', '{due_date}', '{payment_label}', '{order_number}', '{pay_url}' ],
					'subject'      => esc_html__( 'Your payment of {amount} is overdue', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Your payment is overdue', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( '{payment_label} of {amount} was due on {due_date} and has not been paid yet.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Please pay as soon as you can to keep using Pay Later.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p><a href="{pay_url}">' . esc_html__( 'Pay now', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a></p>' . $thanks,
				],
				'payment_received'     => [
					'label'        => esc_html__( 'Payment Received', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when a repayment is paid, or when the store records a payment.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{amount}', '{order_number}' ],
					'subject'      => esc_html__( 'We received your payment of {amount}', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Thank you for your payment', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'We received your payment of {amount}. Thank you!', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'You now owe {amount_owed} and have {available_credit} available to spend. {next_payment_notice}', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $account . $thanks,
				],
				'credit_restored'      => [
					'label'        => esc_html__( 'Refund or Cancellation', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when a Pay Later order is refunded or cancelled and the amount comes off what the customer owes.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{amount}', '{order_number}', '{reason}' ],
					'subject'      => esc_html__( 'Your Pay Later balance was updated for order #{order_number}', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Your balance was updated', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( '{amount} for order #{order_number} was returned to your Pay Later credit ({reason}).', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'You now owe {amount_owed} and have {available_credit} available to spend.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $account . $thanks,
				],
				'credit_adjusted'      => [
					'label'        => esc_html__( 'Balance Adjusted by the Store', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when you add or remove dues by hand and choose to email the customer.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{amount}', '{adjustment}', '{note}' ],
					'subject'      => esc_html__( 'Your Pay Later balance was adjusted', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Your balance was adjusted', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'We {adjustment} {amount}: {note}', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'You now owe {amount_owed} and have {available_credit} available to spend.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $account . $thanks,
				],
				'application_approved' => [
					'label'        => esc_html__( 'Application Approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when you approve a customer.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [ '{credit_limit}' ],
					'subject'      => esc_html__( 'You are approved for Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Welcome to Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Good news: your Pay Later application is approved with a spending limit of {credit_limit}.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Choose Pay Later at checkout to split your next order into easy payments.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $account . $thanks,
				],
				'application_rejected' => [
					'label'        => esc_html__( 'Application Not Approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent when you reject a customer.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'customer',
					'placeholders' => [],
					'subject'      => esc_html__( 'An update on your Pay Later application', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'Application update', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( 'Hi {customer_name},', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'Thank you for applying for Pay Later. We are not able to approve your application right now.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p>' . esc_html__( 'If you have any questions, just reply to this email.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p>' . $thanks,
				],
				'new_application'      => [
					'label'        => esc_html__( 'New Application (Store)', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description'  => esc_html__( 'Sent to the store when a customer applies for Pay Later.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'recipient'    => 'admin',
					'placeholders' => [ '{customer_email}', '{review_url}' ],
					'subject'      => esc_html__( 'New Pay Later application from {customer_name}', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'heading'      => esc_html__( 'New Pay Later application', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'message'      => '<p>' . esc_html__( '{customer_name} ({customer_email}) applied for Pay Later.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</p><p><a href="{review_url}">' . esc_html__( 'Review applications', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a></p>',
				],
			];
		}

		/**
		 * Placeholders every email understands.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_common_placeholders() {
			return [ '{customer_name}', '{site_title}', '{account_url}', '{amount_owed}', '{available_credit}', '{credit_limit}' ];
		}

		/**
		 * Every email's switch and wording, falling back to the default wording.
		 *
		 * @return array event => [ enabled, subject, heading, message ].
		 */
		public static function ddwcpp_get_settings() {
			$saved    = get_option( self::OPTION, [] );
			$saved    = is_array( $saved ) ? $saved : [];
			$settings = [];

			foreach ( self::ddwcpp_get_events() as $event => $defaults ) {
				$row = isset( $saved[ $event ] ) && is_array( $saved[ $event ] ) ? $saved[ $event ] : [];

				$settings[ $event ] = [
					'enabled' => isset( $row['enabled'] ) ? $row['enabled'] : 'yes',
					'subject' => ! empty( $row['subject'] ) ? $row['subject'] : $defaults['subject'],
					'heading' => ! empty( $row['heading'] ) ? $row['heading'] : $defaults['heading'],
					'message' => ! empty( $row['message'] ) ? $row['message'] : $defaults['message'],
				];
			}

			return $settings;
		}

		/**
		 * Clean the submitted settings.
		 *
		 * @param mixed $value Submitted value.
		 * @return array
		 */
		public static function ddwcpp_sanitize_settings( $value ) {
			$clean = [];

			foreach ( array_keys( self::ddwcpp_get_events() ) as $event ) {
				$row = isset( $value[ $event ] ) && is_array( $value[ $event ] ) ? $value[ $event ] : [];

				$clean[ $event ] = [
					'enabled' => ! empty( $row['enabled'] ) && 'yes' === $row['enabled'] ? 'yes' : '',
					'subject' => isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : '',
					'heading' => isset( $row['heading'] ) ? sanitize_text_field( $row['heading'] ) : '',
					'message' => isset( $row['message'] ) ? wp_kses_post( $row['message'] ) : '',
				];
			}

			return $clean;
		}

		/**
		 * Send an event email.
		 *
		 * @param string $event   Event key.
		 * @param int    $user_id Customer the email is about.
		 * @param array  $vars    Event placeholders, keyed without braces, e.g. [ 'amount' => '$10.00' ].
		 * @return bool Whether the email was handed to WooCommerce.
		 */
		public static function ddwcpp_send( $event, $user_id, $vars = [] ) {
			$events   = self::ddwcpp_get_events();
			$settings = self::ddwcpp_get_settings();
			$user     = get_userdata( $user_id );

			if ( ! isset( $events[ $event ] ) || empty( $settings[ $event ]['enabled'] ) || ! $user ) {
				return false;
			}

			$replace = self::ddwcpp_get_replacements( $user, $vars );
			$fill    = function ( $text ) use ( $replace ) {
				return strtr( $text, $replace );
			};
			$plain   = function ( $text ) use ( $fill ) {
				// Subjects and headings are plain text, so prices lose their markup.
				return html_entity_decode( wp_strip_all_tags( $fill( $text ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
			};

			do_action(
				'ddwcpp_mail',
				[
					'email'   => 'admin' === $events[ $event ]['recipient'] ? get_option( 'admin_email' ) : $user->user_email,
					'name'    => 'admin' === $events[ $event ]['recipient'] ? '' : $user->display_name,
					'subject' => $plain( $settings[ $event ]['subject'] ),
					'heading' => $plain( $settings[ $event ]['heading'] ),
					'message' => wpautop( $fill( $settings[ $event ]['message'] ) ),
					'event'   => $event,
				]
			);

			return true;
		}

		/**
		 * Placeholder values for one email.
		 *
		 * @param \WP_User $user User.
		 * @param array    $vars Event values.
		 * @return array
		 */
		protected static function ddwcpp_get_replacements( $user, $vars ) {
			global $ddwcpp_configuration;

			$account = ( new DDWCPP_Users_Helper( $ddwcpp_configuration ) )->ddwcpp_get_account( $user->ID );
			$next    = '';

			if ( $account['due_date'] && $account['next_amount'] > 0 ) {
				/* translators: %1$s: amount, %2$s: date. */
				$next = sprintf( esc_html__( 'Your next payment is %1$s on %2$s.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wc_price( $account['next_amount'] ), date_i18n( wc_date_format(), strtotime( $account['due_date'] ) ) );
			}

			$values = array_merge(
				[
					'customer_name'       => $user->display_name,
					'customer_email'      => $user->user_email,
					'site_title'          => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
					'account_url'         => esc_url( ddwcpp_get_account_url() ),
					'pay_url'             => esc_url( ddwcpp_get_account_url() ),
					'amount_owed'         => wc_price( $account['dues'] ),
					'available_credit'    => wc_price( max( 0, $account['balance'] ) ),
					'credit_limit'        => wc_price( $account['limit'] ),
					'next_payment_notice' => $next,
				],
				(array) $vars
			);

			$replace = [];

			foreach ( $values as $key => $value ) {
				$replace[ '{' . $key . '}' ] = (string) $value;
			}

			return $replace;
		}
	}
}
