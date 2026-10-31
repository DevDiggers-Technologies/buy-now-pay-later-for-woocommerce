<?php
/**
 * File handler
 *
 * @author DevDiggers
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_File_Handler' ) ) {
	/**
	 * File handler class
	 */
	class DDWCPP_File_Handler {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->ddwcpp_configuration = $this->ddwcpp_set_globals();

			if ( is_admin() ) {
				new DDWCPP_Admin_Dashboard( $this->ddwcpp_configuration );
				new Admin\DDWCPP_Admin_Hooks( $this->ddwcpp_configuration );
				new Admin\DDWCPP_Setup_Wizard();
			}

			// Gateway, emails, order status hooks and the cron run on every request type:
			// a refund or cancellation from the admin must move the balance just like a checkout.
			new Common\DDWCPP_Common_Hooks( $this->ddwcpp_configuration );

			if ( ! is_admin() || wp_doing_ajax() ) {
				new Front\DDWCPP_Front_Hooks( $this->ddwcpp_configuration );
			}
		}

		/**
		 * Set globals function
		 *
		 * @return array
		 */
		public function ddwcpp_set_globals() {
			global $ddwcpp_configuration;

			$limit      = get_option( '_ddwcpp_limit' );
			$menu_title = get_option( '_ddwcpp_menu_title' );
			$mode       = get_option( '_ddwcpp_verification_mode', 'manual' );
			// Text settings show their default in the field and fall back to it when cleared.
			$text = function ( $option, $fallback ) {
				$value = get_option( $option, '' );
				return '' !== trim( (string) $value ) ? $value : $fallback;
			};

			$ddwcpp_configuration = [
				'enabled'                 => get_option( '_ddwcpp_enabled' ),

				// Eligibility.
				'limit'                   => is_numeric( $limit ) && $limit > 0 ? (float) $limit : 1000,
				// A store coming from Pro may have the document mode saved; it falls back to manual review.
				'verification_mode'       => array_key_exists( $mode, ddwcpp_get_verification_mode_options() ) ? $mode : 'manual',
				'auto_activate'           => get_option( '_ddwcpp_auto_activate' ),
				'min_order_amount'        => (float) get_option( '_ddwcpp_min_order_amount' ),
				'max_order_amount'        => (float) get_option( '_ddwcpp_max_order_amount' ),
				'restore_on_cancel'       => get_option( '_ddwcpp_restore_on_cancel', 'yes' ),

				// Reminders.
				'charge_due_days'         => absint( get_option( '_ddwcpp_charge_due_days', 30 ) ),
				'reminder_days'           => absint( get_option( '_ddwcpp_reminder_days' ) ),
				'block_overdue'           => get_option( '_ddwcpp_block_overdue' ),

				// Display.
				'menu_title'              => ! empty( $menu_title ) ? $menu_title : esc_html__( 'Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				'show_credit_at_checkout' => get_option( '_ddwcpp_show_credit_at_checkout', 'yes' ),

				// My Account.
				'my_account_endpoint'     => DDWCPP_POSTPAID_ENDPOINT,
				'sidebar_widgets'         => get_option( '_ddwcpp_sidebar_widgets' ),

				// Installment messaging.
				'message_enabled'         => get_option( '_ddwcpp_message_enabled' ),
				'message_locations'       => (array) get_option( '_ddwcpp_message_locations', [ 'product', 'cart' ] ),
				'message_text'            => $text( '_ddwcpp_message_text', ddwcpp_get_default_message_text() ),
				'message_text_single'     => $text( '_ddwcpp_message_text_single', ddwcpp_get_default_message_text( true ) ),
			];

			return $ddwcpp_configuration;
		}
	}
}
