<?php
/**
 * Customers list.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Customers;

use DDWCPostPaid\Helper\Users\DDWCPP_Users_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'DDWCPP_Customers_List_Template' ) ) {
	/**
	 * Customers list class
	 */
	class DDWCPP_Customers_List_Template extends \WP_List_Table {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Users Helper Variable
		 *
		 * @var DDWCPP_Users_Helper
		 */
		protected $users_helper;

		/**
		 * Class constructor
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->users_helper         = new DDWCPP_Users_Helper( $ddwcpp_configuration );

			parent::__construct(
				[
					'singular' => 'ddwcpp-customer',
					'plural'   => 'ddwcpp-customers',
					'ajax'     => false,
				]
			);
		}

		/**
		 * Current status view.
		 *
		 * @return string
		 */
		protected function ddwcpp_get_status() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
			$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

			return array_key_exists( $status, $this->ddwcpp_get_view_labels() ) ? $status : 'postpaid';
		}

		/**
		 * View labels.
		 *
		 * @return array
		 */
		protected function ddwcpp_get_view_labels() {
			return [
				'postpaid' => esc_html__( 'Pay Later Customers', 'buy-now-pay-later-for-woocommerce' ),
				'pending'  => esc_html__( 'Pending Review', 'buy-now-pay-later-for-woocommerce' ),
				'active'   => esc_html__( 'Active', 'buy-now-pay-later-for-woocommerce' ),
				'overdue'  => esc_html__( 'Overdue', 'buy-now-pay-later-for-woocommerce' ),
				'rejected' => esc_html__( 'Rejected', 'buy-now-pay-later-for-woocommerce' ),
				'all'      => esc_html__( 'All Users', 'buy-now-pay-later-for-woocommerce' ),
			];
		}

		/**
		 * Meta query for a view.
		 *
		 * @param string $status View.
		 * @return array
		 */
		protected function ddwcpp_get_meta_query( $status ) {
			switch ( $status ) {
				case 'pending':
				case 'rejected':
					return [
						[
							'key'   => '_ddwcpp_verification_status',
							'value' => $status,
						],
					];
				case 'active':
					return [
						[
							'key'     => '_ddwcpp_postpaid_allotted',
							'compare' => 'EXISTS',
						],
					];
				case 'overdue':
					return [
						[
							'key'     => '_ddwcpp_due_date',
							'value'   => current_time( 'mysql' ),
							'compare' => '<',
							'type'    => 'DATETIME',
						],
					];
				case 'all':
					return [];
				default:
					return [
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
		}

		/**
		 * Status links above the table.
		 *
		 * @return array
		 */
		protected function get_views() {
			$current = $this->ddwcpp_get_status();
			$views   = [];

			foreach ( $this->ddwcpp_get_view_labels() as $status => $label ) {
				$query = new \WP_User_Query(
					[
						'meta_query'  => $this->ddwcpp_get_meta_query( $status ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Counts for the list views.
						'number'      => 1,
						'fields'      => 'ID',
						'count_total' => true,
					]
				);

				$views[ $status ] = sprintf(
					'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
					esc_url(
						ddwcpp_get_dashboard_url(
							[
								'menu'   => 'customers',
								'status' => $status,
							]
						)
					),
					$current === $status ? ' class="current" aria-current="page"' : '',
					esc_html( $label ),
					esc_html( number_format_i18n( $query->get_total() ) )
				);
			}

			return $views;
		}

		/**
		 * Prepare Items
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list parameters.
			$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], [ 'display_name', 'user_email', 'user_registered' ], true ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'user_registered';
			$order   = isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'ASC' : 'DESC';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$per_page = $this->get_items_per_page( 'ddwcpp_customers_per_page', 20 );
			$args     = [
				'number'     => $per_page,
				'paged'      => $this->get_pagenum(),
				'orderby'    => $orderby,
				'order'      => $order,
				'meta_query' => $this->ddwcpp_get_meta_query( $this->ddwcpp_get_status() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The list is a view over customer meta.
			];

			if ( $search ) {
				$args['search']         = '*' . $search . '*';
				$args['search_columns'] = [ 'ID', 'user_login', 'user_email', 'user_nicename', 'display_name' ];
			}

			$query = new \WP_User_Query( apply_filters( 'ddwcpp_customers_list_query_args', $args ) );

			$this->set_pagination_args(
				[
					'total_items' => $query->get_total(),
					'per_page'    => $per_page,
				]
			);

			$this->items = $query->get_results();
		}

		/**
		 *  No items
		 *
		 * @return void
		 */
		public function no_items() {
			$args = [
				'title'       => esc_html__( 'No customers yet', 'buy-now-pay-later-for-woocommerce' ),
				'description' => esc_html__( 'Customers appear here once they apply for Pay Later or activate it from My Account. Approve them, set their limits and adjust balances from this screen.', 'buy-now-pay-later-for-woocommerce' ),
			];

			// A search or filter that matches nothing is not an empty store.
			foreach ( [ 's' ] as $filter ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
				if ( ! empty( $_GET[ $filter ] ) ) {
					$args = [
						'title'       => esc_html__( 'No matches', 'buy-now-pay-later-for-woocommerce' ),
						'description' => esc_html__( 'Nothing matches this search or these filters. Try different terms or reset the filters.', 'buy-now-pay-later-for-woocommerce' ),
					];
					break;
				}
			}

			if ( function_exists( 'ddfw_print_empty_state' ) ) {
				ddfw_print_empty_state( $args );
			} else {
				echo esc_html( $args['description'] );
			}
		}

		/**
		 *  Associative array of columns
		 *
		 * @return array
		 */
		public function get_columns() {
			return apply_filters(
				'ddwcpp_customers_list_columns',
				[
					'customer' => esc_html__( 'Customer', 'buy-now-pay-later-for-woocommerce' ),
					'status'   => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
					'limit'    => esc_html__( 'Credit Limit', 'buy-now-pay-later-for-woocommerce' ),
					'balance'  => esc_html__( 'Available', 'buy-now-pay-later-for-woocommerce' ),
					'dues'     => esc_html__( 'Dues', 'buy-now-pay-later-for-woocommerce' ),
					'actions'  => esc_html__( 'Actions', 'buy-now-pay-later-for-woocommerce' ),
				]
			);
		}

		/**
		 * Columns to make sortable.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return [
				'customer' => [ 'display_name', false ],
			];
		}

		/**
		 * Primary column.
		 *
		 * @return string
		 */
		protected function get_default_primary_column_name() {
			return 'customer';
		}

		/**
		 * Status action URL.
		 *
		 * @param int    $user_id User ID.
		 * @param string $action  Action.
		 * @return string
		 */
		protected function ddwcpp_get_action_url( $user_id, $action ) {
			return wp_nonce_url(
				ddwcpp_get_dashboard_url(
					[
						'menu'           => 'customers',
						'status'         => $this->ddwcpp_get_status(),
						'action'         => $action,
						'ddwcpp-user-id' => $user_id,
					]
				),
				'ddwcpp_customers_bulk',
				'ddwcpp_nonce'
			);
		}

		/**
		 * Customer column: avatar, name, email and row actions.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_customer( $item ) {
			$status  = $this->users_helper->ddwcpp_get_verification_status( $item->ID );
			$actions = [];

			if ( 'none' !== $this->ddwcpp_configuration['verification_mode'] ) {
				foreach ( [ 'eligible', 'rejected', 'pending' ] as $action ) {
					if ( $action !== $status ) {
						$labels             = [
							'eligible' => esc_html__( 'Approve', 'buy-now-pay-later-for-woocommerce' ),
							'rejected' => esc_html__( 'Reject', 'buy-now-pay-later-for-woocommerce' ),
							'pending'  => esc_html__( 'Mark Pending', 'buy-now-pay-later-for-woocommerce' ),
						];
						$actions[ $action ] = '<a href="' . esc_url( $this->ddwcpp_get_action_url( $item->ID, $action ) ) . '">' . $labels[ $action ] . '</a>';
					}
				}
			}

			$actions['transactions'] = '<a href="' . esc_url(
				ddwcpp_get_dashboard_url(
					[
						'menu'        => 'transactions',
						'customer-id' => $item->ID,
					]
				)
			) . '">' . esc_html__( 'Transactions', 'buy-now-pay-later-for-woocommerce' ) . '</a>';
			$actions['edit']         = '<a href="' . esc_url( get_edit_user_link( $item->ID ) ) . '">' . esc_html__( 'Profile', 'buy-now-pay-later-for-woocommerce' ) . '</a>';

			return sprintf(
				'<div class="ddwcpp-customer">%1$s<div><strong>%2$s</strong><span>%3$s</span></div></div>%4$s',
				get_avatar( $item->ID, 36 ),
				esc_html( $item->display_name ),
				esc_html( $item->user_email ),
				$this->row_actions( apply_filters( 'ddwcpp_customers_list_row_actions', $actions, $item ) )
			);
		}

		/**
		 * Status column.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_status( $item ) {
			$status = $this->users_helper->ddwcpp_get_verification_status( $item->ID );
			$html   = '<mark class="ddwcpp-status ddwcpp-status-' . esc_attr( $status ) . '">' . esc_html( ddwcpp_get_translation( $status ) ) . '</mark>';

			if ( 'eligible' === $status ) {
				$html .= $this->users_helper->ddwcpp_is_allotted( $item->ID )
					? '<br /><small>' . esc_html__( 'Credit active', 'buy-now-pay-later-for-woocommerce' ) . '</small>'
					: '<br /><small>' . esc_html__( 'Not activated yet', 'buy-now-pay-later-for-woocommerce' ) . '</small>';
			}

			return $html;
		}

		/**
		 * Inline editable limit.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_limit( $item ) {
			// Setting a limit per customer is a Pro feature; every customer starts with the store default.
			return sprintf(
				'<span class="ddwcpp-limit-value">%1$s</span> <span class="ddfw-upgrade-to-pro-tag-wrapper ddwcpp-locked-action"><button type="button" class="button button-small" disabled="disabled">%2$s</button>%3$s</span>',
				wp_kses_post( wc_price( $this->users_helper->ddwcpp_get_limit( $item->ID ) ) ),
				esc_html__( 'Edit', 'buy-now-pay-later-for-woocommerce' ),
				ddfw_get_pro_tag()
			);
		}

		/**
		 * Available credit.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_balance( $item ) {
			$account = $this->users_helper->ddwcpp_get_account( $item->ID );

			return '<span class="ddwcpp-balance">' . wc_price( max( 0, $account['balance'] ) ) . '</span>';
		}

		/**
		 * Dues with the due date.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_dues( $item ) {
			$account = $this->users_helper->ddwcpp_get_account( $item->ID );
			$html    = '<span class="ddwcpp-dues">' . wc_price( $account['dues'] ) . '</span>';

			if ( $account['dues'] > 0 && $account['due_date'] ) {
				$html .= '<br /><small class="' . ( $account['overdue'] ? 'ddwcpp-overdue' : '' ) . '">';
				/* translators: %s: due date. */
				$html .= sprintf( $account['overdue'] ? esc_html__( 'Overdue since %s', 'buy-now-pay-later-for-woocommerce' ) : esc_html__( 'Due %s', 'buy-now-pay-later-for-woocommerce' ), esc_html( date_i18n( wc_date_format(), strtotime( $account['due_date'] ) ) ) );
				$html .= '</small>';
			}

			return $html;
		}

		/**
		 * Adjust button.
		 *
		 * @param \WP_User $item User.
		 * @return string
		 */
		public function column_actions( $item ) {
			return sprintf(
				'<button type="button" class="button ddwcpp-open-adjust" data-user-id="%1$d" data-customer="%2$s">%3$s</button>',
				absint( $item->ID ),
				esc_attr( $item->display_name . ' <' . $item->user_email . '>' ),
				esc_html__( 'Adjust Balance', 'buy-now-pay-later-for-woocommerce' )
			);
		}
	}
}
