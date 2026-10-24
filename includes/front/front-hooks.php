<?php
/**
 * Front end hooks.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Front;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Front_Hooks' ) ) {
	/**
	 * Front hooks class
	 */
	class DDWCPP_Front_Hooks extends DDWCPP_Front_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			parent::__construct( $ddwcpp_configuration );

			if ( empty( $ddwcpp_configuration['enabled'] ) ) {
				return;
			}

			add_filter( 'woocommerce_account_menu_items', [ $this, 'ddwcpp_add_woocommerce_menu' ] );
			add_filter( 'woocommerce_get_query_vars', [ $this, 'ddwcpp_add_query_vars' ] );
			add_action( 'woocommerce_account_' . DDWCPP_POSTPAID_ENDPOINT . '_endpoint', [ $this, 'ddwcpp_add_postpaid_content_on_my_account_page' ] );
			add_filter( 'woocommerce_endpoint_' . DDWCPP_POSTPAID_ENDPOINT . '_title', [ $this, 'ddwcpp_change_endpoint_title' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'ddwcpp_front_scripts' ] );
			add_filter( 'sidebars_widgets', [ $this, 'ddwcpp_hide_sidebar_on_endpoint' ] );
			add_action( 'wp_loaded', [ $this, 'ddwcpp_handle_form_submissions' ], 20 );

			// Dues repayment cart.
			add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'ddwcpp_validate_add_to_cart' ], 10, 2 );
			add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'ddwcpp_restore_dues_cart_item' ], 10, 2 );
			add_action( 'woocommerce_before_calculate_totals', [ $this, 'ddwcpp_set_dues_price' ], 20 );
			add_filter( 'woocommerce_available_payment_gateways', [ $this, 'ddwcpp_filter_gateways_for_dues' ] );
			add_filter( 'woocommerce_cart_item_quantity', [ $this, 'ddwcpp_lock_dues_quantity' ], 10, 3 );

			add_action( 'woocommerce_review_order_before_payment', [ $this, 'ddwcpp_explain_unavailable_postpaid' ] );

			// Installment messaging.
			$locations = (array) $ddwcpp_configuration['message_locations'];

			if ( in_array( 'product', $locations, true ) ) {
				add_action( 'woocommerce_single_product_summary', [ $this, 'ddwcpp_product_message' ], 11 );
			}

			if ( in_array( 'loop', $locations, true ) ) {
				add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'ddwcpp_loop_message' ], 15 );
			}

			if ( in_array( 'cart', $locations, true ) ) {
				add_action( 'woocommerce_cart_totals_after_order_total', [ $this, 'ddwcpp_cart_message' ] );
			}

			add_shortcode( 'ddwcpp_installments_message', [ $this, 'ddwcpp_message_shortcode' ] );
			add_action( 'wp_footer', [ $this, 'ddwcpp_print_message_dialog' ] );
		}
	}
}
