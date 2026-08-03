/**
 * Editor UI for the lonsda/form block.
 *
 * Deliberately plain JavaScript with wp.element.createElement rather than JSX,
 * so the plugin needs no build step — one small editor script is not worth a
 * toolchain someone has to install before they can change a label.
 */
( function ( blocks, element, blockEditor, components, data, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'lonsda/form', {
		edit: function ( props ) {
			var blockProps = blockEditor.useBlockProps();

			// Forms are exposed on the REST API for the editor's benefit only;
			// the front end reads the custom table directly.
			var forms = data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'llf_form', {
					per_page: -1,
					status: 'publish',
				} );
			}, [] );

			var options = [ { label: __( 'Select a form…', 'lonsda-light-form' ), value: 0 } ];

			// llf_id, not form.id: a form is edited as a post but rendered from
			// the forms table, and the table id is what the shortcode and the
			// Forms list use. A form saved but not yet projected has no table
			// id, so it is left out rather than offered as an unusable choice.
			( forms || [] ).forEach( function ( form ) {
				if ( ! form.llf_id ) {
					return;
				}

				options.push( {
					label: form.title.rendered || __( '(no title)', 'lonsda-light-form' ),
					value: form.llf_id,
				} );
			} );

			var chosen = props.attributes.formId;

			return el(
				'div',
				blockProps,
				el(
					components.Placeholder,
					{
						icon: 'feedback',
						label: __( 'Lonsda Form', 'lonsda-light-form' ),
						instructions: forms === null
							? __( 'Loading forms…', 'lonsda-light-form' )
							: __( 'Pick the form to show here. Its fields are edited under Lonsda Forms, so changes appear everywhere it is used.', 'lonsda-light-form' ),
					},
					forms !== null && options.length === 1
						? el( 'p', {}, __( 'No forms yet — create one under Lonsda Forms first.', 'lonsda-light-form' ) )
						: el( components.SelectControl, {
							label: __( 'Form', 'lonsda-light-form' ),
							value: chosen,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { formId: parseInt( value, 10 ) || 0 } );
							},
						} )
				)
			);
		},

		// Dynamic block: the markup comes from render.php on every request, so
		// a form edited later is not left stale inside saved post content.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.i18n
);
