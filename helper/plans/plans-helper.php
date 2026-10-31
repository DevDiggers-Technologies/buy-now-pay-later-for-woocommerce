<?php
/**
 * Payment plans helper.
 *
 * Two built in plans: Pay in 4 and Pay in 30 days. Their schedules are fixed; title,
 * description, availability and order amount limits are stored in one option.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Helper\Plans;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Plans_Helper' ) ) {
	/**
	 * Plans helper class
	 */
	class DDWCPP_Plans_Helper {
		/**
		 * Option that stores every plan, keyed by plan ID.
		 */
		const OPTION = '_ddwcpp_plans';


		/**
		 * Fields of a plan with their defaults.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_plan_defaults() {
			return [
				'id'          => '',
				'title'       => '',
				'description' => '',
				'count'       => 4,
				'interval'    => 2,
				'unit'        => 'week',
				'upfront'     => '',
				'status'      => 'active',
				'position'    => 0,
			];
		}

		/**
		 * The two built in plans.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_presets() {
			return [
				'pay-in-4'  => [
					'title'       => esc_html__( 'Pay in 4', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => esc_html__( 'Four equal payments, one every two weeks. The first is due at purchase.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'count'       => 4,
					'interval'    => 2,
					'unit'        => 'week',
					'upfront'     => 'yes',
					'position'    => 1,
				],
				'pay-in-30' => [
					'title'       => esc_html__( 'Pay in 30 days', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'description' => esc_html__( 'Get it now, pay the full amount within 30 days.', 'devdiggers-buy-now-pay-later-for-woocommerce' ),
					'count'       => 1,
					'interval'    => 30,
					'unit'        => 'day',
					'position'    => 2,
				],
			];
		}

		/**
		 * Both plans. Only the on/off status is stored; the rest comes from the preset.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_plans() {
			$stored = get_option( self::OPTION, [] );
			$stored = is_array( $stored ) ? $stored : [];
			$plans  = [];

			foreach ( self::ddwcpp_get_presets() as $id => $preset ) {
				$plan = wp_parse_args( array_merge( $preset, [ 'id' => $id ] ), self::ddwcpp_get_plan_defaults() );

				if ( isset( $stored[ $id ]['status'] ) ) {
					$plan['status'] = 'inactive' === $stored[ $id ]['status'] ? 'inactive' : 'active';
				}

				$plans[ $id ] = $plan;
			}

			return $plans;
		}

		/**
		 * Active plans.
		 *
		 * @return array
		 */
		public static function ddwcpp_get_active_plans() {
			return array_filter(
				self::ddwcpp_get_plans(),
				function ( $plan ) {
					return 'active' === $plan['status'];
				}
			);
		}

		/**
		 * Switch the two plans on or off from the submitted settings. Anything else stored in the
		 * option is kept as it is.
		 *
		 * @param mixed $value Submitted value, plan ID => [ 'status' => 'active' ].
		 * @return array
		 */
		public static function ddwcpp_sanitize_statuses( $value ) {
			$value  = is_array( $value ) ? $value : [];
			$stored = get_option( self::OPTION, [] );
			$stored = is_array( $stored ) ? $stored : [];

			foreach ( array_keys( self::ddwcpp_get_presets() ) as $id ) {
				$row           = isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ? $stored[ $id ] : [];
				$row['status'] = isset( $value[ $id ]['status'] ) && 'active' === $value[ $id ]['status'] ? 'active' : 'inactive';
				$stored[ $id ] = $row;
			}

			return $stored;
		}

		/**
		 * Split an amount into the plan's installments.
		 *
		 * Equal parts rounded to the store's decimals; the rounding difference lands on the first
		 * installment so the parts always add up to the total.
		 *
		 * @param array $plan  Plan.
		 * @param float $total Amount to split.
		 * @param int   $start Purchase timestamp.
		 * @return array List of [ 'amount' => float, 'due_date' => 'Y-m-d H:i:s' ].
		 */
		public static function ddwcpp_build_schedule( $plan, $total, $start = 0 ) {
			$count    = max( 1, absint( $plan['count'] ) );
			$decimals = wc_get_price_decimals();
			$part     = floor( $total / $count * pow( 10, $decimals ) ) / pow( 10, $decimals );
			$first    = round( $total - $part * ( $count - 1 ), $decimals );
			$start    = $start ? $start : time();
			$rows     = [];

			for ( $i = 0; $i < $count; $i++ ) {
				// An upfront plan's first payment is due today, the rest follow one interval apart.
				$steps  = ! empty( $plan['upfront'] ) ? $i : $i + 1;
				$rows[] = [
					'amount'   => 0 === $i ? $first : $part,
					'due_date' => wp_date( 'Y-m-d 23:59:59', $steps ? strtotime( '+' . ( $steps * absint( $plan['interval'] ) ) . ' ' . $plan['unit'], $start ) : $start ),
				];
			}

			return $rows;
		}

		/**
		 * "every 2 weeks" style text.
		 *
		 * @param array $plan Plan.
		 * @return string
		 */
		public static function ddwcpp_get_interval_label( $plan ) {
			$interval = absint( $plan['interval'] );

			switch ( $plan['unit'] ) {
				case 'day':
					/* translators: %d: number of days. */
					return 1 === $interval ? esc_html__( 'every day', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : sprintf( esc_html__( 'every %d days', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $interval );
				default:
					/* translators: %d: number of weeks. */
					return 1 === $interval ? esc_html__( 'every week', 'devdiggers-buy-now-pay-later-for-woocommerce' ) : sprintf( esc_html__( 'every %d weeks', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $interval );
			}
		}

		/**
		 * "in 30 days" style text, for single payment plans.
		 *
		 * @param array $plan Plan.
		 * @return string
		 */
		public static function ddwcpp_get_term_label( $plan ) {
			$interval = absint( $plan['interval'] );

			switch ( $plan['unit'] ) {
				case 'day':
					/* translators: %d: number of days. */
					return sprintf( _n( 'in %d day', 'in %d days', $interval, 'devdiggers-buy-now-pay-later-for-woocommerce' ), $interval );
				default:
					/* translators: %d: number of weeks. */
					return sprintf( _n( 'in %d week', 'in %d weeks', $interval, 'devdiggers-buy-now-pay-later-for-woocommerce' ), $interval );
			}
		}

		/**
		 * One line summary of a plan for an amount, e.g. "4 payments of $25.00 every 2 weeks".
		 *
		 * @param array $plan   Plan.
		 * @param float $amount Order total, 0 to describe the plan alone.
		 * @return string HTML.
		 */
		public static function ddwcpp_get_summary( $plan, $amount = 0 ) {
			$count = absint( $plan['count'] );

			if ( $amount <= 0 ) {
				if ( 1 === $count ) {
					/* translators: %s: term, e.g. "in 30 days". */
					return sprintf( esc_html__( 'Pay the full amount %s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), self::ddwcpp_get_term_label( $plan ) );
				}

				/* translators: %1$d: number of payments, %2$s: interval, e.g. "every 2 weeks". */
				return sprintf( esc_html__( '%1$d payments %2$s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $count, self::ddwcpp_get_interval_label( $plan ) );
			}

			$total    = $amount;
			$schedule = self::ddwcpp_build_schedule( $plan, $total );

			if ( 1 === $count ) {
				/* translators: %1$s: amount, %2$s: term, e.g. "in 30 days". */
				return sprintf( esc_html__( 'Pay %1$s %2$s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), wc_price( $total ), self::ddwcpp_get_term_label( $plan ) );
			}

			/* translators: %1$d: number of payments, %2$s: amount of each payment, %3$s: interval, e.g. "every 2 weeks". */
			return sprintf( esc_html__( '%1$d payments of %2$s %3$s', 'devdiggers-buy-now-pay-later-for-woocommerce' ), $count, wc_price( end( $schedule )['amount'] ), self::ddwcpp_get_interval_label( $plan ) );
		}
	}
}
