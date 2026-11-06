import { describe, expect, it } from 'vitest';
import { isValidUrl, withTrailingSlash, getInitials } from '@/js/utils';

describe( 'utils', () => {
	it( 'validates well-formed urls', () => {
		expect( isValidUrl( 'https://example.com/path' ) ).toBe( true );
		expect( isValidUrl( 'not-a-url' ) ).toBe( false );
	} );

	it( 'only accepts http and https urls', () => {
		expect( isValidUrl( 'http://localhost:8890' ) ).toBe( true );
		expect( isValidUrl( 'ftp://example.com' ) ).toBe( false );
		expect( isValidUrl( 'javascript:alert(1)' ) ).toBe( false );
		expect( isValidUrl( 'mailto:a@b.c' ) ).toBe( false );
	} );

	it( 'adds a trailing slash only when needed', () => {
		expect( withTrailingSlash( '' ) ).toBe( '' );
		expect( withTrailingSlash( 'https://example.com' ) ).toBe(
			'https://example.com/'
		);
		expect( withTrailingSlash( 'https://example.com/' ) ).toBe(
			'https://example.com/'
		);
	} );
} );

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
