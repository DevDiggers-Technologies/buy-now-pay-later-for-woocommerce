'use strict';

import './front.less';

const settings = window.ddwcppFrontObj || {};

/**
 * Format an amount the way WooCommerce does.
 *
 * @param {number} amount Amount.
 * @return {string} Formatted price.
 */
const formatPrice = ( amount ) => {
	const currency = settings.currency || {};
	const decimals = undefined === currency.decimals ? 2 : currency.decimals;
	const [ whole, fraction ] = amount.toFixed( decimals ).split( '.' );
	const number =
		whole.replace( /\B(?=(\d{3})+(?!\d))/g, undefined === currency.thousand ? ',' : currency.thousand ) +
		( fraction ? ( undefined === currency.decimal ? '.' : currency.decimal ) + fraction : '' );

	return ( currency.format || '%1$s%2$s' ).replace( '%1$s', currency.symbol || '' ).replace( '%2$s', number );
};

/**
 * Re-price the product page message when a variation is picked.
 *
 * @param {HTMLElement} message Message element.
 * @param {number}      price   Variation price.
 */
const updateMessage = ( message, price ) => {
	const plan = JSON.parse( message.dataset.ddwcppPlan || '{}' );
	const amount = message.querySelector( '.ddwcpp-message-amount' );
	if ( ! amount || ! plan.count ) {
		return;
	}

	const factor = Math.pow( 10, settings.currency && undefined !== settings.currency.decimals ? settings.currency.decimals : 2 );
	const each = 1 === plan.count ? price : Math.floor( ( price / plan.count ) * factor ) / factor;

	amount.textContent = formatPrice( each );
};

/**
 * Instant tabs: links inside the PostPaid area swap the section in place. Every
 * section keeps its own URL, so emails, bookmarks, the back button and no JS all work.
 */
const initInstantTabs = () => {
	const base = settings.accountUrl;
	const cache = new Map();

	if ( ! base || ! window.fetch || ! window.DOMParser ) {
		return;
	}

	const isSection = ( link ) => link && link.href.indexOf( base ) === 0 && ! link.target && ! link.hasAttribute( 'download' );

	const load = ( url ) => {
		if ( ! cache.has( url ) ) {
			cache.set(
				url,
				fetch( url, { credentials: 'same-origin' } )
					.then( ( response ) => ( response.ok ? response.text() : Promise.reject() ) )
					.catch( ( error ) => {
						cache.delete( url );
						throw error;
					} )
			);
		}

		return cache.get( url );
	};

	const swap = ( url, push ) => {
		const current = document.querySelector( '.ddwcpp-account' );

		if ( ! current ) {
			window.location.href = url;
			return;
		}

		current.classList.add( 'is-loading' );
		current.setAttribute( 'aria-busy', 'true' );

		load( url )
			.then( ( html ) => {
				const next = new DOMParser().parseFromString( html, 'text/html' ).querySelector( '.ddwcpp-account' );

				if ( ! next ) {
					throw new Error( 'missing' );
				}

				current.replaceWith( next );
				cache.clear(); // Figures change after a payment; never show a stale section twice.

				if ( push ) {
					window.history.pushState( { ddwcpp: true }, '', url );
				}

				const active = next.querySelector( '.ddwcpp-nav-item.is-active' );

				if ( active ) {
					active.focus( { preventScroll: true } );
				}
			} )
			.catch( () => {
				window.location.href = url;
			} );
	};

	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '.ddwcpp-account a' );

		if ( ! isSection( link ) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || 0 !== event.button ) {
			return;
		}

		event.preventDefault();
		swap( link.href, true );
	} );

	// Start the request on hover, so the click usually lands on a loaded section.
	document.addEventListener( 'pointerover', ( event ) => {
		const link = event.target.closest( '.ddwcpp-nav-item' );

		if ( isSection( link ) && ! link.classList.contains( 'is-active' ) ) {
			load( link.href ).catch( () => {} );
		}
	} );

	window.addEventListener( 'popstate', () => swap( window.location.href, false ) );
};

document.addEventListener( 'DOMContentLoaded', () => {
	initInstantTabs();

	const dialog = document.getElementById( 'ddwcpp-message-dialog' );

	document.addEventListener( 'click', ( event ) => {
		if ( dialog && event.target.closest( '.ddwcpp-message-more' ) ) {
			event.preventDefault();
			dialog.showModal();
		} else if ( dialog && event.target === dialog ) {
			// A click on the backdrop lands on the dialog itself.
			dialog.close();
		}
	} );

	// Variation events are jQuery events, so they need jQuery to listen.
	if ( window.jQuery ) {
		window.jQuery( '.variations_form' ).on( 'found_variation', ( event, variation ) => {
			const summary = event.target.closest( '.summary, .product' );
			const message = summary && summary.querySelector( '.ddwcpp-message' );

			if ( message && variation && variation.display_price ) {
				updateMessage( message, parseFloat( variation.display_price ) );
			}
		} );
	}
} );
