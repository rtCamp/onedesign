/**
 * Jest configuration for OneDesign.
 *
 * Extends @wordpress/scripts default configuration with:
 * - Custom test setup for WordPress mocks
 * - Module path aliases for cleaner imports
 * - Coverage thresholds to maintain code quality
 *
 * @see https://jestjs.io/docs/configuration
 */

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,

	// Display name for clarity in multi-project setups
	displayName: 'onedesign',

	// Root directory for tests
	rootDir: '.',
	roots: [ '<rootDir>', '<rootDir>/tests/js' ],

	// Test setup files run after Jest environment is set up
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/js/setup.ts',
	],

	// Module resolution aliases
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		// Path alias for assets/src directory
		'^@/(.*)$': '<rootDir>/assets/src/$1',
	},

	// Directories to ignore when searching for tests
	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
		'/inc',
		'/vendor/',
		'/vendor-prefixed/',
		'/tests/e2e/',
		'/tests/phpunit/',
	],

	// Test match patterns
	testMatch: [
		'**/__tests__/**/*.{js,jsx,ts,tsx}',
		'**/*.{test,spec}.{js,jsx,ts,tsx}',
	],

	// Files to include in coverage reports
	collectCoverageFrom: [
		'assets/src/**/*.{js,jsx,ts,tsx}',
		// Exclude type definition files
		'!assets/src/**/*.d.ts',
		// Exclude barrel exports
		'!assets/src/**/index.{js,tsx,jsx}',
		// Exclude style files
		'!assets/src/**/*.{css,scss}',
	],

	// Coverage output directory
	coverageDirectory: 'tests/_output/js-coverage',

	// Coverage thresholds - start at 0% to allow gradual adoption
	coverageThreshold: {
		global: {
			branches: 0,
			functions: 0,
			lines: 0,
			statements: 0,
		},
	},

	// Coverage reporters for different outputs
	coverageReporters: [ 'text', 'text-summary', 'lcov', 'html' ],

	// Verbose output for CI environments
	verbose: process.env.CI === 'true',

	// Timeout for slow tests (useful for integration tests)
	testTimeout: 10000,

	// Watch plugins for better DX
	watchPlugins: [
		'jest-watch-typeahead/filename',
		'jest-watch-typeahead/testname',
	],
};
