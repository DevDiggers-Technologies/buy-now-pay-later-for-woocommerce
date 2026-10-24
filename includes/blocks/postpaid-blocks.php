<?php
/**
 * PostPaid payment method for the Cart and Checkout blocks.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_PostPaid_Blocks' ) ) {
	/**
	 * Blocks integration class
	 */
	final class DDWCPP_PostPaid_Blocks extends AbstractPaymentMethodType {
		/**
		 * Payment method name, matching the gateway ID.
		 *
		 * @var string
		 */
		protected $name = 'ddwcpp_postpaid';

		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
		}

		/**
		 * Load the gateway settings.
		 *
		 * @return void
		 */
		public function initialize() {
			$this->settings = get_option( 'woocommerce_ddwcpp_postpaid_settings', [] );
		}

		/**
		 * The server decides availability through the gateway's is_available().
		 *
		 * @return bool
		 */
		public function is_active() {
			return ! empty( $this->ddwcpp_configuration['enabled'] ) && 'no' !== $this->get_setting( 'enabled', 'yes' );
		}

		/**
		 * Script that registers the method in the Checkout block.
		 *
		 * @return array
		 */
		public function get_payment_method_script_handles() {
			wp_register_script(
				'ddwcpp-blocks',
				DDWCPP_PLUGIN_URL . 'assets/js/blocks.js',
				[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
				filemtime( DDWCPP_PLUGIN_FILE . 'assets/js/blocks.js' ),
				true
			);

			return [ 'ddwcpp-blocks' ];
		}

		/**
		 * Data the script reads.
		 *
		 * @return array
		 */
		public function get_payment_method_data() {
			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
			$gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;

			$plans = [];

			foreach ( \DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper::ddwcpp_get_active_plans() as $plan ) {
				$plans[] = [
					'id'       => $plan['id'],
					'title'    => $plan['title'],
					'count'    => (int) $plan['count'],
					'upfront'  => ! empty( $plan['upfront'] ),
					'interval' => \DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper::ddwcpp_get_interval_label( $plan ),
					'term'     => \DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper::ddwcpp_get_term_label( $plan ),
				];
			}

			$user_id = get_current_user_id();

			return [
				'title'       => $this->get_setting( 'title', esc_html__( 'Buy Now, Pay Later', 'buy-now-pay-later-for-woocommerce' ) ),
				'description' => $this->get_setting( 'description' ),
				'credit'      => $gateway && ! is_admin() ? wp_strip_all_tags( html_entity_decode( $gateway->ddwcpp_get_credit_notice() ) ) : '',
				'balance'     => $user_id && ! is_admin() ? ( new \DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper( $this->ddwcpp_configuration ) )->ddwcpp_get_balance( $user_id ) : 0,
				'plans'       => $plans,
				'i18n'        => [
					'legend'   => esc_html__( 'Choose how to pay', 'buy-now-pay-later-for-woocommerce' ),
					/* translators: %1$d: number of payments, %2$s: amount of each, %3$s: interval. */
					'payments' => esc_html__( '%1$d payments of %2$s %3$s', 'buy-now-pay-later-for-woocommerce' ),
					/* translators: %1$s: amount, %2$s: term, e.g. "in 30 days". */
					'single'   => esc_html__( 'Pay %1$s %2$s', 'buy-now-pay-later-for-woocommerce' ),
					/* translators: %s: amount. */
					'today'    => esc_html__( '%s due today', 'buy-now-pay-later-for-woocommerce' ),
					'noFee'    => esc_html__( 'No fees', 'buy-now-pay-later-for-woocommerce' ),
				],
				'supports'    => [ 'products', 'refunds' ],
			];
		}
	}
}
