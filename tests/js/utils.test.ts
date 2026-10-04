/**
 * External dependencies
 */
import { getInitials, isURL, isValidUrl, PurifyElement } from '@/js/utils';

describe( 'getInitials', () => {
	it( 'returns "?" for empty or invalid input', () => {
		expect( getInitials( '' ) ).toBe( '?' );
		expect( getInitials( '   ' ) ).toBe( '?' );
		// @ts-expect-error - exercising the invalid-input guard.
		expect( getInitials( null ) ).toBe( '?' );
		// @ts-expect-error - exercising the invalid-input guard.
		expect( getInitials( undefined ) ).toBe( '?' );
		// @ts-expect-error - exercising the invalid-input guard.
		expect( getInitials( 42 ) ).toBe( '?' );
	} );

	it( 'uppercases a single character name', () => {
		expect( getInitials( 'a' ) ).toBe( 'A' );
	} );

	it( 'returns the first two characters of a single-word name', () => {
		expect( getInitials( 'design' ) ).toBe( 'DE' );
	} );

	it( 'combines the initials of a multi-word name', () => {
		expect( getInitials( 'One Design' ) ).toBe( 'OD' );
		expect( getInitials( 'jane doe smith' ) ).toBe( 'JD' );
	} );

	it( 'splits on common separators', () => {
		expect( getInitials( 'one-design' ) ).toBe( 'OD' );
		expect( getInitials( 'one_design' ) ).toBe( 'OD' );
		expect( getInitials( 'one.design' ) ).toBe( 'OD' );
		expect( getInitials( 'one,design' ) ).toBe( 'OD' );
	} );

	it( 'trims surrounding whitespace before extracting', () => {
		expect( getInitials( '   spaced name   ' ) ).toBe( 'SN' );
	} );
} );

describe( 'isURL', () => {
	it( 'accepts well-formed http(s) URLs', () => {
		expect( isURL( 'https://example.com' ) ).toBe( true );
		expect( isURL( 'http://example.com' ) ).toBe( true );
		expect( isURL( 'https://sub.example.com/path?query=1' ) ).toBe( true );
		expect( isURL( 'https://example.com:8080/path' ) ).toBe( true );
	} );

	it( 'rejects non-http(s) or malformed strings', () => {
		expect( isURL( 'ftp://example.com' ) ).toBe( false );
		expect( isURL( 'example.com' ) ).toBe( false );
		expect( isURL( 'not a url' ) ).toBe( false );
		expect( isURL( '' ) ).toBe( false );
		expect( isURL( 'https://localhost' ) ).toBe( false );
	} );
} );

describe( 'isValidUrl', () => {
	it( 'accepts parseable http(s) URLs', () => {
		expect( isValidUrl( 'https://example.com' ) ).toBe( true );
		expect( isValidUrl( 'http://example.com/path' ) ).toBe( true );
	} );

	it( 'rejects unparseable strings and non-http(s) schemes', () => {
		expect( isValidUrl( 'not a url' ) ).toBe( false );
		expect( isValidUrl( '' ) ).toBe( false );
		expect( isValidUrl( 'ftp://example.com' ) ).toBe( false );
	} );
} );

describe( 'PurifyElement', () => {
	it( 'strips all HTML tags, keeping text content', () => {
		expect( PurifyElement( '<strong>Bold</strong>' ) ).toBe( 'Bold' );
		expect( PurifyElement( '<a href="#">link</a> text' ) ).toBe(
			'link text'
		);
	} );

	it( 'removes script tags entirely', () => {
		expect(
			PurifyElement( 'safe<script>alert(1)</script>' )
		).not.toContain( '<script>' );
	} );

	it( 'leaves plain text untouched', () => {
		expect( PurifyElement( 'just text' ) ).toBe( 'just text' );
	} );
} );
