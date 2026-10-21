<?php
/**
 * PostPaid notification email (plain text).
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit();

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

foreach ( (array) $email_message as $ddwcpp_line ) {
	echo esc_html( wp_strip_all_tags( html_entity_decode( $ddwcpp_line ) ) ) . "\n\n";
}

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce hook.
