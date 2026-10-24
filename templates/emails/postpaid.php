<?php
/**
 * PostPaid notification email (HTML).
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit();

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce hook.
?>
<?php foreach ( (array) $email_message as $ddwcpp_line ) : ?>
	<?php echo wp_kses_post( $ddwcpp_line ); ?>
<?php endforeach; ?>
<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce hook.
