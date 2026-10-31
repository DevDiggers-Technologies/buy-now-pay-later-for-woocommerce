<?php
/**
 * This file handles all admin dashboard functionalities.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes;

use DevDiggers\Framework\Includes\DDFW_Plugin_Dashboard;
use DevDiggers\Framework\Includes\DDFW_Assets;
use DevDiggers\Framework\Includes\DDFW_SVG;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Templates\Admin;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Admin_Dashboard' ) ) {
	/**
	 * Admin Dashboard Class
	 */
	class DDWCPP_Admin_Dashboard {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Dashboard Variable
		 *
		 * @var DDFW_Plugin_Dashboard
		 */
		protected $dashboard;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;

			add_action( 'admin_enqueue_scripts', [ $this, 'ddwcpp_enqueue_admin_scripts' ] );
			add_filter( 'ddfw_modify_svg_icons', [ $this, 'ddwcpp_add_svg_icons' ], 10, 2 );
			add_filter( 'admin_footer_text', [ $this, 'ddwcpp_set_admin_footer_text' ], 99 );

			$this->ddwcpp_add_dashboard_menu();
		}

		/**
		 * Add Admin menu function
		 *
		 * @return void
		 */
		public function ddwcpp_add_dashboard_menu() {
			ob_start();
			echo wp_kses( ddwcpp_get_brand_mark(), ddfw_kses_allowed_svg_tags() );
			esc_html_e( 'Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' );
			$plugin_name = ob_get_clean();

			$args = [
				'page_title'              => esc_html__( 'Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				'menu_title'              => esc_html__( 'Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				'slug'                    => DDWCPP_DASHBOARD_PAGE,
				'plugin_name'             => $plugin_name,
				'capability'              => apply_filters( 'ddwcpp_modify_menu_capability', 'manage_woocommerce' ),
				'upgrade_url'             => 'https://devdiggers.com/product/woocommerce-buy-now-pay-later/',
				'screen_options_callback' => [ $this, 'add_screen_options' ],
				'menus'                   => [
					'dashboard'     => [
						'label'    => esc_html__( 'Dashboard', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout'   => 'full-width',
						'callback' => [ $this, 'ddwcpp_get_dashboard_template' ],
					],
					'customers'     => [
						'label'    => esc_html__( 'Customers', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout'   => 'full-width',
						'callback' => [ $this, 'ddwcpp_get_customers_template' ],
					],
					'installments'  => [
						'label'    => esc_html__( 'Installments', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout'   => 'full-width',
						'callback' => [ $this, 'ddwcpp_get_installments_template' ],
					],
					'plans'         => [
						'label'    => esc_html__( 'Plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout'   => 'full-width',
						'callback' => [ $this, 'ddwcpp_get_plans_template' ],
					],
					'transactions'  => [
						'label'    => esc_html__( 'Transactions', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout'   => 'full-width',
						'callback' => [ $this, 'ddwcpp_get_transactions_template' ],
					],
					'configuration' => [
						'label'  => esc_html__( 'Configuration', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'layout' => 'sidebar',
						'tabs'   => [
							'general'    => [
								'label'    => esc_html__( 'General', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'general', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_configuration_template' ],
							],
							'approval'   => [
								'label'    => esc_html__( 'Credit & Approval', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'eligibility', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_configuration_template' ],
							],
							'repayment'  => [
								'label'    => esc_html__( 'Repayments', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'repayment', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_repayment_template' ],
							],
							'reminders'  => [
								'label'    => esc_html__( 'Reminders & Fees', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'payment-terms', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_configuration_template' ],
							],
							'messaging'  => [
								'label'    => esc_html__( 'Messaging', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'messaging', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_configuration_template' ],
							],
							'appearance' => [
								'label'    => esc_html__( 'Appearance', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'appearance', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_appearance_template' ],
							],
							'emails'     => [
								'label'    => esc_html__( 'Emails', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								'icon'     => DDFW_SVG::get_svg_icon( 'emails', true, [ 'size' => 18 ] ),
								'callback' => [ $this, 'ddwcpp_get_configuration_template' ],
							],
						],
					],
				],
			];

			$this->dashboard = new DDFW_Plugin_Dashboard( $args );
		}

		/**
		 * Current dashboard menu.
		 *
		 * @return string
		 */
		protected function ddwcpp_get_current_menu() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing parameter.
			return ! empty( $_GET['menu'] ) ? sanitize_key( wp_unslash( $_GET['menu'] ) ) : 'dashboard';
		}

		/**
		 * Add screen options for the admin dashboard
		 *
		 * @return void
		 */
		public function add_screen_options() {
			$current_menu = $this->ddwcpp_get_current_menu();

			// Instantiating the list table registers its columns with the screen.
			if ( 'customers' === $current_menu ) {
				new Admin\Customers\DDWCPP_Customers_List_Template( $this->ddwcpp_configuration );

				add_screen_option(
					'per_page',
					[
						'label'   => esc_html__( 'Customers Per Page', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'default' => 20,
						'option'  => 'ddwcpp_customers_per_page',
					]
				);
			} elseif ( 'installments' === $current_menu ) {
				new Admin\Installments\DDWCPP_Installments_List_Template( $this->ddwcpp_configuration );

				add_screen_option(
					'per_page',
					[
						'label'   => esc_html__( 'Installments Per Page', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'default' => 20,
						'option'  => 'ddwcpp_installments_per_page',
					]
				);
			} elseif ( 'transactions' === $current_menu ) {
				new Admin\Transactions\DDWCPP_Transactions_List_Template( $this->ddwcpp_configuration );

				add_screen_option(
					'per_page',
					[
						'label'   => esc_html__( 'Transactions Per Page', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'default' => 20,
						'option'  => 'ddwcpp_transactions_per_page',
					]
				);
			}
		}

		/**
		 * Dashboard menu.
		 *
		 * @return void
		 */
		public function ddwcpp_get_dashboard_template() {
			new Admin\Dashboard\DDWCPP_Dashboard_Template( $this->ddwcpp_configuration );
		}

		/**
		 * Customers menu.
		 *
		 * @return void
		 */
		public function ddwcpp_get_customers_template() {
			$list_table = new Admin\Customers\DDWCPP_Customers_List_Template( $this->ddwcpp_configuration );

			$this->ddwcpp_print_notice();
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pay Later Customers', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h1>
			<hr class="wp-header-end" />
			<?php $list_table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( DDWCPP_DASHBOARD_PAGE ); ?>" />
				<input type="hidden" name="menu" value="customers" />
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
				$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
				?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
				<?php
				$list_table->prepare_items();
				$list_table->search_box( esc_html__( 'Search Customers', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'ddwcpp-customer-search' );
				$list_table->display();
				?>
			</form>
			<?php
			$this->ddwcpp_render_adjust_dialog();
		}

		/**
		 * Installments menu.
		 *
		 * @return void
		 */
		public function ddwcpp_get_installments_template() {
			$list_table = new Admin\Installments\DDWCPP_Installments_List_Template( $this->ddwcpp_configuration );

			$this->ddwcpp_print_notice();
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Installments', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h1>
			<?php $this->ddwcpp_locked_export_button(); ?>
			<hr class="wp-header-end" />
			<?php $list_table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( DDWCPP_DASHBOARD_PAGE ); ?>" />
				<input type="hidden" name="menu" value="installments" />
				<?php
				$filters = \DDWCPostPaid\Includes\Admin\DDWCPP_Admin_Functions::ddwcpp_get_installment_filters();
				?>
				<input type="hidden" name="view" value="<?php echo esc_attr( $filters['view'] ? $filters['view'] : 'all' ); ?>" />
				<?php if ( $filters['user_id'] ) : ?>
					<input type="hidden" name="customer-id" value="<?php echo esc_attr( $filters['user_id'] ); ?>" />
				<?php endif; ?>
				<?php
				$list_table->prepare_items();
				$list_table->search_box( esc_html__( 'Search by order', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'ddwcpp-installment-search' );
				$list_table->display();
				?>
			</form>
			<?php
		}

		/**
		 * Plans menu: creating and editing payment plans is a Pro feature.
		 *
		 * @return void
		 */
		public function ddwcpp_get_plans_template() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcpp_get_upgrade_card(
					'plans',
					[
						'heading'       => esc_html__( 'Build the payment plans your customers ask for', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'Create your own plans with their own schedules, terms, fees and order limits, and offer exactly the terms that fit your store and your margins. Pay in 4 and Pay in 30 days are switched on or off under Configuration > General.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'list_features' => [
							esc_html__( 'Unlimited plans: 3, 6 or 12 monthly payments, weekly or custom schedules', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Your own payment terms: Net 15, Net 45, Net 60 or any number of days', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Plan fees, fixed or a percentage, shown before the customer chooses', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Minimum and maximum order amount per plan', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Rename plans, switch them on and off, and choose which one the product page advertises', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						],
					]
				)
			);
		}

		/**
		 * Dialog for a manual balance adjustment, opened from the customers list.
		 *
		 * @return void
		 */
		protected function ddwcpp_render_adjust_dialog() {
			?>
			<dialog id="ddwcpp-adjust-dialog" class="ddwcpp-dialog">
				<form method="dialog" class="ddwcpp-dialog-form">
					<h2><?php esc_html_e( 'Adjust Pay Later balance', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h2>
					<p class="ddwcpp-dialog-customer"></p>
					<p>
						<input type="radio" name="ddwcpp_adjust_type" id="ddwcpp-adjust-credit" value="credit" checked /><label for="ddwcpp-adjust-credit"><?php esc_html_e( 'Reduce dues (credit the customer)', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label><br />
						<input type="radio" name="ddwcpp_adjust_type" id="ddwcpp-adjust-debit" value="debit" /><label for="ddwcpp-adjust-debit"><?php esc_html_e( 'Increase dues (debit the customer)', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label>
					</p>
					<p>
						<label for="ddwcpp-adjust-amount"><?php esc_html_e( 'Amount', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label>
						<input type="number" id="ddwcpp-adjust-amount" min="0.01" step="any" required class="regular-text" />
					</p>
					<p>
						<label for="ddwcpp-adjust-note"><?php esc_html_e( 'Note (shown to the customer)', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label>
						<input type="text" id="ddwcpp-adjust-note" maxlength="250" class="regular-text" />
					</p>
					<p class="ddwcpp-adjust-due">
						<label for="ddwcpp-adjust-due-days"><?php esc_html_e( 'Due in (days)', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label>
						<input type="number" id="ddwcpp-adjust-due-days" min="0" step="1" value="<?php echo esc_attr( $this->ddwcpp_configuration['charge_due_days'] ); ?>" class="small-text" />
						<span class="description"><?php esc_html_e( 'Only for added dues. 0 means no due date.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></span>
					</p>
					<p>
						<label><input type="checkbox" id="ddwcpp-adjust-notify" checked /> <?php esc_html_e( 'Email the customer', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></label>
					</p>
					<div class="ddwcpp-dialog-actions">
						<button type="button" class="button ddwcpp-dialog-cancel"><?php esc_html_e( 'Cancel', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></button>
						<button type="submit" class="button button-primary ddwcpp-dialog-save"><?php esc_html_e( 'Save adjustment', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></button>
					</div>
				</form>
			</dialog>
			<?php
		}

		/**
		 * Transactions menu: list or a single transaction.
		 *
		 * @return void
		 */
		public function ddwcpp_get_transactions_template() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin routing parameters.
			$action         = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
			$transaction_id = isset( $_GET['transaction_id'] ) ? absint( wp_unslash( $_GET['transaction_id'] ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( 'view' === $action && $transaction_id ) {
				$transaction = ( new DDWCPP_Transactions_Helper() )->ddwcpp_get_transaction( $transaction_id );

				if ( $transaction ) {
					new Admin\Transactions\DDWCPP_Transaction_View_Template( $transaction );
				} else {
					ddfw_print_notification( esc_html__( 'Invalid transaction ID.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'error' );
				}

				return;
			}

			$list_table = new Admin\Transactions\DDWCPP_Transactions_List_Template( $this->ddwcpp_configuration );

			$this->ddwcpp_print_notice();
			?>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Transactions', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h1>
			<?php $this->ddwcpp_locked_export_button(); ?>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( DDWCPP_DASHBOARD_PAGE ); ?>" />
				<input type="hidden" name="menu" value="transactions" />
				<?php
				$list_table->prepare_items();
				$list_table->search_box( esc_html__( 'Search Transactions', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'ddwcpp-transaction-search' );
				$list_table->display();
				?>
			</form>
			<?php
		}

		/**
		 * Print the outcome of a row or bulk action after the redirect that ran it.
		 *
		 * @return void
		 */
		protected function ddwcpp_print_notice() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of the completed action's outcome.
			$notice = isset( $_GET['ddwcpp-notice'] ) ? sanitize_key( wp_unslash( $_GET['ddwcpp-notice'] ) ) : '';
			$count  = isset( $_GET['ddwcpp-count'] ) ? absint( wp_unslash( $_GET['ddwcpp-count'] ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			switch ( $notice ) {
				case 'eligible':
				case 'rejected':
				case 'pending':
					/* translators: %1$d: number of customers, %2$s: status. */
					ddfw_print_notification( sprintf( _n( '%1$d customer moved to %2$s.', '%1$d customers moved to %2$s.', $count, 'devdiggers-buy-now-pay-later-for-woocommerce' ), $count, ddwcpp_get_translation( $notice ) ) );
					break;
				case 'no-selection':
					ddfw_print_notification( esc_html__( 'That item could not be found.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'error' );
					break;
				case 'nothing-updated':
					ddfw_print_notification( esc_html__( 'Nothing was updated.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'error' );
					break;
				case 'mark_paid':
					/* translators: %d: number of installments. */
					ddfw_print_notification( sprintf( _n( '%d payment recorded.', '%d payments recorded.', $count, 'devdiggers-buy-now-pay-later-for-woocommerce' ), $count ) );
					break;
				case 'invalid-nonce':
					ddfw_print_notification( esc_html__( 'Invalid nonce. Security check failed!', 'devdiggers-buy-now-pay-later-for-woocommerce' ), 'error' );
					break;
			}
		}

		/**
		 * Configuration tabs that share one template class.
		 *
		 * @return void
		 */
		public function ddwcpp_get_configuration_template() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing parameter.
			$tab = ! empty( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

			new Admin\Configuration\DDWCPP_Configuration_Template( $this->ddwcpp_configuration, $tab );
		}

		/**
		 * Repayments tab: every setting on it is a Pro feature.
		 *
		 * @return void
		 */
		public function ddwcpp_get_repayment_template() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcpp_get_upgrade_card(
					'repayments',
					[
						'heading'       => esc_html__( 'Let customers pay what suits them, and earn on credit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'Customers already pay installment by installment. Pro adds flexible repayments and interest, so slower payers cover the cost of the credit you give.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'list_features' => [
							esc_html__( 'Partial payments: customers pay any amount toward their dues', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Minimum repayment amount you control', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Interest on repayments, fixed or a percentage, shown before they pay', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						],
					]
				)
			);
		}

		/**
		 * Appearance tab: every setting on it is a Pro feature.
		 *
		 * @return void
		 */
		public function ddwcpp_get_appearance_template() {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the framework helper.
			echo ddfw_kses_form_html(
				ddwcpp_get_upgrade_card(
					'appearance',
					[
						'heading'       => esc_html__( 'Make the customer area look like your store', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'The My Account section ships with a clean default design. Pro lets you match it to your brand without touching code.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'list_features' => [
							esc_html__( 'Credit card style balance or balance panel', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Segmented, underline or pill tabs, sharp, soft or round corners', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Eleven brand colors and your own tab labels', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							esc_html__( 'Toggle icons, the credit usage bar and upcoming payments', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						],
					]
				)
			);
		}

		/**
		 * Disabled Export CSV button, labelled PRO.
		 *
		 * @return void
		 */
		protected function ddwcpp_locked_export_button() {
			?>
			<span class="ddfw-upgrade-to-pro-tag-wrapper ddwcpp-locked-action">
				<button type="button" class="page-title-action button" disabled="disabled"><?php esc_html_e( 'Export CSV', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></button>
				<?php echo wp_kses_post( ddfw_get_pro_tag() ); ?>
			</span>
			<?php
		}

		/**
		 * Enqueue admin scripts function
		 *
		 * @return void
		 */
		public function ddwcpp_enqueue_admin_scripts() {
			$screen = get_current_screen();

			// The payment plan box on the order screen reuses the storefront schedule styles.
			if ( $screen && in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
				wp_enqueue_style( 'ddwcpp-front-style', DDWCPP_PLUGIN_URL . 'assets/css/front.css', [], filemtime( DDWCPP_PLUGIN_FILE . 'assets/css/front.css' ) );
			}

			if ( ! $this->dashboard->is_a_plugin_page() ) {
				return;
			}

			wp_enqueue_style( 'ddwcpp-admin-style', DDWCPP_PLUGIN_URL . 'assets/css/admin.css', [ DDFW_Assets::$framework_css_handle ], filemtime( DDWCPP_PLUGIN_FILE . 'assets/css/admin.css' ) );
			wp_enqueue_script( 'ddwcpp-admin-script', DDWCPP_PLUGIN_URL . 'assets/js/admin.js', [ DDFW_Assets::$framework_js_handle, 'jquery' ], filemtime( DDWCPP_PLUGIN_FILE . 'assets/js/admin.js' ), true );

			wp_localize_script(
				'ddwcpp-admin-script',
				'ddwcppAdminObj',
				[
					'ajax' => [
						'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
						'ajaxNonce' => wp_create_nonce( 'ddwcpp-nonce' ),
					],
					'i18n' => [
						'pleaseEnter'   => esc_html__( 'Please enter', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'moreCharacter' => esc_html__( 'or more characters', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'noResult'      => esc_html__( 'No results found', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'amountError'   => esc_html__( 'Enter an amount greater than zero.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'error'         => esc_html__( 'Something went wrong. Please try again.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					],
				]
			);
		}

		/**
		 * Thank-you footer on the plugin's own screens.
		 *
		 * @param string $footer_text Default footer text.
		 * @return string
		 */
		public function ddwcpp_set_admin_footer_text( $footer_text ) {
			if ( ! current_user_can( 'manage_woocommerce' ) || ! $this->dashboard->is_a_plugin_page() ) {
				return $footer_text;
			}

			$review_link = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/devdiggers-buy-now-pay-later-for-woocommerce/reviews/#new-post' ) . '" target="_blank" title="' . esc_attr__( 'Review', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'Review', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . '">' . \DDWCPP_Free_Init::ddwcpp_get_review_stars_svg() . '</a>';

			/* translators: %s: star rating link. */
			return wp_kses( sprintf( esc_html__( 'If DevDiggers Buy Now Pay Later for WooCommerce is working well for you, please leave us a %s rating. It really helps.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $review_link ), ddwcpp_get_allowed_html() );
		}

		/**
		 * Add SVG icons
		 *
		 * @param array $default_svg_icons Framework icons.
		 * @param array $args              Icon args.
		 * @return array
		 */
		public function ddwcpp_add_svg_icons( $default_svg_icons, $args ) {
			$size  = ! empty( $args['size'] ) ? $args['size'] : '24';
			$attrs = 'xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . ( ! empty( $args['stroke_color'] ) ? $args['stroke_color'] : 'currentColor' ) . '" stroke-width="' . ( isset( $args['stroke_width'] ) ? $args['stroke_width'] : '2' ) . '" stroke-linecap="round" stroke-linejoin="round"';

			$svg_icons = [
				'eligibility'   => '<svg ' . $attrs . '><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>',
				'repayment'     => '<svg ' . $attrs . '><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path><path d="M6 15h4"></path></svg>',
				'appearance'    => '<svg ' . $attrs . '><circle cx="13.5" cy="6.5" r="1.5"></circle><circle cx="17.5" cy="10.5" r="1.5"></circle><circle cx="8.5" cy="7.5" r="1.5"></circle><circle cx="6.5" cy="12.5" r="1.5"></circle><path d="M12 2a10 10 0 0 0 0 20c.9 0 1.6-.7 1.6-1.6 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.9.7-1.6 1.6-1.6H16a6 6 0 0 0 6-6c0-4.9-4.5-8.6-10-8.6z"></path></svg>',
				'emails'        => '<svg ' . $attrs . '><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-10 6L2 7"></path></svg>',
				'messaging'     => '<svg ' . $attrs . '><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path></svg>',
				'installments'  => '<svg ' . $attrs . '><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path><path d="M8 2v4"></path><path d="M16 2v4"></path><path d="m9 16 2 2 4-4"></path></svg>',
				'plans'         => '<svg ' . $attrs . '><path d="M12 2v10l7 4"></path><circle cx="12" cy="12" r="10"></circle></svg>',
				'payment-terms' => '<svg ' . $attrs . '><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path><path d="M12 14v3l2 1"></path></svg>',
				'outstanding'   => '<svg ' . $attrs . '><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>',
				'credit'        => '<svg ' . $attrs . '><path d="M12 2v20"></path><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
				'used'          => '<svg ' . $attrs . '><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"></path></svg>',
				'repaid'        => '<svg ' . $attrs . '><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"></path><path d="m22 4-10 10-3-3"></path></svg>',
				'customers'     => '<svg ' . $attrs . '><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1a4 4 0 0 1 0 7.8"></path></svg>',
				'pending'       => '<svg ' . $attrs . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M9 15h6"></path></svg>',
				'overdue'       => '<svg ' . $attrs . '><path d="m21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>',
			];

			return array_merge( $default_svg_icons, $svg_icons );
		}
	}
}
