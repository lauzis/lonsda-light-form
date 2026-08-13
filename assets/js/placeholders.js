/**
 * Click a placeholder token to copy it.
 *
 * Its own file rather than part of the form editor's: the same panel appears on
 * the translations screen, where a translator retypes wording that has tokens in
 * it and a mistyped one is invisible until an email goes out wrong. Copying
 * beats retyping, on both screens, so the behaviour cannot belong to either.
 */
( function () {
	'use strict';

	function attach( root ) {
		root.addEventListener( 'click', function ( event ) {
			var code = event.target.closest( '.llf-copy' );

			if ( ! code ) {
				return;
			}

			var text = code.textContent.trim();

			function done() {
				code.classList.add( 'llf-copied' );
				window.setTimeout( function () {
					code.classList.remove( 'llf-copied' );
				}, 700 );
			}

			// No clipboard permission, or an insecure origin: selecting the
			// text at least leaves it one keystroke from copied.
			function select() {
				var range = document.createRange();
				range.selectNodeContents( code );
				var selection = window.getSelection();
				selection.removeAllRanges();
				selection.addRange( range );
				done();
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, select );
				return;
			}

			select();
		} );

		// The same from the keyboard, since each token is focusable.
		root.addEventListener( 'keydown', function ( event ) {
			if ( ( 'Enter' === event.key || ' ' === event.key ) && event.target.closest( '.llf-copy' ) ) {
				event.preventDefault();
				event.target.click();
			}
		} );
	}

	/**
	 * The panel is an ordinary part of the page on the translations screen and
	 * an ordinary metabox on the form editor, so it is usually there already —
	 * but this script may still run first, and waiting costs nothing.
	 */
	var tries = 0;

	( function look() {
		var panel = document.querySelector( '.llf-placeholders' );

		if ( panel ) {
			attach( panel );
			return;
		}

		if ( ++tries < 100 ) {
			window.setTimeout( look, 100 );
		}
	} )();
} )();
