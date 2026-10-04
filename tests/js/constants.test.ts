/**
 * The constants module reads its configuration from a `window.*` settings
 * object at import time, picking the first source that is defined in a fixed
 * precedence order. These tests re-require the module under different window
 * states to exercise that resolution and the derived values.
 */

// Node's `require` is provided by the Jest CommonJS environment.
declare const require: ( id: string ) => typeof import('@/js/constants');

const WINDOW_KEYS = [
	'OneDesignSettings',
	'patternSyncData',
	'TemplateLibraryData',
	'OneDesignMultiSiteSettings',
] as const;

function clearWindowSettings(): void {
	// Assign `undefined` rather than `delete` — the module guards on
	// `typeof window.X !== 'undefined'`, and setup.ts installs
	// `OneDesignSettings` as a defined property.
	WINDOW_KEYS.forEach( ( key ) => {
		( window as unknown as Record< string, unknown > )[ key ] = undefined;
	} );
}

function loadConstants(
	windowState: Record< string, unknown > = {}
): typeof import('@/js/constants') {
	clearWindowSettings();
	Object.entries( windowState ).forEach( ( [ key, value ] ) => {
		( window as unknown as Record< string, unknown > )[ key ] = value;
	} );

	let constants!: typeof import('@/js/constants');
	// isolateModules gives the re-require a fresh CommonJS registry so the
	// module's import-time `window.*` resolution runs again for each case.
	jest.isolateModules( () => {
		constants = require( '@/js/constants' );
	} );
	return constants;
}

afterEach( () => {
	clearWindowSettings();
} );

describe( 'constants', () => {
	it( 'derives values from the settings source', () => {
		const constants = loadConstants( {
			OneDesignSettings: {
				restUrl: 'https://example.com/wp-json',
				restNonce: 'abc123',
				apiKey: 'key-1',
				settingsLink: 'https://example.com/settings',
				multisites: [ { id: 2 } ],
				isMultisite: true,
				isGoverningSiteSelected: true,
				currentSiteId: 7,
			},
		} );

		expect( constants.API_NAMESPACE ).toBe(
			'https://example.com/wp-json/onedesign/v1'
		);
		expect( constants.NONCE ).toBe( 'abc123' );
		expect( constants.API_KEY ).toBe( 'key-1' );
		expect( constants.SETTINGS_LINK ).toBe(
			'https://example.com/settings'
		);
		expect( constants.MULTISITES ).toEqual( [ { id: 2 } ] );
		expect( constants.IS_MULTISITE ).toBe( true );
		expect( constants.IS_GOVERNING_SITE_SELECTED ).toBe( true );
		expect( constants.CURRENT_SITE_ID ).toBe( '7' );
	} );

	it( 'follows the source precedence (primary wins, else next defined)', () => {
		const preferred = loadConstants( {
			OneDesignSettings: { restUrl: 'https://winner.example' },
			patternSyncData: { restUrl: 'https://loser.example' },
		} );
		expect( preferred.API_NAMESPACE ).toBe(
			'https://winner.example/onedesign/v1'
		);

		const fallback = loadConstants( {
			patternSyncData: { restUrl: 'https://patterns.example' },
		} );
		expect( fallback.API_NAMESPACE ).toBe(
			'https://patterns.example/onedesign/v1'
		);
	} );

	it( 'uses safe empty defaults when no settings source is defined', () => {
		const constants = loadConstants();

		expect( constants.API_NAMESPACE ).toBe( '' );
		expect( constants.NONCE ).toBe( '' );
		expect( constants.MULTISITES ).toEqual( [] );
		expect( constants.IS_MULTISITE ).toBe( false );
		expect( constants.IS_GOVERNING_SITE_SELECTED ).toBe( false );
		expect( constants.CURRENT_SITE_ID ).toBe( '' );
	} );

	it( 'keeps a "0" current site id rather than falling back to empty', () => {
		const constants = loadConstants( {
			OneDesignSettings: { currentSiteId: 0 },
		} );

		expect( constants.CURRENT_SITE_ID ).toBe( '0' );
	} );
} );
