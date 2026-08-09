/**
 * The Testing tab on the form editor.
 *
 * Enqueued rather than printed inside the tab: Carbon Fields renders an html
 * field through React's dangerouslySetInnerHTML, which inserts markup without
 * running any script in it. The same rendering is why the panel is waited for —
 * React mounts the tabs after this file has run.
 */
( function () {
	'use strict';

	var settings = window.LLFFormEditor || {};

	function waitFor( selector, done ) {
		var tries = 0;

		( function look() {
			var el = document.querySelector( selector );

			if ( el ) {
				done( el );
				return;
			}

			if ( ++tries < 100 ) {
				window.setTimeout( look, 100 );
			}
		} )();
	}

	function panel( root ) {
		var to     = root.querySelector( '#llf-test-to' ),
			status = root.querySelector( '#llf-test-status' ),
			post   = root.getAttribute( 'data-post' );

		if ( ! to || ! status ) {
			return;
		}

		function say( text, ok ) {
			status.textContent = text;
			status.className = ok ? 'llf-test-ok' : 'llf-test-bad';
		}

		function send( which, button ) {
			var address = ( to.value || '' ).trim();

			if ( ! address ) {
				say( settings.i18n.noEmail, false );
				to.focus();
				return;
			}

			var data = new FormData();
			data.append( 'action', 'llf_test_mail' );
			data.append( '_wpnonce', settings.nonce );
			data.append( 'post_id', post );
			data.append( 'which', which );
			data.append( 'to', address );

			button.disabled = true;
			say( settings.i18n.sending, true );

			window.fetch( settings.postUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					say( r.message || '', !! r.sent );
					button.disabled = false;
				} )
				.catch( function () {
					say( settings.i18n.failed, false );
					button.disabled = false;
				} );
		}

		[
			[ '#llf-test-notification', 'notification' ],
			[ '#llf-test-auto-reply', 'auto_reply' ]
		].forEach( function ( pair ) {
			var button = root.querySelector( pair[0] );

			if ( button ) {
				button.addEventListener( 'click', function () {
					send( pair[1], button );
				} );
			}
		} );
	}

	waitFor( '.llf-test-mail', panel );
} )();
