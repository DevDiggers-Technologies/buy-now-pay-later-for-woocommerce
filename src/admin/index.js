'use strict';

import './admin.less';

const { ajax, i18n } = window.ddwcppAdminObj;

/**
 * POST to admin-ajax and return the parsed JSON body.
 *
 * @param {string} action AJAX action.
 * @param {Object} data   Fields.
 * @return {Promise<Object>} Response.
 */
const post = ( action, data ) =>
	fetch( ajax.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: new URLSearchParams( { action, nonce: ajax.ajaxNonce, ...data } ),
	} ).then( ( response ) => response.json() );

/**
 * Flash a short status next to a control.
 *
 * @param {Element} anchor  Element to place the message after.
 * @param {string}  message Text.
 * @param {boolean} success Whether it worked.
 */
const flash = ( anchor, message, success ) => {
	const note = document.createElement( 'span' );
	note.className = `ddwcpp-flash ${ success ? 'ddwcpp-positive' : 'ddwcpp-negative' }`;
	note.textContent = message;
	anchor.after( note );
	setTimeout( () => note.remove(), 2500 );
};

/**
 * Refresh the amounts of a customers list row.
 *
 * @param {Element} row  Table row.
 * @param {Object}  data Response data.
 */
const updateRow = ( row, data ) => {
	if ( ! row ) {
		return;
	}
	const set = ( selector, html ) => {
		const element = row.querySelector( selector );
		if ( element ) {
			element.innerHTML = html;
		}
	};
	set( '.ddwcpp-balance', data.balance );
	set( '.ddwcpp-dues', data.dues );
};

const initSelects = ( $ ) => {
	if ( ! $.fn.select2 ) {
		return;
	}

	$( '.ddwcpp-users' ).select2( {
		width: '260px',
		allowClear: true,
		placeholder: $( '.ddwcpp-users' ).data( 'placeholder' ),
		minimumInputLength: 3,
		ajax: {
			transport: ( params, success, failure ) =>
				post( 'ddwcpp_get_users_list', { query: params.data.term || '' } ).then( success ).catch( failure ),
			delay: 250,
			processResults: ( response ) => ( {
				results: ( response.success ? response.data.users : [] ).map( ( user ) => ( {
					id: user.ID,
					text: `(#${ user.ID }) ${ user.user_login } <${ user.user_email }>`,
				} ) ),
			} ),
		},
		language: {
			inputTooShort: ( args ) => `${ i18n.pleaseEnter } ${ args.minimum - args.input.length } ${ i18n.moreCharacter }`,
			noResults: () => i18n.noResult,
		},
	} );
};

const initAdjustDialog = () => {
	const getDialog = () => document.getElementById( 'ddwcpp-adjust-dialog' );
	let current = null;

	document.addEventListener( 'click', ( event ) => {
		const dialog = getDialog();
		if ( ! dialog || typeof dialog.showModal !== 'function' ) {
			return;
		}

		if ( event.target.closest( '.ddwcpp-dialog-cancel' ) ) {
			dialog.close();
			return;
		}

		const opener = event.target.closest( '.ddwcpp-open-adjust' );
		if ( ! opener ) {
			return;
		}
		current = opener;
		dialog.querySelector( '.ddwcpp-dialog-customer' ).textContent = opener.dataset.customer;
		dialog.querySelector( '#ddwcpp-adjust-amount' ).value = '';
		dialog.querySelector( '#ddwcpp-adjust-note' ).value = '';
		dialog.querySelector( '#ddwcpp-adjust-credit' ).checked = true;
		dialog.querySelector( '.ddwcpp-adjust-due' ).hidden = true;
		dialog.showModal();
		dialog.querySelector( '#ddwcpp-adjust-amount' ).focus();
	} );

	// The due date only matters when dues are added.
	document.addEventListener( 'change', ( event ) => {
		if ( 'ddwcpp_adjust_type' === event.target.name ) {
			const due = document.querySelector( '.ddwcpp-adjust-due' );
			if ( due ) {
				due.hidden = 'debit' !== event.target.value;
			}
		}
	} );

	document.addEventListener( 'submit', ( event ) => {
		const dialog = event.target.closest( '#ddwcpp-adjust-dialog' );
		if ( ! dialog ) {
			return;
		}
		event.preventDefault();

		const amount = dialog.querySelector( '#ddwcpp-adjust-amount' );
		const save = dialog.querySelector( '.ddwcpp-dialog-save' );

		if ( ! amount.value || parseFloat( amount.value ) <= 0 ) {
			flash( save, i18n.amountError, false );
			return;
		}

		save.disabled = true;
		post( 'ddwcpp_adjust_balance', {
			user_id: current.dataset.userId,
			type: dialog.querySelector( 'input[name="ddwcpp_adjust_type"]:checked' ).value,
			amount: amount.value,
			note: dialog.querySelector( '#ddwcpp-adjust-note' ).value,
			notify: dialog.querySelector( '#ddwcpp-adjust-notify' ).checked,
			due_days: dialog.querySelector( '#ddwcpp-adjust-due-days' ).value,
		} )
			.then( ( response ) => {
				if ( response.success ) {
					updateRow( current.closest( 'tr' ), response.data );
					dialog.close();
					flash( current, response.data.message, true );
				} else {
					flash( save, response.data.message || i18n.error, false );
				}
			} )
			.catch( () => flash( save, i18n.error, false ) )
			.finally( () => ( save.disabled = false ) );
	} );
};

const initConfirmLinks = () => {
	document.addEventListener( 'click', ( event ) => {
		const confirmLink = event.target.closest( '.ddwcpp-confirm' );
		if ( confirmLink && ! window.confirm( confirmLink.dataset.confirm ) ) {
			event.preventDefault();
		}
	} );
};

jQuery( ( $ ) => {
	initSelects( $ );

	// Turbo navigation re-runs this file on every screen swap. The listeners are delegated
	// to document, so they are bound once and keep working for whatever markup is swapped in.
	if ( ! window.ddwcppAdminBound ) {
		window.ddwcppAdminBound = true;
		initAdjustDialog();
		initConfirmLinks();
	}
} );
