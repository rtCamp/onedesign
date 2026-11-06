import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import type { EventEmitter } from 'node:events';
import { afterEach, beforeEach, vi } from 'vitest';

vi.mock( '@wordpress/api-fetch' );

// Report jsdom errors (like its unimplemented navigation) through the test's console, like Jest did.
const { jsdom } = globalThis as unknown as {
	jsdom: { virtualConsole: EventEmitter };
};
jsdom.virtualConsole.removeAllListeners( 'jsdomError' );
jsdom.virtualConsole.on( 'jsdomError', ( error: Error ) =>
	// eslint-disable-next-line no-console
	console.error( error )
);

const fetchMock = vi.fn< typeof fetch >();

Object.defineProperty( global, 'fetch', {
	value: fetchMock,
	writable: true,
} );

Object.defineProperty( window, 'OneDesignSettings', {
	value: {
		nonce: 'nonce',
		api_key: 'api-key',
		siteType: 'governing-site',
		setupUrl: '/wp-admin/admin.php?page=onedesign-settings',
	},
	writable: true,
} );

// jsdom has no matchMedia, which some @wordpress/components hooks call.
window.matchMedia = vi.fn( ( query: string ) => ( {
	matches: false,
	media: query,
	onchange: null,
	addListener: vi.fn(),
	removeListener: vi.fn(),
	addEventListener: vi.fn(),
	removeEventListener: vi.fn(),
	dispatchEvent: vi.fn(),
} ) );

Object.defineProperty( navigator, 'clipboard', {
	value: {
		writeText: vi.fn().mockResolvedValue( undefined ),
	},
	configurable: true,
} );

beforeEach( () => {
	vi.clearAllMocks();
	fetchMock.mockReset();
} );

// Vitest doesn't register Testing Library's cleanup without globals.
afterEach( cleanup );
