<?php
/**
 * Small helpers shared by admin screens, the wizard, the gateway and the storefront.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit();

if ( ! function_exists( 'ddwcpp_get_dashboard_url' ) ) {
	/**
	 * URL of a screen inside the plugin dashboard.
	 *
	 * @param array $args Query arguments, e.g. [ 'menu' => 'customers' ].
	 * @return string
	 */
	function ddwcpp_get_dashboard_url( $args = [] ) {
		return add_query_arg( array_merge( [ 'page' => DDWCPP_DASHBOARD_PAGE ], $args ), admin_url( 'admin.php' ) );
	}
}

if ( ! function_exists( 'ddwcpp_get_account_url' ) ) {
	/**
	 * URL of the PostPaid section in My Account.
	 *
	 * @param string $value Optional endpoint value, e.g. a page number or 'view/12'.
	 * @return string
	 */
	function ddwcpp_get_account_url( $value = '' ) {
		return wc_get_endpoint_url( DDWCPP_POSTPAID_ENDPOINT, $value, wc_get_page_permalink( 'myaccount' ) );
	}
}

if ( ! function_exists( 'ddwcpp_get_dues_product_id' ) ) {
	/**
	 * ID of the hidden product used to pay dues.
	 *
	 * @return int
	 */
	function ddwcpp_get_dues_product_id() {
		$product_id = absint( get_option( '_ddwcpp_dues_product_id' ) );

		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			$product_id = DDWCPP_Install::ddwcpp_create_product();
		}

		return $product_id;
	}
}

if ( ! function_exists( 'ddwcpp_get_default_message_text' ) ) {
	/**
	 * Default installment message.
	 *
	 * @param bool $single For plans paid in one payment.
	 * @return string
	 */
	function ddwcpp_get_default_message_text( $single = false ) {
		return $single ? esc_html__( 'or buy now and pay {amount} {interval}', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : esc_html__( 'or {count} payments of {amount} {interval}', 'devdiggers-buy-now-pay-later-for-woocommerce' );
	}
}

if ( ! function_exists( 'ddwcpp_get_default_dialog_steps' ) ) {
	/**
	 * Default "how it works" steps of the Learn more dialog.
	 *
	 * @return string[]
	 */
	function ddwcpp_get_default_dialog_steps() {
		return [
			esc_html__( 'Choose Pay Later at checkout and pick the plan that suits you.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			esc_html__( 'Get your order. No card or bank details needed today.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			esc_html__( 'Pay each installment from your account. We remind you before every due date.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
		];
	}
}

if ( ! function_exists( 'ddwcpp_get_verification_mode_options' ) ) {
	/**
	 * How a customer becomes eligible.
	 *
	 * @return array
	 */
	function ddwcpp_get_verification_mode_options() {
		return [
			'manual' => esc_html__( 'Administrator approves without a document', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'none'   => esc_html__( 'No verification, every customer is eligible', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
		];
	}
}

if ( ! function_exists( 'ddwcpp_get_message_location_options' ) ) {
	/**
	 * Where the installment message can show.
	 *
	 * @return array
	 */
	function ddwcpp_get_message_location_options() {
		return [
			'product' => esc_html__( 'Product page, under the price', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'loop'    => esc_html__( 'Shop and category pages', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'cart'    => esc_html__( 'Cart, under the total', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
		];
	}
}

if ( ! function_exists( 'ddwcpp_get_translation' ) ) {
	/**
	 * Translate one of the plugin's stored keys for display.
	 *
	 * @param string $key Stored key.
	 * @return string
	 */
	function ddwcpp_get_translation( $key ) {
		$strings = [
			// Transaction types.
			'credit'          => esc_html__( 'Credit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'debit'           => esc_html__( 'Debit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			// Transaction references.
			'order_debit'     => esc_html__( 'Order Purchase', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'order_refund'    => esc_html__( 'Order Refund', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'order_cancelled' => esc_html__( 'Order Cancelled', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'cleared_dues'    => esc_html__( 'Dues Repayment', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'manual_credit'   => esc_html__( 'Manual Credit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'manual_debit'    => esc_html__( 'Manual Debit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'late_fee'        => esc_html__( 'Late Fee', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'plan_fee'        => esc_html__( 'Plan Fee', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'manual_payment'  => esc_html__( 'Payment Recorded', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			// Installment statuses.
			'paid'            => esc_html__( 'Paid', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'active'          => esc_html__( 'Active', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'cancelled'       => esc_html__( 'Cancelled', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'upcoming'        => esc_html__( 'Upcoming', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'due_soon'        => esc_html__( 'Due Soon', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			// Verification statuses.
			'pending'         => esc_html__( 'Pending', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'eligible'        => esc_html__( 'Approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'rejected'        => esc_html__( 'Rejected', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'not_applied'     => esc_html__( 'Not Applied', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
			'overdue'         => esc_html__( 'Overdue', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
		];

		$strings = apply_filters( 'ddwcpp_modify_translations', $strings );

		return isset( $strings[ $key ] ) ? $strings[ $key ] : $key;
	}
}

if ( ! function_exists( 'ddwcpp_get_brand_mark' ) ) {
	/**
	 * Brand mark shown beside the plugin name in the dashboard header.
	 *
	 * @return string
	 */
	function ddwcpp_get_brand_mark() {
		return '<svg width="32" height="32" viewBox="0 0 30 30" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><circle cx="15" cy="15" r="14" fill="var(--ddfw-tab-background-color)"/><rect x="6" y="9" width="18" height="12" rx="2" stroke="var(--ddfw-primary-color)" stroke-width="1.6"/><path d="M6 13h18" stroke="var(--ddfw-primary-color)" stroke-width="1.6"/><path d="M9.5 17.5h4" stroke="var(--ddfw-primary-color)" stroke-width="1.6" stroke-linecap="round"/><circle cx="21.5" cy="20.5" r="3.5" fill="var(--ddfw-primary-color)"/><path d="M21.5 19v1.6l1 .7" stroke="#fff" stroke-width="1" stroke-linecap="round"/></svg>';
	}
}

if ( ! function_exists( 'ddwcpp_get_allowed_html' ) ) {
	/**
	 * Post HTML plus the inline SVG the plugin's icons use, for wp_kses().
	 *
	 * @return array
	 */
	function ddwcpp_get_allowed_html() {
		$shape = [
			'd'               => true,
			'x'               => true,
			'y'               => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'rx'              => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		];

		return array_merge(
			wp_kses_allowed_html( 'post' ),
			[
				'svg'    => [
					'xmlns'           => true,
					'width'           => true,
					'height'          => true,
					'viewbox'         => true,
					'fill'            => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
					'aria-hidden'     => true,
					'focusable'       => true,
					'class'           => true,
				],
				'path'   => $shape,
				'rect'   => $shape,
				'circle' => $shape,
			]
		);
	}
}

if ( ! function_exists( 'ddwcpp_get_upgrade_card' ) ) {
	/**
	 * Upgrade card for a Pro feature, with its screenshot when one ships in assets/images/pro.
	 *
	 * @param string $image Screenshot name without extension.
	 * @param array  $args  Heading, description and list_features.
	 * @return string
	 */
	function ddwcpp_get_upgrade_card( $image, $args ) {
		$file = 'assets/images/pro/' . sanitize_file_name( $image ) . '.webp';

		return ddfw_get_upgrade_to_pro_section(
			array_merge(
				$args,
				[
					'image_url'   => file_exists( DDWCPP_PLUGIN_FILE . $file ) ? DDWCPP_PLUGIN_URL . $file : '',
					'upgrade_url' => 'https://devdiggers.com/product/woocommerce-buy-now-pay-later/',
				]
			)
		);
	}
}
