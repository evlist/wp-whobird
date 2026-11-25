// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

// Loads the editor script against a fake `wp` global (no browser, no WordPress).

const test = require( 'node:test' );
const assert = require( 'node:assert' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const blockDir = path.join( __dirname, '..', '..', 'blocks', 'wp-whobird' );

function loadEditorScript() {
	const registered = [];
	const el = ( type, props, ...children ) => ( { type, props, children } );
	const wp = {
		blocks: { registerBlockType: ( name, settings ) => registered.push( { name, settings } ) },
		element: { createElement: el },
		blockEditor: { useBlockProps: () => ( { className: 'wp-block' } ) },
		components: { TextControl: 'TextControl', SelectControl: 'SelectControl' },
		i18n: { __: ( text ) => text },
	};
	const code = fs.readFileSync( path.join( blockDir, 'index.js' ), 'utf8' );
	vm.runInNewContext( code, { window: { wp } } );
	return registered;
}

test( 'registers the block with an edit component', () => {
	const registered = loadEditorScript();
	assert.strictEqual( registered.length, 1 );
	assert.strictEqual( registered[ 0 ].name, 'wpwbd/wp-whobird' );
	assert.strictEqual( typeof registered[ 0 ].settings.edit, 'function' );
} );

test( 'the block name matches block.json', () => {
	const json = JSON.parse( fs.readFileSync( path.join( blockDir, 'block.json' ), 'utf8' ) );
	assert.strictEqual( loadEditorScript()[ 0 ].name, json.name );
} );

test( 'edit shows the period and unit controls and updates attributes', () => {
	const { edit } = loadEditorScript()[ 0 ].settings;
	const calls = [];
	const tree = edit( {
		attributes: { periodNumber: 2, periodUnit: 'week' },
		setAttributes: ( attrs ) => calls.push( attrs ),
	} );
	const [ , number, unit ] = tree.children;
	assert.strictEqual( number.type, 'TextControl' );
	assert.strictEqual( number.props.value, 2 );
	assert.strictEqual( unit.type, 'SelectControl' );
	assert.strictEqual( unit.props.value, 'week' );
	assert.deepStrictEqual( Array.from( unit.props.options, ( o ) => o.value ), [ 'day', 'week', 'month' ] );
	number.props.onChange( '3' );
	unit.props.onChange( 'month' );
	assert.strictEqual( JSON.stringify( calls ), JSON.stringify( [ { periodNumber: '3' }, { periodUnit: 'month' } ] ) );
} );

test( 'edit falls back to the defaults', () => {
	const { edit } = loadEditorScript()[ 0 ].settings;
	const tree = edit( { attributes: {}, setAttributes() {} } );
	assert.strictEqual( tree.children[ 1 ].props.value, 1 );
	assert.strictEqual( tree.children[ 2 ].props.value, 'day' );
} );

test( 'block.json references handles and files that exist', () => {
	const json = JSON.parse( fs.readFileSync( path.join( blockDir, 'block.json' ), 'utf8' ) );
	for ( const key of [ 'editorStyle', 'style', 'render' ] ) {
		assert.ok( json[ key ].startsWith( 'file:./' ), key );
		assert.ok( fs.existsSync( path.join( blockDir, json[ key ].slice( 7 ) ) ), key );
	}
	const php = fs.readFileSync( path.join( blockDir, '..', '..', 'wp-whobird.php' ), 'utf8' );
	for ( const key of [ 'editorScript', 'viewScript' ] ) {
		assert.ok( php.includes( `'${ json[ key ] }'` ), `${ key } handle is registered in wp-whobird.php` );
	}
} );

test( 'every script parses', () => {
	for ( const file of [ 'index.js', 'view.js', 'admin-mapping.js' ] ) {
		const code = fs.readFileSync( path.join( blockDir, file ), 'utf8' );
		assert.doesNotThrow( () => new vm.Script( code, { filename: file } ), file );
	}
} );
