/** @type {import('stylelint').Config} */
module.exports = {
	extends: '@wordpress/stylelint-config/scss',
	ignoreFiles: [
		'**/*.js',
		'**/*.jsx',
		'**/*.ts',
		'**/*.tsx',
		'**/*.json',
		'**/*.php',
		'**/*.svg',
	],
	rules: {
		// Conflicts with Prettier, which strips the blank line before a first nested rule.
		'rule-empty-line-before': null,
		// WordPress core class names (components-*, dashicons-*, etc.) don't match the kebab-case pattern.
		'selector-class-pattern': null,
	},
};
