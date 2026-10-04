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
	rules: {},
};
