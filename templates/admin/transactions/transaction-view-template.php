<?php
/**
 * Single transaction, shared by the admin screen and My Account.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Templates\Admin\Transactions;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Transaction_View_Template' ) ) {
	/**
	 * Transaction view template class
	 */
	class DDWCPP_Transaction_View_Template {
		/**
		 * Constructor
		 *
		 * @param array $transaction Transaction row.
		 */
		public function __construct( $transaction ) {
			$this->ddwcpp_render( $transaction );
		}

		/**
		 * Render.
		 *
		 * @param array $transaction Transaction row.
		 * @return void
		 */
		protected function ddwcpp_render( $transaction ) {
			$is_admin = is_admin();
			$customer = get_userdata( $transaction['user_id'] );
			$order    = $transaction['order_id'] ? wc_get_order( $transaction['order_id'] ) : null;
			$rows     = [
				esc_html__( 'Transaction', 'devdiggers-buy-now-pay-later-for-woocommerce' ) => '#' . esc_html( $transaction['id'] ),
				esc_html__( 'Date', 'devdiggers-buy-now-pay-later-for-woocommerce' )   => esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $transaction['date'] ) ) ),
				esc_html__( 'Type', 'devdiggers-buy-now-pay-later-for-woocommerce' )   => '<mark class="ddwcpp-status ddwcpp-status-' . esc_attr( $transaction['type'] ) . '">' . esc_html( ddwcpp_get_translation( $transaction['type'] ) ) . '</mark>',
				esc_html__( 'Description', 'devdiggers-buy-now-pay-later-for-woocommerce' ) => esc_html( ddwcpp_get_translation( $transaction['reference'] ) ),
				esc_html__( 'Amount', 'devdiggers-buy-now-pay-later-for-woocommerce' ) => wc_price( $transaction['amount'], [ 'currency' => $order ? $order->get_currency() : '' ] ),
			];

			if ( $order ) {
				$order_url = $is_admin ? $order->get_edit_order_url() : $order->get_view_order_url();

				$rows[ esc_html__( 'Order', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ] = '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
			}

			if ( $is_admin ) {
				$rows[ esc_html__( 'Customer', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ] = $customer ? '<a href="' . esc_url(
					ddwcpp_get_dashboard_url(
						[
							'menu'        => 'transactions',
							'customer-id' => $customer->ID,
						]
					)
				) . '">' . esc_html( $customer->display_name . ' <' . $customer->user_email . '>' ) . '</a>' : '#' . absint( $transaction['user_id'] );
			}

			$rows[ esc_html__( 'Note', 'devdiggers-buy-now-pay-later-for-woocommerce' ) ] = $transaction['note'] ? esc_html( $transaction['note'] ) : '&mdash;';

			if ( $is_admin ) {
				?>
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Transaction Details', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></h1>
				<a href="<?php echo esc_url( ddwcpp_get_dashboard_url( [ 'menu' => 'transactions' ] ) ); ?>" class="page-title-action button"><?php esc_html_e( 'Back to Transactions', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a>
				<hr class="wp-header-end" />
				<?php
			} else {
				?>
				<p><a href="<?php echo esc_url( ddwcpp_get_account_url() ); ?>">&larr; <?php esc_html_e( 'Back', 'devdiggers-buy-now-pay-later-for-woocommerce' ); ?></a></p>
				<?php
			}
			?>
			<table class="<?php echo $is_admin ? 'widefat striped ddwcpp-transaction-details-table' : 'shop_table ddwcpp-transaction-details-table'; ?>">
				<tbody>
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><?php echo wp_kses_post( $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}
	}
}
