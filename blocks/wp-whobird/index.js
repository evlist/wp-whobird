// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Block editor code for the WhoBird block (registration and edit interface).
 *
 * Plain JavaScript, no build step: it relies on the WordPress script globals
 * (wp.blocks, wp.element, wp.blockEditor, wp.components, wp.i18n). The matching
 * script dependencies are declared in wp-whobird.php.
 */

( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { TextControl, SelectControl } = wp.components;
	const { __ } = wp.i18n;

	/**
	 * Edit component: the controls for the observation period and its unit.
	 *
	 * @param {Object} props Block props from the editor.
	 * @return {Element} Element to render in the editor.
	 */
	function Edit( props ) {
		const { attributes, setAttributes } = props;

		return el(
			'div',
			useBlockProps(),
			el(
				'p',
				null,
				el( 'i', { className: 'fas fa-crow' } ),
				__( 'WhoBIRD observations', 'wp-whobird' )
			),
			el( TextControl, {
				label: __( 'Observation Period', 'wp-whobird' ),
				value: attributes.periodNumber || 1,
				onChange: ( value ) => setAttributes( { periodNumber: value } ),
				type: 'number',
			} ),
			el( SelectControl, {
				label: __( 'Period Unit', 'wp-whobird' ),
				value: attributes.periodUnit || 'day',
				options: [
					{ label: __( 'Day(s)', 'wp-whobird' ), value: 'day' },
					{ label: __( 'Week(s)', 'wp-whobird' ), value: 'week' },
					{ label: __( 'Month(s)', 'wp-whobird' ), value: 'month' },
				],
				onChange: ( value ) => setAttributes( { periodUnit: value } ),
			} )
		);
	}

	// The metadata (name, attributes, ...) comes from block.json, merged by WordPress.
	registerBlockType( 'wpwbd/wp-whobird', { edit: Edit } );
} )( window.wp );
