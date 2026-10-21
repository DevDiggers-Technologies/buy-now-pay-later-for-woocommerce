<?php
/**
 * Hooks that run on both admin and front end requests.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Common;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Common_Hooks' ) ) {
	/**
	 * Common hooks class
	 */
	class DDWCPP_Common_Hooks extends DDWCPP_Common_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			parent::__construct( $ddwcpp_configuration );

			add_filter( 'woocommerce_payment_gateways', [ $this, 'ddwcpp_add_payment_gateway' ] );
			add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'ddwcpp_register_block_payment_method' ] );

			add_filter( 'woocommerce_email_classes', [ $this, 'ddwcpp_add_new_email_notification' ] );
			add_filter( 'woocommerce_email_actions', [ $this, 'ddwcpp_add_notification_actions' ] );

			add_action( 'init', [ $this, 'ddwcpp_add_endpoints' ] );

			// Dues are credited once the repayment order is paid, whichever paid status it lands on.
			add_action( 'woocommerce_order_status_processing', [ $this, 'ddwcpp_credit_paid_dues' ] );
			add_action( 'woocommerce_order_status_completed', [ $this, 'ddwcpp_credit_paid_dues' ] );

			add_action( 'woocommerce_order_status_cancelled', [ $this, 'ddwcpp_restore_credit_on_cancel' ] );
			add_action( 'woocommerce_order_status_failed', [ $this, 'ddwcpp_restore_credit_on_cancel' ] );

			add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'ddwcpp_save_dues_amount_on_item' ], 10, 3 );
			add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'ddwcpp_hide_order_item_meta' ] );

			// The dues product always shows the plugin icon, never a media library image.
			add_filter( 'woocommerce_product_get_image', [ $this, 'ddwcpp_dues_product_image' ], 10, 2 );
			add_filter( 'woocommerce_store_api_cart_item_images', [ $this, 'ddwcpp_dues_cart_item_images' ], 10, 2 );

			add_action( \DDWCPP_Install::CRON_HOOK, [ $this, 'ddwcpp_process_payment_terms' ] );
			add_action( 'ddwcpp_installment_paid', [ $this, 'ddwcpp_on_installment_paid' ] );

			add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'ddwcpp_register_privacy_exporter' ] );
		}
	}
}
