/**
 * Settings page behaviour: the import/export panel and the reCAPTCHA test.
 *
 * Enqueued rather than printed inside the fields it drives. Carbon Fields
 * renders an html field through React's dangerouslySetInnerHTML, which inserts
 * markup without executing any script in it — so anything inline there is dead
 * code that looks fine.
 *
 * The same rendering is why elements are waited for. React mounts the fields
 * after this file has run, so nothing here can assume the panel exists yet.
 */
( function () {
	'use strict';

	var settings = window.LLFSettings || {};

	/**
	 * Calls back once a selector matches, or gives up.
	 *
	 * A MutationObserver would be tidier, but the panel appears once, early,
	 * and this cannot leave an observer running on a page it never mounts on.
	 */
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

	function transferPanel( root ) {
		var all  = root.querySelector( '#llf-export-all' ),
			ones = root.querySelectorAll( '.llf-export-one' ),
			go   = root.querySelector( '#llf-export-go' );

		function chosen() {
			return Array.prototype.filter.call( ones, function ( c ) {
				return c.checked;
			} ).map( function ( c ) {
				return c.value;
			} );
		}

		function sync() {
			if ( ! go ) {
				return;
			}

			var picked = chosen();

			// Everything selected means no ids at all, which is what the plain
			// link already does — so the button keeps working without this.
			go.href = picked.length === ones.length
				? settings.exportUrl
				: settings.exportUrl + '&ids=' + encodeURIComponent( picked.join( ',' ) );

			go.setAttribute( 'aria-disabled', picked.length === 0 ? 'true' : 'false' );
			go.style.pointerEvents = picked.length === 0 ? 'none' : '';
			go.style.opacity = picked.length === 0 ? '0.5' : '';
		}

		if ( all ) {
			all.addEventListener( 'change', function () {
				Array.prototype.forEach.call( ones, function ( c ) {
					c.checked = all.checked;
				} );
				sync();
			} );
		}

		Array.prototype.forEach.call( ones, function ( c ) {
			c.addEventListener( 'change', function () {
				if ( all ) {
					all.checked = chosen().length === ones.length;
				}
				sync();
			} );
		} );

		sync();

		var file   = root.querySelector( '#llf-import-file' ),
			button = root.querySelector( '#llf-import-go' ),
			status = root.querySelector( '#llf-import-status' );

		if ( ! file || ! button ) {
			return;
		}

		file.addEventListener( 'change', function () {
			button.disabled = ! file.files.length;
			status.textContent = '';
		} );

		button.addEventListener( 'click', function () {
			if ( ! file.files.length ) {
				return;
			}

			// Posted on its own: this panel sits inside the settings form and
			// cannot be a form itself.
			var data = new FormData();
			data.append( 'action', 'llf_import' );
			data.append( '_wpnonce', settings.nonce );
			data.append( 'llf_file', file.files[0] );

			button.disabled = true;
			status.textContent = settings.i18n.importing;

			window.fetch( settings.postUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					status.textContent = r.message || '';
					button.disabled = false;

					if ( r.created ) {
						window.location.reload();
					}
				} )
				.catch( function () {
					status.textContent = settings.i18n.importFailed;
					button.disabled = false;
				} );
		} );
	}

	function recaptchaTest( root ) {
		var holder = root.querySelector( '#llf-recaptcha-widget' ),
			button = root.querySelector( '#llf-recaptcha-test' ),
			status = root.querySelector( '#llf-recaptcha-status' ),
			widget = null;

		if ( ! holder || ! button ) {
			return;
		}

		function say( text, ok ) {
			status.textContent = text;
			status.className = 'llf-recaptcha-status ' + ( ok ? 'llf-ok' : 'llf-bad' );
		}

		// Rendered explicitly because the tick box did not exist when Google's
		// script scanned the page — React had not mounted this field yet.
		function render() {
			if ( ! window.grecaptcha || ! window.grecaptcha.render ) {
				say( settings.i18n.scriptFailed, false );
				button.disabled = true;
				return;
			}

			try {
				widget = window.grecaptcha.render( holder, { sitekey: settings.siteKey } );
			} catch ( e ) {
				// Google refuses to render an invalid key, which is itself the
				// answer to half of what this test is for.
				say( settings.i18n.badSiteKey, false );
				button.disabled = true;
			}
		}

		if ( window.grecaptcha && window.grecaptcha.render ) {
			render();
		} else {
			window.llfRecaptchaReady = render;
		}

		button.addEventListener( 'click', function () {
			var token = window.grecaptcha && null !== widget
				? window.grecaptcha.getResponse( widget )
				: '';

			if ( ! token ) {
				say( settings.i18n.tickFirst, false );
				return;
			}

			button.disabled = true;
			say( settings.i18n.checking, true );

			var data = new FormData();
			data.append( 'action', 'llf_recaptcha_test' );
			data.append( '_wpnonce', settings.nonce );
			data.append( 'token', token );

			window.fetch( settings.postUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( r ) {
					say( r.message || '', !! r.success );
					button.disabled = false;

					if ( window.grecaptcha && null !== widget ) {
						window.grecaptcha.reset( widget );
					}
				} )
				.catch( function () {
					say( settings.i18n.testFailed, false );
					button.disabled = false;
				} );
		} );
	}

	waitFor( '.llf-transfer', transferPanel );

	if ( settings.siteKey ) {
		waitFor( '.llf-recaptcha-test', recaptchaTest );
	}
} )();
