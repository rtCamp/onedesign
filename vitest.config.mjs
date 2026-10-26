/**
 * Vitest configuration for OneDesign.
 *
 * @see https://vitest.dev/config/
 */

/**
 * External dependencies
 */
import { fileURLToPath } from 'node:url';
import { configDefaults, defineConfig } from 'vitest/config';

export default defineConfig( {
	resolve: {
		alias: {
			// Path alias for assets/src directory
			'@': fileURLToPath( new URL( './assets/src', import.meta.url ) ),
		},
	},
	test: {
		environment: 'jsdom',
		setupFiles: [ './tests/js/setup.ts' ],
		exclude: [ ...configDefaults.exclude, 'tests/e2e/**' ],
		// Let Testing Library advance vitest's fake timers while it waits.
		fakeTimers: { shouldAdvanceTime: true },
		// Pre-bundle @wordpress/components, which is slow to load file by file.
		deps: {
			optimizer: {
				client: {
					enabled: true,
					include: [ '@wordpress/components' ],
					exclude: [ 'uuid', 'react/jsx-runtime' ],
				},
			},
		},
		coverage: {
			include: [ 'assets/src/**/*.{js,jsx,ts,tsx}' ],
			exclude: [
				// Type definition files
				'assets/src/**/*.d.ts',
				// Barrel exports
				'assets/src/**/index.{js,tsx,jsx}',
			],
			reportsDirectory: 'tests/_output/js-coverage',
			reporter: [ 'text', 'text-summary', 'lcov', 'html' ],
		},
	},
} );
