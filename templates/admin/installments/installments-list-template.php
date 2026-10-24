<?php
/**
 * Installments list: every payment customers owe, with what is due soon and what is late.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Installments;

use DDWCPostPaid\Helper\Installments\DDWCPP_Installments_Helper;
use DDWCPostPaid\Includes\Admin\DDWCPP_Admin_Functions;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'DDWCPP_Installments_List_Template' ) ) {
	/**
	 * Installments list class
	 */
	class DDWCPP_Installments_List_Template extends \WP_List_Table {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Installments helper.
		 *
		 * @var DDWCPP_Installments_Helper
		 */
		protected $helper;

		/**
		 * Construct
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->helper               = new DDWCPP_Installments_Helper();

			parent::__construct(
				[
					'singular' => 'ddwcpp-installment',
					'plural'   => 'ddwcpp-installments',
					'ajax'     => false,
				]
			);
		}

		/**
		 * Current view.
		 *
		 * @return string
		 */
		protected function ddwcpp_get_view() {
			$filters = DDWCPP_Admin_Functions::ddwcpp_get_installment_filters();
			return $filters['view'] ? $filters['view'] : 'all';
		}

		/**
		 * View labels.
		 *
		 * @return array
		 */
		protected function ddwcpp_get_view_labels() {
			return [
				'upcoming'  => esc_html__( 'Upcoming', 'buy-now-pay-later-for-woocommerce' ),
				'due_soon'  => esc_html__( 'Due in 7 Days', 'buy-now-pay-later-for-woocommerce' ),
				'overdue'   => esc_html__( 'Overdue', 'buy-now-pay-later-for-woocommerce' ),
				'paid'      => esc_html__( 'Paid', 'buy-now-pay-later-for-woocommerce' ),
				'cancelled' => esc_html__( 'Cancelled', 'buy-now-pay-later-for-woocommerce' ),
				'all'       => esc_html__( 'All', 'buy-now-pay-later-for-woocommerce' ),
			];
		}

		/**
		 * Views above the table.
		 *
		 * @return array
		 */
		protected function get_views() {
			$current = $this->ddwcpp_get_view();
			$views   = [];

			foreach ( $this->ddwcpp_get_view_labels() as $view => $label ) {
				$count = $this->helper->ddwcpp_count( [ 'view' => 'all' === $view ? '' : $view ] );

				$views[ $view ] = sprintf(
					'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
					esc_url(
						ddwcpp_get_dashboard_url(
							[
								'menu' => 'installments',
								'view' => $view,
							]
						)
					),
					$current === $view ? ' class="current" aria-current="page"' : '',
					esc_html( $label ),
					esc_html( number_format_i18n( $count ) )
				);
			}

			return $views;
		}

		/**
		 * Prepare items.
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			$filters  = DDWCPP_Admin_Functions::ddwcpp_get_installment_filters();
			$per_page = $this->get_items_per_page( 'ddwcpp_installments_per_page', 20 );
			$history  = in_array( $filters['view'], [ 'paid', 'cancelled' ], true );

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sorting.
			$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ( $history ? 'paid_date' : 'due_date' );
			$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : ( $history ? 'desc' : 'asc' );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$this->items = $this->helper->ddwcpp_get_installments(
				array_merge(
					$filters,
					[
						'per_page' => $per_page,
						'offset'   => ( $this->get_pagenum() - 1 ) * $per_page,
						'orderby'  => $orderby,
						'order'    => $order,
					]
				)
			);

			$this->set_pagination_args(
				[
					'total_items' => $this->helper->ddwcpp_count( $filters ),
					'per_page'    => $per_page,
				]
			);
		}

		/**
		 * Columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			return [
				'customer' => esc_html__( 'Customer', 'buy-now-pay-later-for-woocommerce' ),
				'plan'     => esc_html__( 'Plan', 'buy-now-pay-later-for-woocommerce' ),
				'amount'   => esc_html__( 'Amount', 'buy-now-pay-later-for-woocommerce' ),
				'due_date' => esc_html__( 'Due Date', 'buy-now-pay-later-for-woocommerce' ),
				'status'   => esc_html__( 'Status', 'buy-now-pay-later-for-woocommerce' ),
				'actions'  => esc_html__( 'Actions', 'buy-now-pay-later-for-woocommerce' ),
			];
		}

		/**
		 * Sortable columns.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return [
				'amount'   => [ 'amount', false ],
				'due_date' => [ 'due_date', false ],
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
		 * Customer.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_customer( $item ) {
			$user = get_userdata( $item['user_id'] );

			if ( ! $user ) {
				return esc_html__( 'Deleted customer', 'buy-now-pay-later-for-woocommerce' );
			}

			$filter = ddwcpp_get_dashboard_url(
				[
					'menu'        => 'installments',
					'view'        => 'all',
					'customer-id' => $user->ID,
				]
			);

			return get_avatar( $user->ID, 32, '', '', [ 'class' => 'ddwcpp-avatar' ] ) . '<strong><a href="' . esc_url( $filter ) . '">' . esc_html( $user->display_name ) . '</a></strong><br /><span class="ddwcpp-muted">' . esc_html( $user->user_email ) . '</span>';
		}

		/**
		 * Plan and order.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_plan( $item ) {
			$order = $item['order_id'] ? wc_get_order( $item['order_id'] ) : null;
			$html  = '<strong>' . esc_html( $item['plan_title'] ) . '</strong><br /><span class="ddwcpp-muted">';

			/* translators: %1$d: payment number, %2$d: number of payments. */
			$html .= sprintf( esc_html__( 'Payment %1$d of %2$d', 'buy-now-pay-later-for-woocommerce' ), absint( $item['sequence'] ), absint( $item['total_count'] ) );

			if ( $order ) {
				$html .= ' &middot; <a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
			}

			return $html . '</span>';
		}

		/**
		 * Amount, with what is still owed.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_amount( $item ) {
			$total     = (float) $item['amount'] + (float) $item['late_fee'];
			$remaining = DDWCPP_Installments_Helper::ddwcpp_get_remaining( $item );
			$html      = '<strong>' . wc_price( $total ) . '</strong>';

			if ( (float) $item['late_fee'] > 0 ) {
				/* translators: %s: late fee. */
				$html .= '<br /><small class="ddwcpp-overdue">' . sprintf( esc_html__( 'incl. %s late fee', 'buy-now-pay-later-for-woocommerce' ), wc_price( $item['late_fee'] ) ) . '</small>';
			}

			if ( 'pending' === $item['status'] && $remaining < $total ) {
				/* translators: %s: amount still owed. */
				$html .= '<br /><small class="ddwcpp-muted">' . sprintf( esc_html__( '%s left', 'buy-now-pay-later-for-woocommerce' ), wc_price( $remaining ) ) . '</small>';
			}

			return $html;
		}

		/**
		 * Due date, relative when it is close.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_due_date( $item ) {
			if ( 'paid' === $item['status'] && $item['paid_date'] ) {
				/* translators: %s: date. */
				return esc_html( sprintf( __( 'Paid %s', 'buy-now-pay-later-for-woocommerce' ), date_i18n( wc_date_format(), strtotime( $item['paid_date'] ) ) ) );
			}

			if ( ! $item['due_date'] ) {
				return '<span class="ddwcpp-muted">' . esc_html__( 'No due date', 'buy-now-pay-later-for-woocommerce' ) . '</span>';
			}

			$timestamp = strtotime( $item['due_date'] );
			$now       = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Due dates are stored in site time.
			$html      = esc_html( date_i18n( wc_date_format(), $timestamp ) );

			if ( 'pending' === $item['status'] ) {
				// Calendar days, as My Account counts them, so the store and the customer see the same number.
				$days = (int) round( ( strtotime( substr( $item['due_date'], 0, 10 ) ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );

				if ( 0 === $days ) {
					$label = __( 'Due today', 'buy-now-pay-later-for-woocommerce' );
				} elseif ( $days < 0 ) {
					/* translators: %s: number of days. */
					$label = sprintf( _n( '%s day late', '%s days late', abs( $days ), 'buy-now-pay-later-for-woocommerce' ), number_format_i18n( abs( $days ) ) );
				} else {
					/* translators: %s: number of days. */
					$label = sprintf( _n( 'in %s day', 'in %s days', $days, 'buy-now-pay-later-for-woocommerce' ), number_format_i18n( $days ) );
				}

				$html .= '<br /><small class="' . ( $timestamp < $now ? 'ddwcpp-overdue' : 'ddwcpp-muted' ) . '">' . esc_html( $label ) . '</small>';
			}

			return $html;
		}

		/**
		 * Status.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_status( $item ) {
			$status = 'pending' === $item['status'] && $item['due_date'] && $item['due_date'] < current_time( 'mysql' ) ? 'overdue' : $item['status'];
			$class  = [
				'paid'      => 'eligible',
				'overdue'   => 'rejected',
				'pending'   => 'pending',
				'cancelled' => 'not_applied',
			];

			return '<mark class="ddwcpp-status ddwcpp-status-' . esc_attr( $class[ $status ] ) . '">' . esc_html( ddwcpp_get_translation( $status ) ) . '</mark>';
		}

		/**
		 * Row actions as buttons.
		 *
		 * @param array $item Installment.
		 * @return string
		 */
		public function column_actions( $item ) {
			if ( 'pending' !== $item['status'] ) {
				return '';
			}

			$url = function ( $action ) use ( $item ) {
				return add_query_arg(
					[
						'action'                => $action,
						'ddwcpp-installment-id' => $item['id'],
						'ddwcpp_nonce'          => wp_create_nonce( 'ddwcpp_installments_bulk' ),
						'view'                  => $this->ddwcpp_get_view(),
					],
					ddwcpp_get_dashboard_url( [ 'menu' => 'installments' ] )
				);
			};

			return '<a class="button button-small ddwcpp-confirm" data-confirm="' . esc_attr__( 'Record this payment as received? Use it for payments made outside the store, such as a bank transfer.', 'buy-now-pay-later-for-woocommerce' ) . '" href="' . esc_url( $url( 'mark_paid' ) ) . '">' . esc_html__( 'Mark paid', 'buy-now-pay-later-for-woocommerce' ) . '</a> '
				. '<span class="ddfw-upgrade-to-pro-tag-wrapper ddwcpp-locked-action"><button type="button" class="button button-small" disabled="disabled">' . esc_html__( 'Remind', 'buy-now-pay-later-for-woocommerce' ) . '</button>' . ddfw_get_pro_tag() . '</span>';
		}

		/**
		 * Totals above the table for the current view.
		 *
		 * @param string $which top or bottom.
		 * @return void
		 */
		protected function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			$filters = DDWCPP_Admin_Functions::ddwcpp_get_installment_filters();
			$total   = in_array( $filters['view'], [ 'paid', 'cancelled' ], true ) ? 0 : $this->helper->ddwcpp_sum_remaining( array_merge( $filters, [ 'view' => $filters['view'] ? $filters['view'] : 'pending' ] ) );

			if ( $total > 0 ) {
				/* translators: %s: amount. */
				echo '<div class="alignleft actions ddwcpp-tablenav-total">' . wp_kses_post( sprintf( __( 'Outstanding in this view: %s', 'buy-now-pay-later-for-woocommerce' ), '<strong>' . wc_price( $total ) . '</strong>' ) ) . '</div>';
			}

			if ( $filters['user_id'] ) {
				$user = get_userdata( $filters['user_id'] );
				echo '<div class="alignleft actions">' . esc_html( $user ? $user->display_name : '' ) . ' <a href="' . esc_url(
					ddwcpp_get_dashboard_url(
						[
							'menu' => 'installments',
							'view' => $this->ddwcpp_get_view(),
						]
					)
				) . '">' . esc_html__( 'Show all customers', 'buy-now-pay-later-for-woocommerce' ) . '</a></div>';
			}
		}

		/**
		 * Empty state.
		 *
		 * @return void
		 */
		public function no_items() {
			$args = [
				'title'       => esc_html__( 'Nothing here', 'buy-now-pay-later-for-woocommerce' ),
				'description' => esc_html__( 'Installments appear once customers check out with Pay Later. Each payment of a plan is listed with its due date, so you can see what is coming in and chase what is late.', 'buy-now-pay-later-for-woocommerce' ),
			];

			// A search or filter that matches nothing is not an empty store.
			foreach ( [ 's', 'customer-id' ] as $filter ) {
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
	}
}
