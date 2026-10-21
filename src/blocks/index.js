/**
 * Registers PostPaid in the WooCommerce Checkout block, with the payment plan choice.
 *
 * Hand written on purpose: it only reads globals WooCommerce already loads, so it needs no build step.
 * The server re-checks the plan, the limits and the credit when the order is placed.
 */
( function () {
	const settings = window.wc.wcSettings.getSetting( 'ddwcpp_postpaid_data', {} );
	const el = window.wp.element.createElement;
	const { useState, useEffect } = window.wp.element;
	const decode = window.wp.htmlEntities.decodeEntities;
	const title = decode( settings.title || 'Pay Later' );
	const i18n = settings.i18n || {};

	const sprintf = ( format, ...args ) => {
		let index = 0;
		return format.replace( /%(\d+\$)?[sd]/g, ( match, position ) => String( args[ position ? parseInt( position, 10 ) - 1 : index++ ] ) );
	};

	const format = ( amount, currency ) => {
		const fixed = amount.toFixed( currency.minorUnit );
		const [ whole, fraction ] = fixed.split( '.' );
		const number = whole.replace( /\B(?=(\d{3})+(?!\d))/g, currency.thousandSeparator ) + ( fraction ? currency.decimalSeparator + fraction : '' );

		return decode( currency.prefix + number + currency.suffix );
	};

	const describe = ( plan, total, currency ) => {
		const factor = Math.pow( 10, currency.minorUnit );
		const sum = total;
		const part = Math.floor( ( sum / plan.count ) * factor ) / factor;
		const first = Math.round( ( sum - part * ( plan.count - 1 ) ) * factor ) / factor;

		return {
			sum,
			summary: 1 === plan.count ? sprintf( i18n.single, format( sum, currency ), plan.term ) : sprintf( i18n.payments, plan.count, format( part, currency ), plan.interval ),
			first,
		};
	};

	const Content = ( props ) => {
		const currency = props.billing ? props.billing.currency : null;
		const total = props.billing ? parseInt( props.billing.cartTotal.value, 10 ) / Math.pow( 10, currency.minorUnit ) : 0;
		const plans = ! settings.balance || total <= settings.balance ? settings.plans || [] : [];
		const [ chosen, setChosen ] = useState( plans.length ? plans[ 0 ].id : '' );
		const selected = plans.find( ( plan ) => plan.id === chosen ) ? chosen : plans.length ? plans[ 0 ].id : '';

		useEffect( () => {
			if ( ! props.eventRegistration ) {
				return undefined;
			}

			return props.eventRegistration.onPaymentSetup( () => ( {
				type: props.emitResponse.responseTypes.SUCCESS,
				meta: { paymentMethodData: { ddwcpp_plan: selected } },
			} ) );
		}, [ selected, props.eventRegistration, props.emitResponse ] );

		return el(
			'div',
			{ className: 'ddwcpp-block-description' },
			settings.description ? el( 'p', null, decode( settings.description ) ) : null,
			settings.credit ? el( 'p', { className: 'ddwcpp-checkout-credit' }, decode( settings.credit ) ) : null,
			currency && plans.length
				? el(
						'fieldset',
						{ className: 'ddwcpp-plan-options' },
						el( 'legend', null, i18n.legend ),
						plans.map( ( plan ) => {
							const info = describe( plan, total, currency );

							return el(
								'label',
								{ className: 'ddwcpp-plan-option', key: plan.id },
								el( 'input', {
									type: 'radio',
									name: 'ddwcpp_plan',
									value: plan.id,
									checked: selected === plan.id,
									onChange: () => setChosen( plan.id ),
								} ),
								el(
									'span',
									{ className: 'ddwcpp-plan-option-body' },
									el( 'span', { className: 'ddwcpp-plan-option-title' }, decode( plan.title ) ),
									el( 'span', { className: 'ddwcpp-plan-option-summary' }, info.summary ),
									el(
										'span',
										{ className: 'ddwcpp-plan-option-meta' },
										( plan.upfront && plan.count > 1 ? sprintf( i18n.today, format( info.first, currency ) ) + ' · ' : '' ) +
											i18n.noFee
									)
								)
							);
						} )
				  )
				: null
		);
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'ddwcpp_postpaid',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: () => true,
		supports: { features: settings.supports || [ 'products' ] },
	} );
} )();
