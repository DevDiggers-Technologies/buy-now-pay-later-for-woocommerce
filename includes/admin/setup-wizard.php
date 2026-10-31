<?php
/**
 * Setup wizard integration.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes\Admin;

use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DevDiggers\Framework\Includes\DDFW_Form_Field;
use DevDiggers\Framework\Includes\DDFW_Setup_Wizard;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Setup_Wizard' ) ) {
	/**
	 * Setup wizard class
	 */
	class DDWCPP_Setup_Wizard {
		/**
		 * Construct
		 */
		public function __construct() {
			if ( ! class_exists( '\\DevDiggers\\Framework\\Includes\\DDFW_Setup_Wizard' ) ) {
				return;
			}

			new DDFW_Setup_Wizard( $this->ddwcpp_get_wizard_config() );
		}

		/**
		 * Wizard configuration.
		 *
		 * @return array
		 */
		public function ddwcpp_get_wizard_config() {
			return [
				'plugin_slug'    => DDWCPP_PLUGIN_SLUG,
				'plugin_file'    => DDWCPP_PLUGIN_SLUG . '/functions.php',
				'dashboard_page' => DDWCPP_DASHBOARD_PAGE,
				'redirect_url'   => admin_url( 'admin.php?page=' . DDWCPP_DASHBOARD_PAGE ),
				'brand'          => [
					'name'        => esc_html__( 'Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => esc_html__( 'Welcome to Buy Now Pay Later. A few quick answers and your customers can buy now and pay later.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				],
				'steps'          => [
					'welcome'   => [
						'label'         => esc_html__( 'Welcome', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'view_callback' => [ $this, 'ddwcpp_welcome_view' ],
					],
					'credit'    => [
						'label'         => esc_html__( 'Credit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'title'         => esc_html__( 'Who gets credit, and how much', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'Set the credit limit and how customers are approved.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'view_callback' => [ $this, 'ddwcpp_credit_view' ],
						'save_callback' => [ $this, 'ddwcpp_save_fields' ],
					],
					'plans'     => [
						'label'         => esc_html__( 'Plans', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'title'         => esc_html__( 'How customers pay you back', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'Pick the plans to offer at checkout. You can rename them and set order limits on the Plans screen.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'view_callback' => [ $this, 'ddwcpp_plans_view' ],
						'save_callback' => [ $this, 'ddwcpp_plans_save' ],
					],
					'repayment' => [
						'label'         => esc_html__( 'Reminders', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'title'         => esc_html__( 'Reminders and promotion', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'   => esc_html__( 'Customers are reminded before every payment. Decide what happens when one is missed, and whether shoppers see installment prices.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'view_callback' => [ $this, 'ddwcpp_repayment_view' ],
						'save_callback' => [ $this, 'ddwcpp_save_fields' ],
					],
					'ready'     => [
						'label'             => esc_html__( 'Ready!', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'ready_title'       => esc_html__( 'Pay Later is ready.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'ready_description' => esc_html__( 'Approve your first customers on the Customers screen, then place a test order with Pay Later and pay the first installment from My Account.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					],
				],
			];
		}

		/**
		 * Welcome step.
		 *
		 * @return void
		 */
		public function ddwcpp_welcome_view() {
			?>
			<div class="ddfw-setup-wizard-ready ddfw-setup-wizard-onboarding">
				<div class="ddfw-success-icon-wrap">
					<?php echo wp_kses( str_replace( [ 'width="32"', 'height="32"' ], [ 'width="100"', 'height="100"' ], ddwcpp_get_brand_mark() ), ddwcpp_get_allowed_html() ); ?>
				</div>
				<h2 class="ddfw-setup-wizard-ready-title"><?php esc_html_e( 'Welcome to Pay Later!', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h2>
				<p class="ddfw-setup-wizard-ready-desc">
					<?php esc_html_e( 'Run your own buy now, pay later program. In the next steps you will decide who gets credit, choose your payment plans and set up reminders.', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Credit step.
		 *
		 * @return void
		 */
		public function ddwcpp_credit_view() {
			$this->ddwcpp_render_fields(
				[
					[
						'type'           => 'checkbox',
						'label'          => esc_html__( 'Status', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'checkbox_label' => esc_html__( 'Activate Buy Now Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'    => esc_html__( 'Adds the Pay Later section to My Account and the payment method to checkout.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'             => 'ddwcpp-enabled',
						'name'           => '_ddwcpp_enabled',
						'value'          => get_option( '_ddwcpp_enabled', 'yes' ),
					],
					[
						'type'              => 'number',
						'label'             => esc_html__( 'Default Credit Limit', 'devdiggers-buy-now-pay-later-for-woocommerce' ) . ' (' . html_entity_decode( get_woocommerce_currency_symbol() ) . ')',
						'description'       => esc_html__( 'Customers can owe up to this amount at any time.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'                => 'ddwcpp-limit',
						'name'              => '_ddwcpp_limit',
						'value'             => get_option( '_ddwcpp_limit', 1000 ),
						'custom_attributes' => [
							'min'  => '0.01',
							'step' => 'any',
						],
					],
					[
						'type'        => 'select',
						'label'       => esc_html__( 'Approval Process', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description' => esc_html__( 'How a customer becomes eligible for credit.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'          => 'ddwcpp-verification-mode',
						'name'        => '_ddwcpp_verification_mode',
						'options'     => ddwcpp_get_verification_mode_options(),
						'value'       => get_option( '_ddwcpp_verification_mode', 'manual' ),
					],
					[
						'type'           => 'checkbox',
						'label'          => esc_html__( 'Activate on Approval', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'checkbox_label' => esc_html__( 'Start the credit line as soon as a customer is approved', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'    => esc_html__( 'Otherwise the customer activates it from My Account.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'             => 'ddwcpp-auto-activate',
						'name'           => '_ddwcpp_auto_activate',
						'value'          => get_option( '_ddwcpp_auto_activate' ),
					],
				]
			);
		}

		/**
		 * Repayment step.
		 *
		 * @return void
		 */
		public function ddwcpp_repayment_view() {
			$this->ddwcpp_render_fields(
				[
					[
						'type'              => 'number',
						'label'             => esc_html__( 'Reminder (days before)', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'       => esc_html__( 'Email a reminder this many days before each payment is due. Set 0 to skip reminders.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'                => 'ddwcpp-reminder-days',
						'name'              => '_ddwcpp_reminder_days',
						'value'             => get_option( '_ddwcpp_reminder_days', 3 ),
						'custom_attributes' => [
							'min'  => '0',
							'step' => '1',
						],
					],
					[
						'type'           => 'checkbox',
						'label'          => esc_html__( 'Pause Credit', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'checkbox_label' => esc_html__( 'Stop overdue customers from using Pay Later until they pay', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'description'    => esc_html__( 'Overdue customers are always emailed a notice with a link to pay.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'             => 'ddwcpp-block-overdue',
						'name'           => '_ddwcpp_block_overdue',
						'value'          => get_option( '_ddwcpp_block_overdue', 'yes' ),
					],
					[
						'type'           => 'checkbox',
						'label'          => esc_html__( 'Installment Message', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						/* translators: %s: example installment amount in the store currency. */
						'checkbox_label' => sprintf( esc_html__( 'Show "or 4 payments of %s" on product pages and in the cart', 'devdiggers-buy-now-pay-later-for-woocommerce' ), html_entity_decode( wp_strip_all_tags( wc_price( 25 ) ) ) ),
						'description'    => esc_html__( 'Shoppers see what they would pay per installment before checkout.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
						'id'             => 'ddwcpp-message-enabled',
						'name'           => '_ddwcpp_message_enabled',
						'value'          => get_option( '_ddwcpp_message_enabled', 'yes' ),
					],
				]
			);
		}

		/**
		 * Plans step: switch the ready-made plans on or off.
		 *
		 * @return void
		 */
		public function ddwcpp_plans_view() {
			$fields = [];

			foreach ( DDWCPP_Plans_Helper::ddwcpp_get_plans() as $plan ) {
				$fields[] = [
					'type'           => 'checkbox',
					'label'          => $plan['title'],
					'checkbox_label' => DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan ),
					'description'    => $plan['description'],
					'id'             => 'ddwcpp-plan-' . $plan['id'],
					'name'           => 'ddwcpp_plan_' . $plan['id'],
					'value'          => 'active' === $plan['status'] ? 'yes' : '',
				];
			}

			$this->ddwcpp_render_fields( $fields );
		}

		/**
		 * Plans step save.
		 *
		 * @param array $form_data Form data.
		 * @return \WP_Error|bool
		 */
		public function ddwcpp_plans_save( $form_data ) {
			$active = [];

			foreach ( (array) $form_data as $field ) {
				if ( ! empty( $field['name'] ) && 0 === strpos( $field['name'], 'ddwcpp_plan_' ) && 'yes' === ( isset( $field['value'] ) ? $field['value'] : '' ) ) {
					$active[] = sanitize_title( substr( $field['name'], 12 ) );
				}
			}

			if ( ! $active ) {
				return new \WP_Error( 'ddwcpp_no_plan', esc_html__( 'Choose at least one plan, so customers have a way to pay.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) );
			}

			update_option( DDWCPP_Plans_Helper::OPTION, DDWCPP_Plans_Helper::ddwcpp_sanitize_statuses( array_fill_keys( $active, [ 'status' => 'active' ] ) ), false );

			return true;
		}

		/**
		 * Save a step through the same sanitizers as the configuration screen.
		 *
		 * @param array $form_data Form data.
		 * @return bool
		 */
		public function ddwcpp_save_fields( $form_data ) {
			$registered = get_registered_settings();

			foreach ( (array) $form_data as $field ) {
				$name = ! empty( $field['name'] ) ? $field['name'] : '';

				// Only this plugin's registered settings; update_option() runs each one's sanitize_callback.
				if ( 0 === strpos( $name, '_ddwcpp_' ) && isset( $registered[ $name ] ) ) {
					update_option( $name, sanitize_text_field( isset( $field['value'] ) ? $field['value'] : '' ) );
				}
			}

			return true;
		}

		/**
		 * Render a wizard step's fields inside the framework section chrome.
		 *
		 * @param array $fields Fields.
		 * @return void
		 */
		protected function ddwcpp_render_fields( $fields ) {
			?>
			<div class="ddfw-fields-section">
				<table class="form-table">
					<tbody>
						<?php
						foreach ( $fields as $field ) {
							DDFW_Form_Field::display_form_field( $field );
						}
						?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
}
