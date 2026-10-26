/** @type {import('stylelint').Config} */
export default {
	extends: '@wordpress/stylelint-config/scss',
	ignoreFiles: [ 'build/**', 'tests/**', 'vendor/**' ],
	rules: {
		// Prettier removes the blank line before a block's first nested rule.
		'rule-empty-line-before': [
			'always-multi-line',
			{ except: [ 'first-nested' ], ignore: [ 'after-comment' ] },
		],
	},
};
