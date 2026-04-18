/**
 * Super Fast Blog AI — Admin JS v2.0
 *
 * Shared utilities for all admin pages. Individual page-specific scripts
 * are inlined within their respective admin page PHP templates.
 *
 * Global: window.SFBA (set via wp_localize_script in SFBA_Settings)
 *   { apiBase, nonce, ajaxUrl, i18n }
 */
/* global SFBA */
( function () {
	'use strict';

	if ( typeof SFBA === 'undefined' ) {
		return;
	}

	// ── Shared REST helper ─────────────────────────────────────────────────────

	/**
	 * POST to a SFBA REST endpoint.
	 *
	 * @param {string} path  Relative path (e.g. '/settings').
	 * @param {object} body  Request body.
	 * @returns {Promise<object>}
	 */
	window.sfbaPost = async function ( path, body ) {
		const response = await fetch( SFBA.apiBase + path, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   SFBA.nonce,
			},
			body: JSON.stringify( body ),
		} );
		return response.json();
	};

	/**
	 * GET from a SFBA REST endpoint.
	 *
	 * @param {string} path  Relative path.
	 * @returns {Promise<object>}
	 */
	window.sfbaGet = async function ( path ) {
		const response = await fetch( SFBA.apiBase + path, {
			headers: { 'X-WP-Nonce': SFBA.nonce },
		} );
		return response.json();
	};

	/**
	 * DELETE a SFBA REST resource.
	 *
	 * @param {string} path  Relative path.
	 * @returns {Promise<object>}
	 */
	window.sfbaDelete = async function ( path ) {
		const response = await fetch( SFBA.apiBase + path, {
			method:  'DELETE',
			headers: { 'X-WP-Nonce': SFBA.nonce },
		} );
		return response.json();
	};

	// ── Settings page: save API keys ───────────────────────────────────────────

	document.querySelectorAll( '.sfba-save-key-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', async function () {
			const provider  = this.dataset.provider;
			const input     = document.getElementById( 'sfba-key-' + provider );
			const feedback  = document.getElementById( 'sfba-feedback-' + provider );
			const key       = input ? input.value.trim() : '';

			if ( ! key ) {
				if ( feedback ) { feedback.textContent = 'Please enter an API key.'; feedback.className = 'sfba-provider-feedback error'; }
				return;
			}

			this.disabled    = true;
			this.textContent = SFBA.i18n.saving;

			try {
				const data = await window.sfbaPost( '/settings', { provider_keys: { [ provider ]: key } } );
				if ( feedback ) {
					feedback.textContent = data.success ? SFBA.i18n.saved : ( data.message || SFBA.i18n.failed );
					feedback.className   = 'sfba-provider-feedback ' + ( data.success ? 'success' : 'error' );
				}
				if ( data.success ) {
					const dot = document.querySelector( '.sfba-status-dot[data-provider="' + provider + '"]' );
					if ( dot ) { dot.classList.remove( 'disconnected' ); dot.classList.add( 'connected' ); }
				}
			} catch ( e ) {
				if ( feedback ) { feedback.textContent = SFBA.i18n.failed; feedback.className = 'sfba-provider-feedback error'; }
			}

			this.disabled    = false;
			this.textContent = 'Save Key';
		} );
	} );

	// ── Settings page: remove key ──────────────────────────────────────────────

	document.querySelectorAll( '.sfba-remove-key-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', async function () {
			const provider = this.dataset.provider;
			const input    = document.getElementById( 'sfba-key-' + provider );
			const feedback = document.getElementById( 'sfba-feedback-' + provider );

			this.disabled = true;

			try {
				const data = await window.sfbaPost( '/settings', { provider_keys: { [ provider ]: '' } } );
				if ( data.success ) {
					if ( input )    { input.value = ''; }
					if ( feedback ) { feedback.textContent = SFBA.i18n.key_removed; feedback.className = 'sfba-provider-feedback success'; }
					const dot = document.querySelector( '.sfba-status-dot[data-provider="' + provider + '"]' );
					if ( dot ) { dot.classList.add( 'disconnected' ); dot.classList.remove( 'connected' ); }
				}
			} catch ( e ) {
				if ( feedback ) { feedback.textContent = SFBA.i18n.failed; feedback.className = 'sfba-provider-feedback error'; }
			}

			this.disabled = false;
		} );
	} );

	// ── Settings page: test connection ─────────────────────────────────────────

	document.querySelectorAll( '.sfba-test-key-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', async function () {
			const provider  = this.dataset.provider;
			const feedback  = document.getElementById( 'sfba-feedback-' + provider );
			const origLabel = this.textContent;

			this.disabled    = true;
			this.textContent = SFBA.i18n.testing;

			try {
				const data = await window.sfbaPost( '/settings/test-provider', { provider } );
				if ( feedback ) {
					feedback.textContent = data.success ? SFBA.i18n.connected : ( data.message || SFBA.i18n.failed );
					feedback.className   = 'sfba-provider-feedback ' + ( data.success ? 'success' : 'error' );
				}
			} catch ( e ) {
				if ( feedback ) { feedback.textContent = SFBA.i18n.failed; feedback.className = 'sfba-provider-feedback error'; }
			}

			this.disabled    = false;
			this.textContent = origLabel;
		} );
	} );

} )();
