<?php
/**
 * Plugin Name: DevDiggers Buy Now Pay Later for WooCommerce
 * Description: Run your own buy now, pay later program. Offer Pay in 4 and pay in 30 days on a store credit line you control, show installment pricing on product pages, approve customers and collect every payment with automatic reminders.
 * Plugin URI: https://devdiggers.com/product/woocommerce-buy-now-pay-later/
 * Author: DevDiggers
 * Author URI: https://devdiggers.com/
 * Version: 1.0.0
 * Text Domain: devdiggers-buy-now-pay-later-for-woocommerce
 * Domain Path: /i18n
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * WC tested up to: 11.2
 * DevDiggersPrefix: ddwcpp
 * Requires Plugins: woocommerce
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package DevDiggers Buy Now Pay Later for WooCommerce
 */

// ddwcpp: DevDiggers Buy Now Pay Later for WooCommerce.

use DDWCPostPaid\Includes\DDWCPP_File_Handler;

defined( 'ABSPATH' ) || exit();

/**
 * Point the plugin constants at this directory.
 *
 * @return void
 */
function ddwcpp_free_define_constants() {
	defined( 'DDWCPP_PLUGIN_FILE' ) || define( 'DDWCPP_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
	defined( 'DDWCPP_PLUGIN_URL' ) || define( 'DDWCPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	defined( 'DDWCPP_PLUGIN_SLUG' ) || define( 'DDWCPP_PLUGIN_SLUG', 'devdiggers-buy-now-pay-later-for-woocommerce' );
	defined( 'DDWCPP_DASHBOARD_PAGE' ) || define( 'DDWCPP_DASHBOARD_PAGE', 'ddwcpp-dashboard' );
	defined( 'DDWCPP_POSTPAID_ENDPOINT' ) || define( 'DDWCPP_POSTPAID_ENDPOINT', sanitize_title( get_option( '_ddwcpp_my_account_endpoint' ) ) ? sanitize_title( get_option( '_ddwcpp_my_account_endpoint' ) ) : 'pay-later' );
}

if ( ! class_exists( 'DDWCPP_Free_Init' ) ) {
	/**
	 * Free Init class.
	 */
	final class DDWCPP_Free_Init {
		/**
		 * The single instance of this class.
		 *
		 * @var DDWCPP_Free_Init|null
		 */
		private static $instance = null;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'init', [ $this, 'ddwcpp_init' ], 0 );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'ddwcpp_plugin_settings_link' ] );
			add_filter( 'plugin_row_meta', [ $this, 'ddwcpp_plugin_row_meta' ], 10, 2 );
		}

		/**
		 * Create a plugin instance.
		 *
		 * @return static
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();

				/**
				 * Fires when the main plugin instance is loaded.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ddwcpp_loaded' );
			}

			return self::$instance;
		}

		/**
		 * Init function.
		 *
		 * @return void
		 */
		public function ddwcpp_init() {
			// WordPress.org loads plugin translations automatically.
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action(
					'admin_notices',
					function () {
						?>
						<div class="notice notice-error">
							<p>
								<?php
								/* translators: %1$s: opening link tag, %2$s: closing link tag */
								printf( esc_html__( 'DevDiggers Buy Now Pay Later for WooCommerce is activated but not effective. It requires %1$sWooCommerce%2$s in order to work.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), '<a href="' . esc_url( 'https://wordpress.org/plugins/woocommerce/' ) . '" target="_blank">', '</a>' );
								?>
							</p>
						</div>
						<?php
					}
				);

				return;
			}

			require_once DDWCPP_PLUGIN_FILE . 'autoload/autoload.php';
			require_once DDWCPP_PLUGIN_FILE . 'includes/global-functions.php';
			require_once DDWCPP_PLUGIN_FILE . 'includes/install.php';

			new DDWCPP_File_Handler();

			// The review notice only belongs on this plugin's own screen and its dismiss request.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			$on_plugin_page = is_admin() && isset( $_GET['page'] ) && DDWCPP_DASHBOARD_PAGE === sanitize_key( wp_unslash( $_GET['page'] ) );

			if ( ( $on_plugin_page || wp_doing_ajax() ) && class_exists( '\DevDiggers\Framework\Includes\DDFW_Review_Notice' ) ) {
				new \DevDiggers\Framework\Includes\DDFW_Review_Notice(
					[
						'plugin_name'   => esc_html__( 'DevDiggers Buy Now Pay Later for WooCommerce', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'plugin_prefix' => 'ddwcpp',
						'review_url'    => 'https://wordpress.org/support/plugin/devdiggers-buy-now-pay-later-for-woocommerce/reviews/#new-post',
					]
				);
			}
		}

		/**
		 * Plugin settings link.
		 *
		 * @param array $links Links array.
		 * @return array
		 */
		public function ddwcpp_plugin_settings_link( $links ) {
			$action_links = [
				'dashboard'     => '<a href="' . esc_url( admin_url( 'admin.php?page=' . DDWCPP_DASHBOARD_PAGE ) ) . '">' . esc_html__( 'Dashboard', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
				'configuration' => '<a href="' . esc_url( admin_url( 'admin.php?page=' . DDWCPP_DASHBOARD_PAGE . '&menu=configuration' ) ) . '">' . esc_html__( 'Configuration', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
				'setup-wizard'  => '<a href="' . esc_url( admin_url( 'admin.php?page=' . DDWCPP_DASHBOARD_PAGE . '&setup-wizard=true' ) ) . '">' . esc_html__( 'Setup Wizard', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
				'upgrade'       => '<a href="' . esc_url( 'https://devdiggers.com/product/woocommerce-buy-now-pay-later/' ) . '" target="_blank">' . esc_html__( 'Upgrade to Pro', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
			];

			return array_merge( $action_links, $links );
		}

		/**
		 * Plugin row meta links.
		 *
		 * @param array  $links Links.
		 * @param string $file  Plugin file.
		 * @return array
		 */
		public function ddwcpp_plugin_row_meta( $links, $file ) {
			if ( plugin_basename( __FILE__ ) === $file ) {
				$row_meta = [
					'support'       => '<a href="https://devdiggers.com/contact/" aria-label="' . esc_attr__( 'Support', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '">' . esc_html__( 'Support', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
					'documentation' => '<a href="https://docs.devdiggers.com/woocommerce-postpaid/" aria-label="' . esc_attr__( 'Documentation', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '">' . esc_html__( 'Documentation', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '</a>',
					'review'        => '<a href="https://wordpress.org/support/plugin/devdiggers-buy-now-pay-later-for-woocommerce/reviews/#new-post" target="_blank" title="' . esc_attr__( 'Review', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'Review', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '">' . self::ddwcpp_get_review_stars_svg() . '</a>',
				];

				$links = array_merge( $links, $row_meta );
			}

			return $links;
		}

		/**
		 * Five star rating graphic used in the plugin row and admin footer.
		 *
		 * @return string
		 */
		public static function ddwcpp_get_review_stars_svg() {
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 32" height="10"><path d="M16 26.534L6.111 32 8 20.422l-8-8.2 11.056-1.688L16 0l4.944 10.534L32 12.223l-8 8.2L25.889 32zm40 0L46.111 32 48 20.422l-8-8.2 11.056-1.688L56 0l4.944 10.534L72 12.223l-8 8.2L65.889 32zm40 0L86.111 32 88 20.422l-8-8.2 11.056-1.688L96 0l4.944 10.534L112 12.223l-8 8.2L105.889 32zm40 0L126.111 32 128 20.422l-8-8.2 11.056-1.688L136 0l4.944 10.534L152 12.223l-8 8.2L145.889 32zm40 0L166.111 32 168 20.422l-8-8.2 11.056-1.688L176 0l4.944 10.534L192 12.223l-8 8.2L185.889 32z" fill="#F5A623" fill-rule="evenodd"/></svg>';
		}
	}
}

// Load the Free version and the DevDiggers Framework only when the Pro plugin is not active.
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'DDWCPP_Init' ) ) {
			return;
		}

		ddwcpp_free_define_constants();

		DDWCPP_Free_Init::get_instance();

		// Load DevDiggers Framework if not loaded already.
		if ( ! defined( 'DDFW_LOADED' ) && file_exists( DDWCPP_PLUGIN_FILE . 'devdiggers-framework/init.php' ) ) {
			$should_load = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
			if ( ! empty( $_GET['page'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing input.
				$current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				$prefix       = explode( '-', $current_page )[0];

				if ( 0 === strpos( $prefix, 'ddwc' ) || 0 === strpos( $prefix, 'ddwp' ) ) {
					$pro_class  = strtoupper( $prefix ) . '_Init';
					$free_class = strtoupper( $prefix ) . '_Free_Init';

					// Yield to a sibling DevDiggers plugin except on this plugin's own pages.
					if ( class_exists( $free_class ) && ! class_exists( $pro_class ) && 'ddwcpp' !== $prefix ) {
						$should_load = false;
					}
				}
			}

			if ( $should_load ) {
				require DDWCPP_PLUGIN_FILE . 'devdiggers-framework/init.php';
			}
		}
	},
	10
);

register_activation_hook(
	__FILE__,
	function () {
		ddwcpp_free_define_constants();

		require_once DDWCPP_PLUGIN_FILE . 'includes/install.php';

		DDWCPP_Install::ddwcpp_on_plugin_activation();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		// By hook name: the autoloader may not be loaded when WooCommerce is already inactive.
		wp_clear_scheduled_hook( 'ddwcpp_daily_event' );
	}
);

// HPOS and Cart/Checkout Blocks compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
