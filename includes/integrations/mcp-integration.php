<?php
/**
 * MCP Integration
 *
 * Puts Buy Now Pay Later in front of AI assistants (Claude, ChatGPT, Cursor, VS Code and any MCP
 * client). Every tool is a WordPress Ability (WordPress 6.9+) announced to the DevDiggers AI & MCP
 * hub (DevDiggers Plugins > AI & MCP), which lists them on the shared DevDiggers MCP server, turns
 * them on or off, logs calls and offers a playground.
 *
 * Text here goes to AI clients as JSON, so it is translated but not HTML-escaped.
 *
 * Every number comes from the plugin's own helpers, so it matches the Pay Later admin screens.
 *
 * @package DevDiggers Buy Now Pay Later for WooCommerce
 */

namespace DDWCPostPaid\Includes\Integrations;

use DDWCPostPaid\Helper\Dashboard\DDWCPP_Dashboard_Helper;
use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Helper\Plans\DDWCPP_Plans_Helper;
use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_MCP_Integration' ) ) {
	/**
	 * MCP Integration class
	 */
	class DDWCPP_MCP_Integration {
		/**
		 * Ability category and name prefix.
		 */
		const CATEGORY = 'bnpl';

		/**
		 * Configuration.
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Users helper.
		 *
		 * @var DDWCPP_Users_Helper
		 */
		protected $users_helper;

		/**
		 * Installments helper.
		 *
		 * @var DDWCPP_Installments_Helper
		 */
		protected $installments_helper;

		/**
		 * Construct.
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;

			// The Abilities API arrived in WordPress 6.9.
			if ( ! function_exists( 'wp_register_ability' ) ) {
				return;
			}

			$this->users_helper        = new DDWCPP_Users_Helper( $ddwcpp_configuration );
			$this->installments_helper = new DDWCPP_Installments_Helper();

			add_action( 'wp_abilities_api_categories_init', [ $this, 'ddwcpp_register_category' ] );
			add_action( 'wp_abilities_api_init', [ $this, 'ddwcpp_register_abilities' ] );
			add_filter( 'ddfw_mcp_plugins', [ $this, 'ddwcpp_add_to_hub' ] );
		}

		/**
		 * Announce Buy Now Pay Later to the DevDiggers AI & MCP hub.
		 *
		 * @param array $plugins Hub plugins.
		 * @return array
		 */
		public function ddwcpp_add_to_hub( $plugins ) {
			$plugins[ self::CATEGORY ] = [
				'name'        => __( 'Buy Now Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				'description' => __( 'Ask who owes you what: outstanding and overdue Pay Later credit, what is due this week, customers and their limits, their installments and payments.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				'category'    => self::CATEGORY,
				'icon'        => ddwcpp_get_brand_mark(),
				'url'         => ddwcpp_get_dashboard_url(),
			];

			return $plugins;
		}

		/**
		 * Category for every Buy Now Pay Later ability.
		 *
		 * @return void
		 */
		public function ddwcpp_register_category() {
			if ( wp_has_ability_category( self::CATEGORY ) ) {
				return;
			}

			wp_register_ability_category(
				self::CATEGORY,
				[
					'label'       => __( 'Buy Now Pay Later', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'Store credit, installments, repayments and collections of the Pay Later payment method.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
				]
			);
		}

		/**
		 * Register every ability.
		 *
		 * @return void
		 */
		public function ddwcpp_register_abilities() {
			foreach ( $this->ddwcpp_get_abilities() as $name => $args ) {
				$name = self::CATEGORY . '/' . $name;

				if ( wp_has_ability( $name ) ) {
					continue;
				}

				$readonly = ! empty( $args['readonly'] );

				wp_register_ability(
					$name,
					[
						'label'               => $args['label'],
						'description'         => $args['description'],
						'category'            => self::CATEGORY,
						'input_schema'        => $args['input'],
						'output_schema'       => $args['output'],
						'execute_callback'    => $args['callback'],
						'permission_callback' => isset( $args['permission'] ) ? $args['permission'] : [ $this, 'ddwcpp_can_read' ],
						'meta'                => [
							'show_in_rest' => true,
							'mcp'          => [
								'public' => true,
								'type'   => isset( $args['type'] ) ? $args['type'] : 'tool',
							],
							'annotations'  => [
								'readonly'        => $readonly,
								'destructive'     => ! $readonly,
								'idempotent'      => $readonly,
								'title'           => $args['label'],
								'readOnlyHint'    => $readonly,
								'destructiveHint' => ! $readonly,
								'idempotentHint'  => $readonly,
								'openWorldHint'   => false,
							],
						],
					]
				);
			}
		}

		/**
		 * Who may read: whoever may open the Pay Later screens.
		 *
		 * @return bool
		 */
		public function ddwcpp_can_read() {
			return is_user_logged_in() && current_user_can( apply_filters( 'ddwcpp_modify_menu_capability', 'manage_woocommerce' ) ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Same filtered capability as the Pay Later menu.
		}

		/**
		 * Who may change data: Pay Later managers who are also store managers.
		 *
		 * @return bool
		 */
		public function ddwcpp_can_write() {
			return $this->ddwcpp_can_read() && current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
		}

		/**
		 * Ability definitions.
		 *
		 * @return array
		 */
		protected function ddwcpp_get_abilities() {
			$date     = [
				'type'    => 'string',
				'pattern' => '^\d{4}-\d{2}-\d{2}$',
			];
			$id       = [
				'type'    => 'integer',
				'minimum' => 1,
			];
			$paging   = [
				'page'     => [
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				],
				'per_page' => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				],
			];
			$list_out = function ( $key ) {
				return $this->ddwcpp_object_schema(
					[
						$key          => 'array',
						'total'       => 'integer',
						'total_pages' => 'integer',
						'page'        => 'integer',
					]
				);
			};

			$abilities = [
				'credit-overview'    => [
					'label'       => __( 'Pay Later credit overview', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'The state of the Pay Later book, as on the Pay Later dashboard: total owed, available credit and utilization, accounts owing and overdue with the overdue amount, missed installments, what falls due in the next 7 days, applications waiting for review, the customers who owe the most, and credit used / repaid / orders for a date range compared with the period before. Start here for "how much am I owed" or "what is overdue".', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						[
							'after'  => $date + [ 'description' => __( 'First day of the activity period, Y-m-d. Defaults to 29 days before "before".', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ],
							'before' => $date + [ 'description' => __( 'Last day of the activity period, Y-m-d. Defaults to today.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ],
						]
					),
					'output'      => $this->ddwcpp_object_schema(
						[
							'currency'    => 'string',
							'outstanding' => 'object',
							'overdue'     => 'object',
							'due_soon'    => 'object',
							'activity'    => 'object',
							'top_debtors' => 'array',
						]
					),
					'callback'    => [ $this, 'ddwcpp_credit_overview' ],
				],
				'list-customers'     => [
					'label'       => __( 'List Pay Later customers', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'Customers on Pay Later with their approval status, credit limit, available credit, what they owe, next due date and amount, and whether they are overdue, largest balance first. Filter by status: owing (has a balance), overdue, pending (waiting for approval), active (credit line activated), rejected, or postpaid (everyone who applied or activated). Use the customer_id with bnpl-get-customer for details.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						array_merge(
							[
								'status' => [
									'type'    => 'string',
									'enum'    => [ 'postpaid', 'owing', 'overdue', 'pending', 'active', 'rejected' ],
									'default' => 'postpaid',
								],
								'search' => [
									'type'        => 'string',
									'description' => __( 'Part of the name, username or email, or a user ID.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								],
							],
							$paging
						)
					),
					'output'      => $list_out( 'customers' ),
					'callback'    => [ $this, 'ddwcpp_list_customers' ],
				],
				'get-customer'       => [
					'label'       => __( 'Get a Pay Later customer', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'One customer\'s Pay Later account: approval status, credit limit, available credit, what they owe and when, every Pay Later order with its plan and progress (store charges without an order have order_id 0), their open installments and latest transactions. Give customer_id (from bnpl-list-customers) or email.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						[
							'customer_id' => $id,
							'email'       => [
								'type'   => 'string',
								'format' => 'email',
							],
						]
					),
					'output'      => $this->ddwcpp_object_schema(
						[
							'customer'     => 'object',
							'account'      => 'object',
							'orders'       => 'array',
							'installments' => 'array',
							'transactions' => 'array',
						]
					),
					'callback'    => [ $this, 'ddwcpp_get_customer' ],
				],
				'list-installments'  => [
					'label'       => __( 'List installments', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'Scheduled Pay Later payments, as on Pay Later > Installments, soonest due first, with the customer, order, plan, payment number, amount, late fee, paid, remaining, due date and days overdue, plus the total still owed for the filter. view: overdue (missed), due_soon (due within "days", default 7: "what is due this week"), upcoming (not yet due), pending (every unpaid one), paid, cancelled or all. Filter by customer_id or order_id.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						array_merge(
							[
								'view'        => [
									'type'    => 'string',
									'enum'    => [ 'overdue', 'due_soon', 'upcoming', 'pending', 'paid', 'cancelled', 'all' ],
									'default' => 'pending',
								],
								'days'        => [
									'type'        => 'integer',
									'minimum'     => 1,
									'maximum'     => 365,
									'default'     => 7,
									'description' => __( 'Window of the due_soon view in days.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								],
								'customer_id' => $id,
								'order_id'    => $id,
							],
							$paging
						)
					),
					'output'      => $list_out( 'installments' ),
					'callback'    => [ $this, 'ddwcpp_list_installments' ],
				],
				'list-transactions'  => [
					'label'       => __( 'List Pay Later transactions', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'The Pay Later ledger, newest first, as on Pay Later > Transactions: purchases on credit, repayments, payments recorded by the store, refunds, cancellations and manual adjustments, with the customer, amount, order and note. A debit lowers the customer\'s available credit (they owe more), a credit raises it.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						array_merge(
							[
								'type'        => [
									'type'    => 'string',
									'enum'    => [ 'any', 'credit', 'debit' ],
									'default' => 'any',
								],
								'reference'   => [
									'type'        => 'string',
									'description' => __( 'What the transaction was, e.g. order_debit (purchase), cleared_dues (customer repayment), manual_payment (payment recorded by the store), order_refund, order_cancelled, manual_credit, manual_debit.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								],
								'customer_id' => $id,
								'after'       => $date + [ 'description' => __( 'From this day, Y-m-d.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ],
								'before'      => $date + [ 'description' => __( 'Up to this day, Y-m-d.', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ],
								'search'      => [
									'type'        => 'string',
									'description' => __( 'Transaction ID, order ID or part of the note.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
								],
							],
							$paging
						)
					),
					'output'      => $list_out( 'transactions' ),
					'callback'    => [ $this, 'ddwcpp_list_transactions' ],
				],
				'settings-summary'   => [
					'label'       => __( 'Pay Later settings', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'How Pay Later is set up: whether it is on, the default credit limit, how customers are approved, order amount limits, when single charges fall due, reminder timing, whether overdue customers are blocked, and the payment plans offered.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema( [] ),
					'output'      => $this->ddwcpp_object_schema(
						[
							'enabled' => 'boolean',
							'plans'   => 'array',
						]
					),
					'callback'    => [ $this, 'ddwcpp_settings_summary' ],
				],
				'collections-review' => [
					'type'        => 'prompt',
					'label'       => __( 'Collections review', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'Review what customers owe on Pay Later: overdue payments and who to chase first, what falls due soon, customers close to their limit and applications waiting, ending with a prioritized follow up list.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => true,
					'input'       => $this->ddwcpp_input_schema(
						[
							'days' => [
								'type'        => 'string',
								'description' => __( 'How many days ahead count as due soon. Defaults to 7.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							],
						]
					),
					'output'      => $this->ddwcpp_object_schema( [ 'messages' => 'array' ] ),
					'callback'    => [ $this, 'ddwcpp_prompt_collections_review' ],
				],
			];

			// The hub keeps write tools off until the owner turns them on; without the hub there is no switch, so no write tool.
			if ( class_exists( 'DDFW_MCP' ) ) {
				$abilities['mark-installment-paid'] = [
					'label'       => __( 'Mark an installment paid', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => __( 'Record that the store received the remaining amount of one unpaid installment (cash, bank transfer...), exactly like "Mark paid" on Pay Later > Installments: the customer\'s dues go down, a "Payment Recorded" transaction is logged and the customer gets the payment received email. Pass the installment_id from bnpl-list-installments and its remaining amount as "amount" to confirm.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'readonly'    => false,
					'input'       => $this->ddwcpp_input_schema(
						[
							'installment_id' => $id,
							'amount'         => [
								'type'        => 'number',
								'minimum'     => 0,
								'description' => __( 'The installment\'s remaining amount, as bnpl-list-installments shows it. A different amount is refused.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
							],
						],
						[ 'installment_id', 'amount' ]
					),
					'output'      => $this->ddwcpp_object_schema(
						[
							'installment' => 'object',
							'account'     => 'object',
						]
					),
					'callback'    => [ $this, 'ddwcpp_mark_installment_paid' ],
					'permission'  => [ $this, 'ddwcpp_can_write' ],
				];
			}

			/**
			 * Buy Now Pay Later abilities for AI assistants (MCP). Add, change or remove one; names get the "bnpl/" prefix.
			 *
			 * @param array $abilities Ability definitions keyed by name.
			 */
			return apply_filters( 'ddwcpp_mcp_abilities', $abilities );
		}

		/**
		 * Input schema of an object; an empty call is allowed unless something is required.
		 *
		 * @param array $properties Properties.
		 * @param array $required   Required properties.
		 * @return array
		 */
		protected function ddwcpp_input_schema( $properties, $required = [] ) {
			$schema = [
				'type'                 => 'object',
				'additionalProperties' => false,
				// An object in JSON ( {} ), so MCP clients read it as "no arguments needed".
				'default'              => (object) [],
			];

			if ( $properties ) {
				$schema['properties'] = $properties;
			}

			if ( $required ) {
				$schema['required'] = $required;
				unset( $schema['default'] );
			}

			return $schema;
		}

		/**
		 * Output schema: an object with the given top-level property types.
		 *
		 * @param array $types Property => JSON type.
		 * @return array
		 */
		protected function ddwcpp_object_schema( $types ) {
			$properties = [];

			foreach ( $types as $key => $type ) {
				$properties[ $key ] = [ 'type' => $type ];
			}

			return [
				'type'       => 'object',
				'properties' => $properties,
			];
		}

		/*
		 * ---------------------------------------------------------------------------------------------
		 * Shared helpers.
		 * ---------------------------------------------------------------------------------------------
		 */

		/**
		 * A Y-m-d date from input, or the fallback.
		 *
		 * @param array  $input    Input.
		 * @param string $key      Key.
		 * @param string $fallback Fallback.
		 * @return string
		 */
		protected function ddwcpp_date( $input, $key, $fallback = '' ) {
			$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$date  = \DateTime::createFromFormat( '!Y-m-d', $value );

			return $date && $date->format( 'Y-m-d' ) === $value ? $value : $fallback;
		}

		/**
		 * Page and per-page from input.
		 *
		 * @param array $input Input.
		 * @return array [ page, per_page ]
		 */
		protected function ddwcpp_paging( $input ) {
			return [
				max( 1, isset( $input['page'] ) ? absint( $input['page'] ) : 1 ),
				min( 100, max( 1, isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20 ) ),
			];
		}

		/**
		 * List response with paging numbers.
		 *
		 * @param string $key      List key.
		 * @param array  $items    Items.
		 * @param int    $total    Total items.
		 * @param int    $page     Page.
		 * @param int    $per_page Per page.
		 * @return array
		 */
		protected function ddwcpp_list_response( $key, $items, $total, $page, $per_page ) {
			return [
				$key          => array_values( $items ),
				'total'       => (int) $total,
				'total_pages' => (int) ceil( $total / $per_page ),
				'page'        => (int) $page,
				'currency'    => get_woocommerce_currency(),
			];
		}

		/**
		 * Price rounded to the store decimals.
		 *
		 * @param mixed $amount Amount.
		 * @return float
		 */
		protected function ddwcpp_round( $amount ) {
			return round( (float) $amount, wc_get_price_decimals() );
		}

		/**
		 * Plain text of a translated label that may carry HTML entities or a price.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		protected function ddwcpp_plain( $text ) {
			return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		/**
		 * Customer from input (customer_id or email).
		 *
		 * @param array $input Input.
		 * @return \WP_User|\WP_Error
		 */
		protected function ddwcpp_find_customer( $input ) {
			$user = false;

			if ( ! empty( $input['customer_id'] ) ) {
				$user = get_userdata( absint( $input['customer_id'] ) );
			} elseif ( ! empty( $input['email'] ) ) {
				$user = get_user_by( 'email', sanitize_email( $input['email'] ) );
			}

			if ( ! $user ) {
				return new \WP_Error( 'ddwcpp_customer_not_found', __( 'Customer not found. Use bnpl-list-customers to find the customer ID.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), [ 'status' => 404 ] );
			}

			return $user;
		}

		/**
		 * Name and email of a user, cached for the request.
		 *
		 * @param int $user_id User ID.
		 * @return array
		 */
		protected function ddwcpp_user( $user_id ) {
			static $users = [];

			if ( ! isset( $users[ $user_id ] ) ) {
				$user              = get_userdata( $user_id );
				$users[ $user_id ] = [
					'name'  => $user ? $user->display_name : '#' . $user_id,
					'email' => $user ? $user->user_email : '',
				];
			}

			return $users[ $user_id ];
		}

		/**
		 * Order number of an order ID, cached for the request.
		 *
		 * @param int $order_id Order ID.
		 * @return string
		 */
		protected function ddwcpp_order_number( $order_id ) {
			static $numbers = [];

			if ( ! $order_id ) {
				return '';
			}

			if ( ! isset( $numbers[ $order_id ] ) ) {
				$order                = wc_get_order( $order_id );
				$numbers[ $order_id ] = $order ? (string) $order->get_order_number() : '';
			}

			return $numbers[ $order_id ];
		}

		/**
		 * A customer's account in plain numbers.
		 *
		 * @param int $user_id User ID.
		 * @return array
		 */
		protected function ddwcpp_account_row( $user_id ) {
			$account = $this->users_helper->ddwcpp_get_account( $user_id );

			return [
				'status'        => $account['status'],
				'status_label'  => $this->ddwcpp_plain( ddwcpp_get_translation( $account['status'] ) ),
				'credit_active' => (bool) $account['allotted'],
				'credit_limit'  => $this->ddwcpp_round( $account['limit'] ),
				'available'     => $this->ddwcpp_round( max( 0, $account['balance'] ) ),
				'dues'          => $this->ddwcpp_round( $account['dues'] ),
				'next_due_date' => (string) $account['due_date'],
				'next_amount'   => $this->ddwcpp_round( $account['next_amount'] ),
				'overdue'       => (bool) $account['overdue'],
			];
		}

		/**
		 * One installment row in plain numbers.
		 *
		 * @param array $row Installment.
		 * @return array
		 */
		protected function ddwcpp_installment_row( $row ) {
			$now     = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Due dates are stored in site time.
			$due     = $row['due_date'] ? strtotime( $row['due_date'] ) : 0;
			$overdue = 'pending' === $row['status'] && $due && $due < $now;
			$user    = $this->ddwcpp_user( (int) $row['user_id'] );

			return [
				'id'             => (int) $row['id'],
				'customer_id'    => (int) $row['user_id'],
				'customer_name'  => $user['name'],
				'customer_email' => $user['email'],
				'order_id'       => (int) $row['order_id'],
				'order_number'   => $this->ddwcpp_order_number( (int) $row['order_id'] ),
				'plan'           => (string) $row['plan_title'],
				'payment'        => (int) $row['sequence'] . '/' . (int) $row['total_count'],
				'amount'         => $this->ddwcpp_round( $row['amount'] ),
				'late_fee'       => $this->ddwcpp_round( $row['late_fee'] ),
				'paid'           => $this->ddwcpp_round( $row['paid_amount'] ),
				'remaining'      => $this->ddwcpp_round( DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row ) ),
				'due_date'       => (string) $row['due_date'],
				'days_overdue'   => $overdue ? (int) floor( ( $now - $due ) / DAY_IN_SECONDS ) : 0,
				'status'         => $overdue ? 'overdue' : (string) $row['status'],
				'paid_date'      => (string) $row['paid_date'],
				'reminder_sent'  => (bool) $row['reminder_sent'],
			];
		}

		/**
		 * One transaction row in plain numbers.
		 *
		 * @param array $row Transaction.
		 * @return array
		 */
		protected function ddwcpp_transaction_row( $row ) {
			$user = $this->ddwcpp_user( (int) $row['user_id'] );

			return [
				'id'              => (int) $row['id'],
				'date'            => (string) $row['date'],
				'customer_id'     => (int) $row['user_id'],
				'customer_name'   => $user['name'],
				'type'            => (string) $row['type'],
				'reference'       => (string) $row['reference'],
				'reference_label' => $this->ddwcpp_plain( ddwcpp_get_translation( $row['reference'] ) ),
				'amount'          => $this->ddwcpp_round( $row['amount'] ),
				'order_id'        => (int) $row['order_id'],
				'order_number'    => $this->ddwcpp_order_number( (int) $row['order_id'] ),
				'note'            => (string) $row['note'],
			];
		}

		/**
		 * The dashboard helper, with its account and period figures reachable from here.
		 *
		 * @return DDWCPP_Dashboard_Helper
		 */
		protected function ddwcpp_dashboard_helper() {
			// ponytail: a subclass only to call the dashboard's own protected sums, so the numbers stay the dashboard's.
			return new class( $this->ddwcpp_configuration ) extends DDWCPP_Dashboard_Helper {
				/**
				 * Credit position of every active customer.
				 *
				 * @return array
				 */
				public function ddwcpp_mcp_accounts() {
					return $this->ddwcpp_get_accounts();
				}

				/**
				 * Totals of a period and of the period before it.
				 *
				 * @param string $from Y-m-d.
				 * @param string $to   Y-m-d.
				 * @return array [ current, previous, previous range ]
				 */
				public function ddwcpp_mcp_periods( $from, $to ) {
					$previous = $this->ddwcpp_get_previous_range(
						[
							'from' => $from,
							'to'   => $to,
						]
					);

					return [ $this->ddwcpp_get_period_totals( $from, $to ), $this->ddwcpp_get_period_totals( $previous['from'], $previous['to'] ), $previous ];
				}
			};
		}

		/**
		 * A value with its previous value and the change in percent.
		 *
		 * @param float $value    Current.
		 * @param float $previous Previous.
		 * @return array
		 */
		protected function ddwcpp_compare( $value, $previous ) {
			return [
				'value'          => $this->ddwcpp_round( $value ),
				'previous'       => $this->ddwcpp_round( $previous ),
				'change_percent' => $previous > 0 ? round( ( $value - $previous ) / $previous * 100, 1 ) : null,
			];
		}

		/*
		 * ---------------------------------------------------------------------------------------------
		 * Abilities.
		 * ---------------------------------------------------------------------------------------------
		 */

		/**
		 * Outstanding credit, overdue, due soon and activity of a period.
		 *
		 * @param array $input Input.
		 * @return array|\WP_Error
		 */
		public function ddwcpp_credit_overview( $input = [] ) {
			$input  = (array) $input;
			$before = $this->ddwcpp_date( $input, 'before', current_time( 'Y-m-d' ) );
			$after  = $this->ddwcpp_date( $input, 'after', gmdate( 'Y-m-d', strtotime( $before . ' -29 days' ) ) );

			if ( $after > $before ) {
				return new \WP_Error( 'ddwcpp_invalid_range', __( 'The start date must be before the end date.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), [ 'status' => 400 ] );
			}

			$dashboard = $this->ddwcpp_dashboard_helper();
			$accounts  = $dashboard->ddwcpp_mcp_accounts();

			list( $current, $previous, $previous_range ) = $dashboard->ddwcpp_mcp_periods( $after, $before );

			$rate = function ( $totals ) {
				return $totals['used'] > 0 ? min( 100, $totals['repaid'] * 100 / $totals['used'] ) : 0;
			};

			$pending = new \WP_User_Query(
				[
					'meta_key'    => '_ddwcpp_verification_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Same count as the dashboard.
					'meta_value'  => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same count as the dashboard.
					'number'      => 1,
					'fields'      => 'ID',
					'count_total' => true,
				]
			);

			return [
				'currency'             => get_woocommerce_currency(),
				'outstanding'          => [
					'total_owed'          => $this->ddwcpp_round( $accounts['outstanding'] ),
					'accounts_owing'      => (int) $accounts['owing'],
					'active_accounts'     => (int) $accounts['active'],
					'available_credit'    => $this->ddwcpp_round( $accounts['available'] ),
					'total_credit_limit'  => $this->ddwcpp_round( $accounts['limit'] ),
					'utilization_percent' => $accounts['limit'] > 0 ? round( $accounts['outstanding'] * 100 / $accounts['limit'], 1 ) : 0,
				],
				'overdue'              => [
					'accounts'                  => (int) $accounts['overdue'],
					'owed_by_overdue_accounts'  => $this->ddwcpp_round( $accounts['overdue_amount'] ),
					'missed_installments'       => $this->installments_helper->ddwcpp_count( [ 'view' => 'overdue' ] ),
					'missed_installments_total' => $this->ddwcpp_round( $this->installments_helper->ddwcpp_sum_remaining( [ 'view' => 'overdue' ] ) ),
				],
				'due_soon'             => [
					'days'         => 7,
					'installments' => $this->installments_helper->ddwcpp_count( [ 'view' => 'due_soon' ] ),
					'amount'       => $this->ddwcpp_round( $this->installments_helper->ddwcpp_sum_remaining( [ 'view' => 'due_soon' ] ) ),
				],
				'pending_applications' => 'none' === $this->ddwcpp_configuration['verification_mode'] ? 0 : (int) $pending->get_total(),
				'activity'             => [
					'after'               => $after,
					'before'              => $before,
					'previous_after'      => $previous_range['from'],
					'previous_before'     => $previous_range['to'],
					'credit_used'         => $this->ddwcpp_compare( $current['used'], $previous['used'] ),
					'repaid'              => $this->ddwcpp_compare( $current['repaid'], $previous['repaid'] ),
					'orders'              => [
						'value'    => (int) $current['orders'],
						'previous' => (int) $previous['orders'],
					],
					'repayment_rate'      => round( $rate( $current ), 1 ),
					'repayment_rate_prev' => round( $rate( $previous ), 1 ),
				],
				'top_debtors'          => array_map(
					function ( $row ) {
						$user = $this->ddwcpp_user( $row['user_id'] );

						return [
							'customer_id'   => (int) $row['user_id'],
							'name'          => $user['name'],
							'email'         => $user['email'],
							'dues'          => $this->ddwcpp_round( $row['dues'] ),
							'credit_limit'  => $this->ddwcpp_round( $row['limit'] ),
							'next_due_date' => $row['due_date'],
							'overdue'       => (bool) $row['overdue'],
						];
					},
					$accounts['top_debtors']
				),
			];
		}

		/**
		 * Customers with their credit position.
		 *
		 * @param array $input Input.
		 * @return array
		 */
		public function ddwcpp_list_customers( $input = [] ) {
			$input = (array) $input;
			$view  = isset( $input['status'] ) && in_array( $input['status'], [ 'postpaid', 'owing', 'overdue', 'pending', 'active', 'rejected' ], true ) ? $input['status'] : 'postpaid';

			list( $page, $per_page ) = $this->ddwcpp_paging( $input );

			// Same views as Pay Later > Customers; "owing" is the active accounts that owe something.
			switch ( $view ) {
				case 'pending':
				case 'rejected':
					$meta_query = [
						[
							'key'   => '_ddwcpp_verification_status',
							'value' => $view,
						],
					];
					break;
				case 'active':
				case 'owing':
					$meta_query = [
						[
							'key'     => '_ddwcpp_postpaid_allotted',
							'compare' => 'EXISTS',
						],
					];
					break;
				case 'overdue':
					$meta_query = [
						[
							'key'     => '_ddwcpp_due_date',
							'value'   => current_time( 'mysql' ),
							'compare' => '<',
							'type'    => 'DATETIME',
						],
					];
					break;
				default:
					$meta_query = [
						'relation' => 'OR',
						[
							'key'     => '_ddwcpp_verification_status',
							'compare' => 'EXISTS',
						],
						[
							'key'     => '_ddwcpp_postpaid_allotted',
							'compare' => 'EXISTS',
						],
					];
			}

			$args = [
				'fields'     => 'ID',
				'number'     => -1,
				'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The list is a view over customer meta, like the Customers screen.
			];

			if ( ! empty( $input['search'] ) ) {
				$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
				$args['search_columns'] = [ 'ID', 'user_login', 'user_email', 'user_nicename', 'display_name' ];
			}

			// ponytail: every matching customer is loaded to sort by what they owe; Pay Later customers are a small set. Page in SQL on a dues meta if that changes.
			$rows = [];

			foreach ( ( new \WP_User_Query( $args ) )->get_results() as $user_id ) {
				$user = $this->ddwcpp_user( (int) $user_id );
				$row  = array_merge(
					[
						'customer_id' => (int) $user_id,
						'name'        => $user['name'],
						'email'       => $user['email'],
					],
					$this->ddwcpp_account_row( (int) $user_id )
				);

				if ( 'owing' !== $view || $row['dues'] > 0 ) {
					$rows[] = $row;
				}
			}

			usort(
				$rows,
				function ( $a, $b ) {
					$order = $b['dues'] <=> $a['dues'];

					return $order ? $order : strcasecmp( $a['name'], $b['name'] );
				}
			);

			return $this->ddwcpp_list_response( 'customers', array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ), count( $rows ), $page, $per_page );
		}

		/**
		 * One customer's account, orders, open installments and latest transactions.
		 *
		 * @param array $input Input.
		 * @return array|\WP_Error
		 */
		public function ddwcpp_get_customer( $input = [] ) {
			$user = $this->ddwcpp_find_customer( (array) $input );

			if ( is_wp_error( $user ) ) {
				return $user;
			}

			$orders = [];

			foreach ( $this->installments_helper->ddwcpp_get_plans_summary( $user->ID ) as $plan ) {
				$order    = $plan['order_id'] ? wc_get_order( $plan['order_id'] ) : null;
				$orders[] = [
					'order_id'      => (int) $plan['order_id'],
					'order_number'  => $order ? (string) $order->get_order_number() : '',
					'order_status'  => $order ? $order->get_status() : '',
					'plan'          => (string) $plan['plan_title'],
					'started'       => (string) $plan['created'],
					'total'         => $this->ddwcpp_round( $plan['total'] ),
					'remaining'     => $this->ddwcpp_round( $plan['remaining'] ),
					'payments_made' => (int) $plan['paid_count'],
					'payments'      => (int) $plan['count'],
					'next_due_date' => (string) $plan['next_due'],
					'next_amount'   => $this->ddwcpp_round( $plan['next_amount'] ),
					'overdue'       => (bool) $plan['overdue'],
					'status'        => (string) $plan['status'],
					'edit_url'      => $order ? $order->get_edit_order_url() : '',
				];
			}

			$transactions = ( new DDWCPP_Transactions_Helper() )->ddwcpp_get_transactions(
				[
					'user_id'  => $user->ID,
					'per_page' => 10,
				]
			);

			return [
				'currency'     => get_woocommerce_currency(),
				'customer'     => [
					'id'          => (int) $user->ID,
					'name'        => $user->display_name,
					'email'       => $user->user_email,
					'roles'       => array_values( (array) $user->roles ),
					'profile_url' => get_edit_user_link( $user->ID ),
				],
				'account'      => $this->ddwcpp_account_row( $user->ID ),
				'orders'       => array_slice( $orders, 0, 25 ),
				'installments' => array_map( [ $this, 'ddwcpp_installment_row' ], array_slice( $this->installments_helper->ddwcpp_get_pending( $user->ID ), 0, 25 ) ),
				'transactions' => array_map( [ $this, 'ddwcpp_transaction_row' ], (array) $transactions ),
				'links'        => [
					'installments' => ddwcpp_get_dashboard_url(
						[
							'menu'        => 'installments',
							'view'        => 'all',
							'customer-id' => $user->ID,
						]
					),
					'transactions' => ddwcpp_get_dashboard_url(
						[
							'menu'        => 'transactions',
							'customer-id' => $user->ID,
						]
					),
				],
			];
		}

		/**
		 * Installments of a view.
		 *
		 * @param array $input Input.
		 * @return array
		 */
		public function ddwcpp_list_installments( $input = [] ) {
			$input = (array) $input;
			$view  = isset( $input['view'] ) && in_array( $input['view'], [ 'overdue', 'due_soon', 'upcoming', 'pending', 'paid', 'cancelled', 'all' ], true ) ? $input['view'] : 'pending';

			list( $page, $per_page ) = $this->ddwcpp_paging( $input );

			$filters = [
				'view'    => 'all' === $view ? '' : $view,
				'days'    => isset( $input['days'] ) ? min( 365, max( 1, absint( $input['days'] ) ) ) : 7,
				'user_id' => isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0,
			];

			if ( ! empty( $input['order_id'] ) ) {
				$filters['order_id'] = absint( $input['order_id'] );
			}

			// Paid lists read best newest first; everything owed soonest due first.
			$order = in_array( $view, [ 'paid', 'cancelled', 'all' ], true ) ? 'DESC' : 'ASC';
			$rows  = $this->installments_helper->ddwcpp_get_installments(
				$filters + [
					'per_page' => $per_page,
					'offset'   => ( $page - 1 ) * $per_page,
					'order'    => $order,
				]
			);

			$response = $this->ddwcpp_list_response( 'installments', array_map( [ $this, 'ddwcpp_installment_row' ], (array) $rows ), $this->installments_helper->ddwcpp_count( $filters ), $page, $per_page );

			$response['total_remaining'] = $this->ddwcpp_round( $this->installments_helper->ddwcpp_sum_remaining( $filters ) );

			return $response;
		}

		/**
		 * Ledger rows.
		 *
		 * @param array $input Input.
		 * @return array|\WP_Error
		 */
		public function ddwcpp_list_transactions( $input = [] ) {
			$input = (array) $input;
			$after = $this->ddwcpp_date( $input, 'after' );
			$to    = $this->ddwcpp_date( $input, 'before' );

			if ( $after && $to && $after > $to ) {
				return new \WP_Error( 'ddwcpp_invalid_range', __( 'The start date must be before the end date.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), [ 'status' => 400 ] );
			}

			list( $page, $per_page ) = $this->ddwcpp_paging( $input );

			$filters = [
				'type'      => isset( $input['type'] ) && in_array( $input['type'], [ 'credit', 'debit' ], true ) ? $input['type'] : '',
				'reference' => isset( $input['reference'] ) ? sanitize_key( $input['reference'] ) : '',
				'user_id'   => isset( $input['customer_id'] ) ? absint( $input['customer_id'] ) : 0,
				'from'      => $after,
				'to'        => $to,
				'search'    => isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '',
			];
			$helper  = new DDWCPP_Transactions_Helper();
			$rows    = $helper->ddwcpp_get_transactions(
				$filters + [
					'per_page' => $per_page,
					'offset'   => ( $page - 1 ) * $per_page,
				]
			);

			return $this->ddwcpp_list_response( 'transactions', array_map( [ $this, 'ddwcpp_transaction_row' ], (array) $rows ), $helper->ddwcpp_get_transactions_count( $filters ), $page, $per_page );
		}

		/**
		 * Allow-listed settings.
		 *
		 * @return array
		 */
		public function ddwcpp_settings_summary() {
			$config = $this->ddwcpp_configuration;
			$plans  = [];

			foreach ( DDWCPP_Plans_Helper::ddwcpp_get_plans() as $plan ) {
				$plans[] = [
					'id'       => (string) $plan['id'],
					'title'    => (string) $plan['title'],
					'summary'  => $this->ddwcpp_plain( DDWCPP_Plans_Helper::ddwcpp_get_summary( $plan ) ),
					'payments' => (int) $plan['count'],
					'upfront'  => ! empty( $plan['upfront'] ),
					'active'   => 'active' === $plan['status'],
				];
			}

			return [
				'enabled'                     => 'yes' === $config['enabled'],
				'currency'                    => get_woocommerce_currency(),
				'default_credit_limit'        => $this->ddwcpp_round( $config['limit'] ),
				'approval'                    => $config['verification_mode'],
				'approval_label'              => $this->ddwcpp_plain( ddwcpp_get_verification_mode_options()[ $config['verification_mode'] ] ),
				'activate_on_approval'        => ! empty( $config['auto_activate'] ),
				'min_order_amount'            => $this->ddwcpp_round( $config['min_order_amount'] ),
				'max_order_amount'            => $this->ddwcpp_round( $config['max_order_amount'] ),
				'restore_credit_on_cancel'    => 'yes' === $config['restore_on_cancel'],
				'single_charge_due_days'      => (int) $config['charge_due_days'],
				'reminder_days_before_due'    => (int) $config['reminder_days'],
				'block_overdue_customers'     => ! empty( $config['block_overdue'] ),
				'show_credit_at_checkout'     => 'yes' === $config['show_credit_at_checkout'],
				'installment_message_enabled' => ! empty( $config['message_enabled'] ),
				'plans'                       => $plans,
				'configuration_url'           => ddwcpp_get_dashboard_url( [ 'menu' => 'configuration' ] ),
			];
		}

		/**
		 * Record the remaining amount of an installment as paid, like "Mark paid" on the Installments screen.
		 *
		 * @param array $input Input.
		 * @return array|\WP_Error
		 */
		public function ddwcpp_mark_installment_paid( $input = [] ) {
			$input = (array) $input;
			$row   = $this->installments_helper->ddwcpp_get( isset( $input['installment_id'] ) ? absint( $input['installment_id'] ) : 0 );

			if ( ! $row ) {
				return new \WP_Error( 'ddwcpp_installment_not_found', __( 'Installment not found. Use bnpl-list-installments to find the installment ID.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), [ 'status' => 404 ] );
			}

			if ( 'pending' !== $row['status'] ) {
				/* translators: %s: installment status. */
				return new \WP_Error( 'ddwcpp_installment_not_pending', sprintf( __( 'This installment is %s, so there is nothing to record.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $row['status'] ), [ 'status' => 400 ] );
			}

			$remaining = DDWCPP_Installments_Helper::ddwcpp_get_remaining( $row );

			if ( ! isset( $input['amount'] ) || abs( (float) $input['amount'] - $remaining ) > 0.5 / pow( 10, wc_get_price_decimals() ) ) {
				/* translators: %s: remaining amount. */
				return new \WP_Error( 'ddwcpp_amount_mismatch', sprintf( __( 'The remaining amount of this installment is %s. Pass it as "amount" to confirm.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wc_format_decimal( $remaining, wc_get_price_decimals() ) ), [ 'status' => 400 ] );
			}

			// Same call as "Mark paid" on Pay Later > Installments.
			$this->users_helper->ddwcpp_record(
				$row['user_id'],
				[
					'type'           => 'credit',
					'amount'         => $remaining,
					'reference'      => 'manual_payment',
					'order_id'       => $row['order_id'] ? $row['order_id'] : null,
					'installment_id' => $row['id'],
					/* translators: %1$d: payment number, %2$d: number of payments. */
					'note'           => sprintf( esc_html__( 'Payment %1$d of %2$d recorded by the store.', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $row['sequence'], $row['total_count'] ),
					'email'          => 'payment_received',
				]
			);

			// AI made changes leave a trail under WooCommerce > Status > Logs.
			wc_get_logger()->info(
				sprintf( 'Installment #%1$d of customer #%2$d (order #%3$d) marked paid, %4$s recorded, by user #%5$d through MCP.', $row['id'], $row['user_id'], $row['order_id'], wc_format_decimal( $remaining, wc_get_price_decimals() ), get_current_user_id() ),
				[ 'source' => 'ddwcpp-mcp' ]
			);

			return [
				'currency'    => get_woocommerce_currency(),
				'recorded'    => $this->ddwcpp_round( $remaining ),
				'installment' => $this->ddwcpp_installment_row( $this->installments_helper->ddwcpp_get( $row['id'] ) ),
				'account'     => $this->ddwcpp_account_row( (int) $row['user_id'] ),
			];
		}

		/**
		 * A prompt: one user message.
		 *
		 * @param string $description Description.
		 * @param string $text        Message.
		 * @return array
		 */
		protected function ddwcpp_prompt( $description, $text ) {
			return [
				'description' => $description,
				'messages'    => [
					[
						'role'    => 'user',
						'content' => [
							'type' => 'text',
							'text' => $text,
						],
					],
				],
			];
		}

		/**
		 * Collections review prompt.
		 *
		 * @param array $input Input.
		 * @return array
		 */
		public function ddwcpp_prompt_collections_review( $input = [] ) {
			$input = (array) $input;
			$days  = isset( $input['days'] ) && absint( $input['days'] ) ? min( 365, absint( $input['days'] ) ) : 7;

			$text = sprintf(
				/* translators: %d: number of days ahead. */
				__(
					'Review my Buy Now Pay Later collections.

Use the Buy Now Pay Later tools:
1. bnpl-credit-overview for the total owed, overdue accounts, missed installments and pending applications.
2. bnpl-list-installments with view overdue, every page: who missed which payment, by how much and for how long.
3. bnpl-list-installments with view due_soon and days %1$d: what falls due next.
4. bnpl-list-customers with status owing: balances against credit limits.
5. bnpl-get-customer for the three customers who owe the most overdue, to see their orders and recent payments.

Then write a short collections report for the store owner:
- Headline: total owed, how much of it is overdue and in how many accounts, and the repayment trend.
- Overdue: each customer with the amount, days late, the order and whether a reminder went out, worst first.
- Due in the next %1$d days: total and the largest payments.
- Risk: customers using more than 80 percent of their limit, and applications waiting for review.
- A prioritized follow up list (who to contact and about which installment).
Use the store currency, round money to 2 decimals and keep it under 300 words. Do not change anything; only suggest. Say plainly when nothing is overdue.',
					'devdiggers-buy-now-pay-later-for-woocommerce'
				),
				$days
			);

			return $this->ddwcpp_prompt( __( 'Collections review', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $text );
		}
	}
}
