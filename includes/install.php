<?php
/**
 * Activation handler. This is the first release, so there is nothing to upgrade or migrate.
 *
 * @package DevDiggers Buy Now Pay Later for WooCommerce
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Install' ) ) {
	/**
	 * Install class
	 */
	class DDWCPP_Install {
		/**
		 * Daily cron hook that sends payment reminders and overdue notices.
		 */
		const CRON_HOOK = 'ddwcpp_daily_event';

		/**
		 * On plugin activation function
		 *
		 * @return void
		 */
		public static function ddwcpp_on_plugin_activation() {
			add_option( 'ddwcpp_installed_at', time() );

			// A store that already has settings (for example from Pro) skips the setup wizard.
			if ( false !== get_option( '_ddwcpp_enabled', false ) ) {
				update_option( 'ddfw_setup_wizard_completed_' . DDWCPP_PLUGIN_SLUG, true );
			} else {
				// Saved, not just defaulted, so Pro keeps the same My Account address.
				add_option( '_ddwcpp_my_account_endpoint', 'pay-later' );
				set_transient( 'ddfw_activation_redirect_' . DDWCPP_PLUGIN_SLUG, true, 30 );
			}

			self::ddwcpp_install();
			set_transient( 'ddwcpp_flush_rewrite_rules', true, MINUTE_IN_SECONDS );
		}

		/**
		 * Create the schema, the dues product, defaults and the cron event.
		 *
		 * @return void
		 */
		public static function ddwcpp_install() {
			// The activation hook runs before the plugin's init loads these.
			require_once DDWCPP_PLUGIN_FILE . 'autoload/autoload.php';
			require_once DDWCPP_PLUGIN_FILE . 'includes/global-functions.php';

			self::ddwcpp_create_schema();

			// Sensible starting point so the plugin is not a blank screen on first run.
			add_option( '_ddwcpp_limit', '1000' );
			add_option( '_ddwcpp_verification_mode', 'manual' );
			add_option( '_ddwcpp_charge_due_days', '30' );
			add_option( '_ddwcpp_reminder_days', '3' );
			add_option( '_ddwcpp_message_enabled', 'yes' );
			add_option( '_ddwcpp_message_locations', [ 'product', 'cart' ] );
			add_option( '_ddwcpp_block_overdue', 'yes' );
			add_option( '_ddwcpp_restore_on_cancel', 'yes' );
			add_option( '_ddwcpp_show_credit_at_checkout', 'yes' );

			if ( class_exists( 'WC_Product_Simple' ) ) {
				self::ddwcpp_create_product();
			}

			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( strtotime( 'tomorrow 09:00' ), 'daily', self::CRON_HOOK );
			}
		}

		/**
		 * Create or upgrade the transactions table.
		 *
		 * @return void
		 */
		public static function ddwcpp_create_schema() {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();
			$table           = $wpdb->prefix . 'ddwcpp_transactions';

			// dbDelta format: two spaces before PRIMARY KEY, one key per line, no IF NOT EXISTS.
			dbDelta(
				"CREATE TABLE $table (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				order_id bigint(20) DEFAULT NULL,
				reference varchar(100) NOT NULL,
				user_id bigint(20) NOT NULL,
				amount varchar(100) NOT NULL,
				type varchar(50) NOT NULL,
				date datetime NOT NULL,
				note varchar(250),
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY date (date)
				) $charset_collate;"
			);

			$table = $wpdb->prefix . 'ddwcpp_installments';

			dbDelta(
				"CREATE TABLE $table (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				user_id bigint(20) NOT NULL,
				order_id bigint(20) NOT NULL DEFAULT 0,
				plan_id varchar(60) NOT NULL DEFAULT '',
				plan_title varchar(200) NOT NULL DEFAULT '',
				sequence smallint(5) NOT NULL DEFAULT 1,
				total_count smallint(5) NOT NULL DEFAULT 1,
				amount decimal(19,4) NOT NULL DEFAULT 0,
				paid_amount decimal(19,4) NOT NULL DEFAULT 0,
				late_fee decimal(19,4) NOT NULL DEFAULT 0,
				due_date datetime DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				paid_date datetime DEFAULT NULL,
				payment_order_id bigint(20) NOT NULL DEFAULT 0,
				reminder_sent tinyint(1) NOT NULL DEFAULT 0,
				overdue_notified tinyint(1) NOT NULL DEFAULT 0,
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_status (user_id,status),
				KEY order_id (order_id),
				KEY status_due (status,due_date)
				) $charset_collate;"
			);
		}

		/**
		 * Create the hidden product customers buy to clear their dues.
		 *
		 * @return int Product ID.
		 */
		public static function ddwcpp_create_product() {
			$product_id = absint( get_option( '_ddwcpp_dues_product_id' ) );

			if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
				// A store coming from Pro may already have it.
				$existing   = get_page_by_path( 'ddwcpp-postpaid-dues', OBJECT, 'product' );
				$product_id = $existing ? $existing->ID : 0;
			}

			if ( $product_id ) {
				// Older stores named it "PostPaid Dues"; bring it in line with the plugin name.
				$product = wc_get_product( $product_id );
				$name    = esc_html__( 'Pay Later Dues', 'devdiggers-buy-now-pay-later-for-woocommerce' );

				if ( $product && $name !== $product->get_name() ) {
					$product->set_name( $name );
					$product->save();
				}

				update_option( '_ddwcpp_dues_product_id', $product_id );
				return $product_id;
			}

			$product = new WC_Product_Simple();
			$product->set_name( esc_html__( 'Pay Later Dues', 'devdiggers-buy-now-pay-later-for-woocommerce' ) );
			$product->set_slug( 'ddwcpp-postpaid-dues' );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_regular_price( '0' );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_tax_status( 'none' );
			$product->set_sku( 'ddwcpp_postpaid_dues' );
			$product_id = $product->save();

			update_option( '_ddwcpp_dues_product_id', $product_id );

			return $product_id;
		}
	}
}
