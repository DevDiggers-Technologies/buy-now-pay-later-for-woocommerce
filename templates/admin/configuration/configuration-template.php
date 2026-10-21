<?php
/**
 * Configuration tabs: General, Credit & Approval, Repayments, Reminders & Fees and Messaging.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Configuration;

use DevDiggers\Framework\Includes\DDFW_Layout;
use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Configuration_Template' ) ) {
	/**
	 * Configuration template class
	 */
	class DDWCPP_Configuration_Template {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Construct
		 *
		 * @param array  $ddwcpp_configuration Configuration.
		 * @param string $tab                  Tab key.
		 */
		public function __construct( $ddwcpp_configuration, $tab = 'general' ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;

			$sections = $this->ddwcpp_get_sections();
			$tab      = isset( $sections[ $tab ] ) ? $tab : 'general';

			( new DDFW_Layout() )->get_form_section_layout( $sections[ $tab ], 'ddwcpp-' . $tab . '-configuration-fields' );
		}

		/**
		 * Currency suffix for amount labels.
		 *
		 * @return string
		 */
		protected function ddwcpp_currency() {
			return ' (' . html_entity_decode( get_woocommerce_currency_symbol() ) . ')';
		}

		/**
		 * Number field attributes for an amount.
		 *
		 * @return array
		 */
		protected function ddwcpp_amount_attributes() {
			return [
				'min'  => '0',
				'step' => 'any',
			];
		}

		/**
		 * An on/off switch for each of the two built-in plans.
		 *
		 * @return array
		 */
		protected function ddwcpp_get_plan_fields() {
			$fields = [];

			foreach ( DDWCPP_Plans_Helper::ddwcpp_get_plans() as $plan ) {
				$fields[] = [
					'type'           => 'checkbox',
					'label'          => $plan['title'],
					'checkbox_label' => DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan ),
					'checkbox_value' => 'active',
					'description'    => $plan['description'],
					'id'             => 'ddwcpp-plan-' . $plan['id'],
					'name'           => '_ddwcpp_plans[' . $plan['id'] . '][status]',
					'value'          => $plan['status'],
				];
			}

			return $fields;
		}

		/**
		 * Sections of every tab.
		 *
		 * @return array
		 */
		public function ddwcpp_get_sections() {
			$config = $this->ddwcpp_configuration;
			$roles  = [];

			foreach ( wp_roles()->roles as $role => $details ) {
				$roles[ $role ] = translate_user_role( $details['name'] );
			}

			return [
				'general'   => [
					[
						'header' => [
							'heading'     => esc_html__( 'Plugin Status', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Turn the Pay Later credit program on or off for the whole store.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Activate Buy Now Pay Later', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Shows the Pay Later section in My Account and the Pay Later payment method at checkout. Balances and history are kept when it is off.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-enabled',
								'name'           => '_ddwcpp_enabled',
								'value'          => $config['enabled'],
							],
							[
								'type'        => 'field_html',
								'label'       => esc_html__( 'Payment Method', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'Title, description, thank you page instructions and shipping method limits are set on the WooCommerce payment method screen.', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-payment-method',
								'html'        => '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ddwcpp_postpaid' ) ) . '">' . esc_html__( 'Configure the Pay Later payment method', 'buy-now-pay-later-for-woocommerce' ) . '</a>',
							],
						],
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Payment Plans', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'The plans customers can pick at checkout. Customers only see plans their credit covers.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => $this->ddwcpp_get_plan_fields(),
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Customer Experience', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'How Pay Later appears to your customers.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'        => 'text',
								'label'       => esc_html__( 'My Account Menu Title', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'Name of the Pay Later section in My Account, for example "Store Credit" or "Pay Later".', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-menu-title',
								'name'        => '_ddwcpp_menu_title',
								'value'       => $config['menu_title'],
							],
							[
								'type'        => 'text',
								'label'       => esc_html__( 'My Account Endpoint', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'The address of the Pay Later section, e.g. "pay-later" for /my-account/pay-later/. Lowercase letters, numbers and hyphens. Links update automatically.', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-my-account-endpoint',
								'name'        => '_ddwcpp_my_account_endpoint',
								'value'       => $config['my_account_endpoint'],
							],
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Sidebar Widgets', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Show theme sidebar widgets on the Pay Later section', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Most stores hide them so customers can focus on their payments and the layout has room to breathe.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-sidebar-widgets',
								'name'           => '_ddwcpp_sidebar_widgets',
								'value'          => $config['sidebar_widgets'],
							],
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Available Credit at Checkout', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Show available credit under the payment method', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Customers see how much credit they have left, and the payment term, before they choose Pay Later.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-show-credit-at-checkout',
								'name'           => '_ddwcpp_show_credit_at_checkout',
								'value'          => $config['show_credit_at_checkout'],
							],
						],
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Orders', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'What happens to credit when a Pay Later order does not go through.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Restore Credit', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Return the credit when a Pay Later order is cancelled or fails', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Refunds always return to credit. This covers orders that are cancelled instead of refunded.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-restore-on-cancel',
								'name'           => '_ddwcpp_restore_on_cancel',
								'value'          => $config['restore_on_cancel'],
							],
						],
					],
				],
				'approval'  => [
					[
						'header' => [
							'heading'     => esc_html__( 'Credit Limit', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'The credit every customer starts with when their credit line is activated. A different limit per customer is a Pro feature.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'              => 'number',
								'label'             => esc_html__( 'Default Credit Limit', 'buy-now-pay-later-for-woocommerce' ) . $this->ddwcpp_currency(),
								'description'       => esc_html__( 'Customers can owe up to this amount at any time.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-limit',
								'name'              => '_ddwcpp_limit',
								'value'             => $config['limit'],
								'custom_attributes' => [
									'min'  => '0.01',
									'step' => 'any',
								],
							],
						],
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Verification', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Decide who gets credit and how they are approved.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'        => 'select',
								'label'       => esc_html__( 'Approval Process', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'Review each application yourself, or let every logged in customer activate credit straight away.', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-verification-mode',
								'name'        => '_ddwcpp_verification_mode',
								'options'     => ddwcpp_get_verification_mode_options(),
								'value'       => $config['verification_mode'],
							],
							ddfw_locked_field(
								[
									'type'           => 'checkbox',
									'label'          => esc_html__( 'Document Upload', 'buy-now-pay-later-for-woocommerce' ),
									'checkbox_label' => esc_html__( 'Ask for an ID, business licence or other document when customers apply', 'buy-now-pay-later-for-woocommerce' ),
									'description'    => esc_html__( 'Files are kept in a private, access checked folder and shown on the Customers screen.', 'buy-now-pay-later-for-woocommerce' ),
									'id'             => 'verification-document',
									'value'          => '',
								],
								'ddwcpp'
							),
							ddfw_locked_field(
								[
									'type'           => 'checkbox',
									'label'          => esc_html__( 'Reverification', 'buy-now-pay-later-for-woocommerce' ),
									'checkbox_label' => esc_html__( 'Let customers upload a new document after a rejection', 'buy-now-pay-later-for-woocommerce' ),
									'description'    => esc_html__( 'Only applies when a document is required.', 'buy-now-pay-later-for-woocommerce' ),
									'id'             => 'reverification-enabled',
									'value'          => '',
								],
								'ddwcpp'
							),
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Activate on Approval', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Start the credit line as soon as a customer is approved', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Otherwise the customer activates it themselves from My Account.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-auto-activate',
								'name'           => '_ddwcpp_auto_activate',
								'value'          => $config['auto_activate'],
							],
							ddfw_locked_field(
								[
									'type'              => 'select',
									'label'             => esc_html__( 'Allowed User Roles', 'buy-now-pay-later-for-woocommerce' ),
									'description'       => esc_html__( 'Only these roles see Pay Later, for example wholesale or B2B customers. Leave empty to allow every role.', 'buy-now-pay-later-for-woocommerce' ),
									'id'                => 'allowed-roles',
									'options'           => $roles,
									'value'             => [],
									'placeholder'       => esc_html__( 'All roles', 'buy-now-pay-later-for-woocommerce' ),
									'custom_attributes' => [ 'multiple' => 'multiple' ],
								],
								'ddwcpp'
							),
						],
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Order Amount', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Limit Pay Later to orders inside a range. Leave a field empty for no limit.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'              => 'number',
								'label'             => esc_html__( 'Minimum Order Amount', 'buy-now-pay-later-for-woocommerce' ) . $this->ddwcpp_currency(),
								'description'       => esc_html__( 'Pay Later is hidden at checkout for smaller orders. 0 for no minimum.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-min-order-amount',
								'name'              => '_ddwcpp_min_order_amount',
								'value'             => wc_format_localized_decimal( $config['min_order_amount'] ),
								'custom_attributes' => $this->ddwcpp_amount_attributes(),
							],
							[
								'type'              => 'number',
								'label'             => esc_html__( 'Maximum Order Amount', 'buy-now-pay-later-for-woocommerce' ) . $this->ddwcpp_currency(),
								'description'       => esc_html__( 'Pay Later is hidden at checkout for larger orders, even when the customer has the credit. 0 for no maximum.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-max-order-amount',
								'name'              => '_ddwcpp_max_order_amount',
								'value'             => wc_format_localized_decimal( $config['max_order_amount'] ),
								'custom_attributes' => $this->ddwcpp_amount_attributes(),
							],
						],
					],
					[
						'header'            => [
							'heading'     => esc_html__( 'Excluded Products', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Keep pay later off products you only sell for cash up front, such as gift cards or deposits.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'after_header_html' => ddwcpp_get_upgrade_card(
							'excluded-products',
							[
								'heading'       => esc_html__( 'Choose which products can be bought on credit', 'buy-now-pay-later-for-woocommerce' ),
								'description'   => esc_html__( 'Protect low margin and high risk items. Excluded products hide the installment message and the payment method.', 'buy-now-pay-later-for-woocommerce' ),
								'list_features' => [
									esc_html__( 'Exclude single products', 'buy-now-pay-later-for-woocommerce' ),
									esc_html__( 'Exclude whole categories', 'buy-now-pay-later-for-woocommerce' ),
									esc_html__( 'Any cart holding an excluded product pays another way', 'buy-now-pay-later-for-woocommerce' ),
								],
							]
						),
					],
				],
				'reminders' => [
					[
						'header' => [
							'heading'     => esc_html__( 'Reminders', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Every installment gets one reminder before it is due and one notice the day after it is missed, each with a link to pay.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'              => 'number',
								'label'             => esc_html__( 'Reminder (days before)', 'buy-now-pay-later-for-woocommerce' ),
								'description'       => esc_html__( 'Email a reminder this many days before each due date. Set 0 to skip reminders.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-reminder-days',
								'name'              => '_ddwcpp_reminder_days',
								'value'             => $config['reminder_days'],
								'custom_attributes' => [
									'min'  => '0',
									'step' => '1',
								],
							],
							ddfw_locked_field(
								[
									'type'              => 'number',
									'label'             => esc_html__( 'Grace Period (days)', 'buy-now-pay-later-for-woocommerce' ),
									'description'       => esc_html__( 'Days after the due date before a payment counts as overdue, the late fee is charged and credit is paused. Set 0 to act the day after.', 'buy-now-pay-later-for-woocommerce' ),
									'id'                => 'grace-days',
									'value'             => 0,
									'custom_attributes' => [
										'min'  => '0',
										'step' => '1',
									],
								],
								'ddwcpp'
							),
							[
								'type'              => 'number',
								'label'             => esc_html__( 'Other Charges Due In (days)', 'buy-now-pay-later-for-woocommerce' ),
								'description'       => esc_html__( 'Due date for amounts you add by hand on the Customers screen. Set 0 for no due date.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-charge-due-days',
								'name'              => '_ddwcpp_charge_due_days',
								'value'             => $config['charge_due_days'],
								'custom_attributes' => [
									'min'  => '0',
									'step' => '1',
								],
							],
						],
					],
					[
						'header' => [
							'heading'     => esc_html__( 'Missed Payments', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'What happens once a payment is overdue.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Pause Credit', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Stop overdue customers from using Pay Later until they pay', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'The checkout tells them why pay later is not available.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-block-overdue',
								'name'           => '_ddwcpp_block_overdue',
								'value'          => $config['block_overdue'],
							],
							ddfw_locked_field(
								[
									'type'        => 'select',
									'label'       => esc_html__( 'Late Fee Type', 'buy-now-pay-later-for-woocommerce' ),
									'description' => esc_html__( 'A fixed amount, or a percentage of the missed payment.', 'buy-now-pay-later-for-woocommerce' ),
									'id'          => 'late-fee-type',
									'options'     => [
										'fixed'      => esc_html__( 'Fixed amount', 'buy-now-pay-later-for-woocommerce' ),
										'percentage' => esc_html__( 'Percentage of the amount', 'buy-now-pay-later-for-woocommerce' ),
									],
									'value'       => 'fixed',
								],
								'ddwcpp'
							),
							ddfw_locked_field(
								[
									'type'              => 'number',
									'label'             => esc_html__( 'Late Fee', 'buy-now-pay-later-for-woocommerce' ),
									'description'       => esc_html__( 'Added once to each missed payment. 0 for no late fee.', 'buy-now-pay-later-for-woocommerce' ),
									'id'                => 'late-fee',
									'value'             => '',
									'custom_attributes' => $this->ddwcpp_amount_attributes(),
								],
								'ddwcpp'
							),
						],
					],
				],
				'messaging' => [
					[
						'header' => [
							'heading'     => esc_html__( 'Installment Message', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Show shoppers what a product costs per installment before they reach checkout, for example "or 4 payments of $25.00 every 2 weeks". A Learn more link explains how Pay Later works.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'fields' => [
							[
								'type'           => 'checkbox',
								'label'          => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
								'checkbox_label' => esc_html__( 'Show the installment message', 'buy-now-pay-later-for-woocommerce' ),
								'description'    => esc_html__( 'Stores that advertise pay over time options usually see bigger orders.', 'buy-now-pay-later-for-woocommerce' ),
								'id'             => 'ddwcpp-message-enabled',
								'name'           => '_ddwcpp_message_enabled',
								'value'          => $config['message_enabled'],
							],
							[
								'type'              => 'select',
								'label'             => esc_html__( 'Show On', 'buy-now-pay-later-for-woocommerce' ),
								'description'       => esc_html__( 'Use the [ddwcpp_installments_message] shortcode to place it anywhere else.', 'buy-now-pay-later-for-woocommerce' ),
								'id'                => 'ddwcpp-message-locations',
								'name'              => '_ddwcpp_message_locations[]',
								'options'           => ddwcpp_get_message_location_options(),
								'value'             => $config['message_locations'],
								'custom_attributes' => [ 'multiple' => 'multiple' ],
							],
							ddfw_locked_field(
								[
									'type'        => 'select',
									'label'       => esc_html__( 'Show To', 'buy-now-pay-later-for-woocommerce' ),
									'description' => esc_html__( 'Showing it to everyone invites new customers to apply. Approved customers only keeps it private.', 'buy-now-pay-later-for-woocommerce' ),
									'id'          => 'message-audience',
									'options'     => [
										'all'      => esc_html__( 'Everyone, guests included', 'buy-now-pay-later-for-woocommerce' ),
										'eligible' => esc_html__( 'Approved customers only', 'buy-now-pay-later-for-woocommerce' ),
									],
									'value'       => 'all',
								],
								'ddwcpp'
							),
							ddfw_locked_field(
								[
									'type'        => 'select',
									'label'       => esc_html__( 'Plan to Advertise', 'buy-now-pay-later-for-woocommerce' ),
									'description' => esc_html__( 'Automatic picks the plan with the most installments that fits the price.', 'buy-now-pay-later-for-woocommerce' ),
									'id'          => 'message-plan',
									'options'     => [ 'auto' => esc_html__( 'Automatic', 'buy-now-pay-later-for-woocommerce' ) ],
									'value'       => 'auto',
								],
								'ddwcpp'
							),
							[
								'type'        => 'text',
								'label'       => esc_html__( 'Message Text', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'For plans with several payments. Placeholders: {count} payments, {amount} each payment, {interval} e.g. "every 2 weeks", {plan} plan title, {total} total.', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-message-text',
								'name'        => '_ddwcpp_message_text',
								'value'       => $config['message_text'],
							],
							[
								'type'        => 'text',
								'label'       => esc_html__( 'Single Payment Text', 'buy-now-pay-later-for-woocommerce' ),
								'description' => esc_html__( 'For plans paid in one go, like Pay in 30 days. {amount} is the full amount and {interval} reads e.g. "in 30 days".', 'buy-now-pay-later-for-woocommerce' ),
								'id'          => 'ddwcpp-message-text-single',
								'name'        => '_ddwcpp_message_text_single',
								'value'       => $config['message_text_single'],
							],
						],
					],
					[
						'header'            => [
							'heading'     => esc_html__( 'Learn More Dialog', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'The explainer that opens from the Learn more link next to the installment message.', 'buy-now-pay-later-for-woocommerce' ),
						],
						'after_header_html' => ddwcpp_get_upgrade_card(
							'learn-more-dialog',
							[
								'heading'       => esc_html__( 'Explain pay later in your own words', 'buy-now-pay-later-for-woocommerce' ),
								'description'   => esc_html__( 'The dialog ships with clear default steps. Pro lets you rewrite it and add your logo, so shoppers trust it and apply.', 'buy-now-pay-later-for-woocommerce' ),
								'list_features' => [
									esc_html__( 'Your own logo or icon', 'buy-now-pay-later-for-woocommerce' ),
									esc_html__( 'Custom title, steps and small print', 'buy-now-pay-later-for-woocommerce' ),
									esc_html__( 'Choose who sees the message and which plan it shows', 'buy-now-pay-later-for-woocommerce' ),
								],
							]
						),
					],
				],

				'emails'    => $this->ddwcpp_get_email_sections(),
			];
		}

		/**
		 * Emails tab: one section per email, LoyaltyX style.
		 *
		 * @return array
		 */
		protected function ddwcpp_get_email_sections() {
			$settings = \DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_get_settings();
			$common   = \DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_get_common_placeholders();
			$sections = [
				[
					'header' => [
						'heading'     => esc_html__( 'Email Design', 'buy-now-pay-later-for-woocommerce' ),
						'description' => esc_html__( 'Every email uses your WooCommerce email header, footer, colors and sender. Switch each one on or off and edit its wording below.', 'buy-now-pay-later-for-woocommerce' ),
					],
					'fields' => [
						[
							'type'        => 'field_html',
							'label'       => esc_html__( 'WooCommerce Emails', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'Sender name, address, logo and colors.', 'buy-now-pay-later-for-woocommerce' ),
							'id'          => 'ddwcpp-email-design',
							'html'        => '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_postpaid_management_notification' ) ) . '">' . esc_html__( 'Open email settings', 'buy-now-pay-later-for-woocommerce' ) . '</a>',
						],
					],
				],
			];

			foreach ( \DDWCPostPaid\Helper\Emails\DDWCPP_Emails_Helper::ddwcpp_get_events() as $event => $info ) {
				$name = function ( $key ) use ( $event ) {
					return '_ddwcpp_email_settings[' . $event . '][' . $key . ']';
				};
				$id   = 'ddwcpp-email-' . str_replace( '_', '-', $event );

				$sections[] = [
					'header' => [
						'heading'     => $info['label'],
						'description' => $info['description'],
					],
					'fields' => [
						[
							'type'           => 'checkbox',
							'label'          => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
							'checkbox_label' => esc_html__( 'Send this email', 'buy-now-pay-later-for-woocommerce' ),
							'description'    => 'admin' === $info['recipient'] ? esc_html__( 'Sent to the store admin email address.', 'buy-now-pay-later-for-woocommerce' ) : esc_html__( 'Sent to the customer.', 'buy-now-pay-later-for-woocommerce' ),
							'id'             => $id . '-enabled',
							'name'           => $name( 'enabled' ),
							'value'          => $settings[ $event ]['enabled'],
						],
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Subject', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'The subject line in the inbox.', 'buy-now-pay-later-for-woocommerce' ),
							'id'          => $id . '-subject',
							'name'        => $name( 'subject' ),
							'value'       => $settings[ $event ]['subject'],
						],
						[
							'type'        => 'text',
							'label'       => esc_html__( 'Heading', 'buy-now-pay-later-for-woocommerce' ),
							'description' => esc_html__( 'The title at the top of the email.', 'buy-now-pay-later-for-woocommerce' ),
							'id'          => $id . '-heading',
							'name'        => $name( 'heading' ),
							'value'       => $settings[ $event ]['heading'],
						],
						[
							'type'          => 'editor',
							'label'         => esc_html__( 'Message', 'buy-now-pay-later-for-woocommerce' ),
							'id'            => $id . '-message',
							'name'          => $name( 'message' ),
							'value'         => $settings[ $event ]['message'],
							'textarea_rows' => 8,
							/* translators: %s: list of placeholders. */
							'description'   => sprintf( esc_html__( 'Available placeholders: %s', 'buy-now-pay-later-for-woocommerce' ), '<code>' . implode( '</code> <code>', array_merge( $info['placeholders'], $common ) ) . '</code>' ),
						],
					],
				];
			}

			return $sections;
		}
	}
}
