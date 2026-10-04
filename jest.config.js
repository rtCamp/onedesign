/**
 * Jest configuration for OneDesign.
 *
 * Extends the @wordpress/scripts Jest preset for this plugin's TS sources and WordPress globals.
 *
 * @see https://jestjs.io/docs/configuration
 */

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,

	displayName: 'onedesign',

	rootDir: '.',
	roots: [ '<rootDir>', '<rootDir>/tests/js' ],

	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/js/setup.ts',
	],

	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@/(.*)$': '<rootDir>/assets/src/$1',
	},

	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
		'/inc',
		'/vendor/',
		'/vendor-prefixed/',
		'/tests/e2e/',
		'/tests/phpunit/',
	],

	testMatch: [
		'**/__tests__/**/*.{js,jsx,ts,tsx}',
		'**/*.{test,spec}.{js,jsx,ts,tsx}',
	],

	collectCoverageFrom: [
		'assets/src/**/*.{js,jsx,ts,tsx}',
		'!assets/src/**/*.d.ts',
		'!assets/src/**/index.{js,tsx,jsx}',
		'!assets/src/**/*.{css,scss}',
	],

	coverageDirectory: 'tests/_output/js-coverage',

	// Start at 0% and ratchet up as coverage lands.
	coverageThreshold: {
		global: {
			branches: 0,
			functions: 0,
			lines: 0,
			statements: 0,
		},
	},

	coverageReporters: [ 'text', 'text-summary', 'lcov', 'html' ],

	verbose: process.env.CI === 'true',

	// Raised from Jest's 5s default for integration-style tests.
	testTimeout: 10000,

	watchPlugins: [
		'jest-watch-typeahead/filename',
		'jest-watch-typeahead/testname',
	],
};
