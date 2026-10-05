/**
 * External dependencies
 */
import '@testing-library/jest-dom';

/**
 * Global Jest setup. Mock the browser globals and the objects OneDesign
 * injects via `wp_localize_script` so component tests run headless.
 */
const fetchMock = jest.fn<
	ReturnType< typeof fetch >,
	Parameters< typeof fetch >
>();

Object.defineProperty( global, 'fetch', {
	value: fetchMock,
	writable: true,
} );

// The settings object OneDesign exposes on `window` (see assets/src/js/constants.ts).
Object.defineProperty( window, 'OneDesignSettings', {
	value: {
		restUrl: 'https://example.com/wp-json',
		restNonce: 'nonce',
		apiKey: '',
		settingsLink: 'https://example.com/wp-admin/',
		multisites: [],
		isMultisite: false,
		isGoverningSiteSelected: false,
		currentSiteId: 1,
	},
	writable: true,
	configurable: true,
} );
