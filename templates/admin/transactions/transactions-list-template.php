<?php
/**
 * Transactions list.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Transactions;

use DDWCPostPaid\Helper\Transactions\DDWCPP_Transactions_Helper;
use DDWCPostPaid\Includes\Admin\DDWCPP_Admin_Functions;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'DDWCPP_Transactions_List_Template' ) ) {
	/**
	 * Transactions list class
	 */
	class DDWCPP_Transactions_List_Template extends \WP_List_Table {
		/**
		 * Configuration Variable
		 *
		 * @var array
		 */
		protected $ddwcpp_configuration;

		/**
		 * Transaction Helper Variable
		 *
		 * @var DDWCPP_Transactions_Helper
		 */
		protected $transaction_helper;

		/**
		 * Active filters.
		 *
		 * @var array
		 */
		protected $filters;

		/**
		 * Class constructor
		 *
		 * @param array $ddwcpp_configuration Configuration.
		 */
		public function __construct( $ddwcpp_configuration ) {
			$this->ddwcpp_configuration = $ddwcpp_configuration;
			$this->transaction_helper   = new DDWCPP_Transactions_Helper();
			$this->filters              = DDWCPP_Admin_Functions::ddwcpp_get_transaction_filters();

			parent::__construct(
				[
					'singular' => 'ddwcpp-transaction',
					'plural'   => 'ddwcpp-transactions',
					'ajax'     => false,
				]
			);
		}

		/**
		 * Prepare Items
		 *
		 * @return void
		 */
		public function prepare_items() {
			$this->_column_headers = $this->get_column_info();

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list parameters.
			$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
			$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$per_page = $this->get_items_per_page( 'ddwcpp_transactions_per_page', 20 );

			$this->set_pagination_args(
				[
					'total_items' => $this->transaction_helper->ddwcpp_get_transactions_count( $this->filters ),
					'per_page'    => $per_page,
				]
			);

			$this->items = $this->transaction_helper->ddwcpp_get_transactions(
				array_merge(
					$this->filters,
					[
						'per_page' => $per_page,
						'offset'   => ( $this->get_pagenum() - 1 ) * $per_page,
						'orderby'  => $orderby,
						'order'    => $order,
					]
				)
			);
		}

		/**
		 *  No items
		 *
		 * @return void
		 */
		public function no_items() {
			$args = [
				'title'       => esc_html__( 'No transactions yet', 'buy-now-pay-later-for-woocommerce' ),
				'description' => esc_html__( 'Every Pay Later purchase, repayment, refund and manual adjustment is logged here, so you always know why a balance changed.', 'buy-now-pay-later-for-woocommerce' ),
			];

			// A search or filter that matches nothing is not an empty store.
			foreach ( [ 's', 'ddwcpp-transaction-type', 'ddwcpp-reference', 'customer-id', 'ddwcpp-transaction-from-date', 'ddwcpp-transaction-to-date' ] as $filter ) {
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
				'ddwcpp_transactions_list_columns',
				[
					'id'        => esc_html__( 'ID', 'buy-now-pay-later-for-woocommerce' ),
					'customer'  => esc_html__( 'Customer', 'buy-now-pay-later-for-woocommerce' ),
					'type'      => esc_html__( 'Type', 'buy-now-pay-later-for-woocommerce' ),
					'reference' => esc_html__( 'Description', 'buy-now-pay-later-for-woocommerce' ),
					'amount'    => esc_html__( 'Amount', 'buy-now-pay-later-for-woocommerce' ),
					'order'     => esc_html__( 'Order', 'buy-now-pay-later-for-woocommerce' ),
					'note'      => esc_html__( 'Note', 'buy-now-pay-later-for-woocommerce' ),
					'date'      => esc_html__( 'Date', 'buy-now-pay-later-for-woocommerce' ),
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
				'id'     => [ 'id', true ],
				'amount' => [ 'amount', false ],
				'type'   => [ 'type', false ],
				'date'   => [ 'date', true ],
			];
		}

		/**
		 * Primary column.
		 *
		 * @return string
		 */
		protected function get_default_primary_column_name() {
			return 'id';
		}

		/**
		 * ID with row actions.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_id( $item ) {
			$view_url = ddwcpp_get_dashboard_url(
				[
					'menu'           => 'transactions',
					'action'         => 'view',
					'transaction_id' => $item['id'],
				]
			);

			$actions = [
				'view' => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'buy-now-pay-later-for-woocommerce' ) . '</a>',
			];

			return '<a href="' . esc_url( $view_url ) . '"><strong>#' . absint( $item['id'] ) . '</strong></a>' . $this->row_actions( apply_filters( 'ddwcpp_transactions_list_line_actions', $actions, $item ) );
		}

		/**
		 * Customer.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_customer( $item ) {
			$user = get_userdata( $item['user_id'] );

			if ( ! $user ) {
				return '#' . absint( $item['user_id'] );
			}

			return sprintf(
				'<div class="ddwcpp-customer">%1$s<div><a href="%2$s"><strong>%3$s</strong></a><span>%4$s</span></div></div>',
				get_avatar( $user->ID, 28 ),
				esc_url(
					ddwcpp_get_dashboard_url(
						[
							'menu'        => 'transactions',
							'customer-id' => $user->ID,
						]
					)
				),
				esc_html( $user->display_name ),
				esc_html( $user->user_email )
			);
		}

		/**
		 * Type badge.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_type( $item ) {
			return '<mark class="ddwcpp-status ddwcpp-status-' . esc_attr( $item['type'] ) . '">' . esc_html( ddwcpp_get_translation( $item['type'] ) ) . '</mark>';
		}

		/**
		 * Signed amount.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_amount( $item ) {
			$order = $item['order_id'] ? wc_get_order( $item['order_id'] ) : null;

			return '<span class="' . ( 'credit' === $item['type'] ? 'ddwcpp-positive' : 'ddwcpp-negative' ) . '">' . ( 'credit' === $item['type'] ? '+' : '&minus;' ) . wc_price( $item['amount'], [ 'currency' => $order ? $order->get_currency() : '' ] ) . '</span>';
		}

		/**
		 * Linked order.
		 *
		 * @param array $item Item.
		 * @return string
		 */
		public function column_order( $item ) {
			$order = $item['order_id'] ? wc_get_order( $item['order_id'] ) : null;

			return $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '&mdash;';
		}

		/**
		 * Render a column when no column specific method exists.
		 *
		 * @param array  $item        Item.
		 * @param string $column_name Column.
		 * @return string
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'reference':
					return esc_html( ddwcpp_get_translation( $item['reference'] ) );
				case 'note':
					return $item['note'] ? esc_html( $item['note'] ) : '&mdash;';
				case 'date':
					return esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $item['date'] ) ) );
			}

			return apply_filters( 'ddwcpp_transactions_list_column_' . $column_name, '', $item );
		}

		/**
		 * Filters above the list.
		 *
		 * @param string $which Position of filter.
		 * @return void
		 */
		public function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			$references = [ 'order_debit', 'cleared_dues', 'order_refund', 'order_cancelled', 'manual_credit', 'manual_debit' ];
			$customer   = $this->filters['user_id'] ? get_userdata( $this->filters['user_id'] ) : null;
			?>
			<div class="alignleft actions ddwcpp-transactions-filters">
				<select name="ddwcpp-transaction-type" data-placeholder="<?php esc_attr_e( 'All types', 'buy-now-pay-later-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Type', 'buy-now-pay-later-for-woocommerce' ); ?>">
					<option value=""><?php esc_html_e( 'All types', 'buy-now-pay-later-for-woocommerce' ); ?></option>
					<?php foreach ( [ 'credit', 'debit' ] as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $this->filters['type'], $type ); ?>><?php echo esc_html( ddwcpp_get_translation( $type ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="ddwcpp-reference" data-placeholder="<?php esc_attr_e( 'All descriptions', 'buy-now-pay-later-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Description', 'buy-now-pay-later-for-woocommerce' ); ?>">
					<option value=""><?php esc_html_e( 'All descriptions', 'buy-now-pay-later-for-woocommerce' ); ?></option>
					<?php foreach ( $references as $reference ) : ?>
						<option value="<?php echo esc_attr( $reference ); ?>" <?php selected( $this->filters['reference'], $reference ); ?>><?php echo esc_html( ddwcpp_get_translation( $reference ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="date" name="ddwcpp-transaction-from-date" value="<?php echo esc_attr( $this->filters['from'] ); ?>" aria-label="<?php esc_attr_e( 'From date', 'buy-now-pay-later-for-woocommerce' ); ?>" />
				<input type="date" name="ddwcpp-transaction-to-date" value="<?php echo esc_attr( $this->filters['to'] ); ?>" aria-label="<?php esc_attr_e( 'To date', 'buy-now-pay-later-for-woocommerce' ); ?>" />
				<select class="ddwcpp-users" name="customer-id" data-placeholder="<?php esc_attr_e( 'Any customer', 'buy-now-pay-later-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Customer', 'buy-now-pay-later-for-woocommerce' ); ?>">
					<?php if ( $customer ) : ?>
						<option value="<?php echo esc_attr( $customer->ID ); ?>" selected="selected"><?php echo esc_html( '(#' . $customer->ID . ') ' . $customer->user_login . ' <' . $customer->user_email . '>' ); ?></option>
					<?php endif; ?>
				</select>
				<?php submit_button( esc_html__( 'Filter', 'buy-now-pay-later-for-woocommerce' ), '', 'ddwcpp-filter', false ); ?>
				<?php if ( array_filter( $this->filters ) ) : ?>
					<a href="<?php echo esc_url( ddwcpp_get_dashboard_url( [ 'menu' => 'transactions' ] ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'buy-now-pay-later-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
