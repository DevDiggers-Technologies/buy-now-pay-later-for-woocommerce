<?php
/**
 * This file handles all admin end action hooks.
 *
 * @author DevDiggers
 * @version 2.0.0
 * @package WooCommerce PostPaid
 */

namespace DDWCPostPaid\Includes\Admin;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Admin_Hooks' ) ) {
	/**
	 * Admin end hook handler class
	 */
	class DDWCPP_Admin_Hooks extends DDWCPP_Admin_Functions {
		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			parent::__construct( $ddwcpp_configuration );

			add_action( 'admin_init', [ $this, 'ddwcpp_register_settings' ] );
			add_action( 'admin_init', [ $this, 'ddwcpp_process_customer_actions' ] );
			add_action( 'admin_init', [ $this, 'ddwcpp_process_installment_actions' ] );
			add_action( 'add_meta_boxes', [ $this, 'ddwcpp_add_order_meta_box' ], 10, 2 );

			add_filter( 'set-screen-option', [ $this, 'ddwcpp_set_screen_option' ], 10, 3 );
			add_filter( 'set_screen_option_ddwcpp_customers_per_page', [ $this, 'ddwcpp_set_screen_option' ], 10, 3 );
			add_filter( 'set_screen_option_ddwcpp_transactions_per_page', [ $this, 'ddwcpp_set_screen_option' ], 10, 3 );
			add_filter( 'set_screen_option_ddwcpp_installments_per_page', [ $this, 'ddwcpp_set_screen_option' ], 10, 3 );

			add_action( 'wp_ajax_ddwcpp_get_users_list', [ $this, 'ddwcpp_ajax_get_users_list' ] );
			add_action( 'wp_ajax_ddwcpp_adjust_balance', [ $this, 'ddwcpp_ajax_adjust_balance' ] );
		}
	}
}
