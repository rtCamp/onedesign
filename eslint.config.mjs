// eslint.config.mjs
import wordpress from '@wordpress/eslint-plugin';
import jest from 'eslint-plugin-jest';

export default [
	{
		ignores: [
			'**/*.min.js',
			'build/**',
			'node_modules/**',
			'tests/_output/**',
			'vendor/**',
			'vendor-prefixed/**',
			// Claude skill scaffold templates for other plugins (not project source)
			'.claude/**',
			// Config files (not subject to project lint rules)
			'eslint.config.mjs',
			'.lintstagedrc.mjs',
			'.prettierrc.js',
		],
	},

	// Spread WordPress recommended config.
	...wordpress.configs.recommended,

	// Project-specific customizations on top of WP recommended
	{
		rules: {
			// Turn off no-unsafe-wp-apis (project opt-out)
			'@wordpress/no-unsafe-wp-apis': 'off',

			// i18n text domain enforcement for this plugin
			'@wordpress/i18n-text-domain': [
				'error',
				{
					allowedTextDomain: 'onedesign',
				},
			],

			// i18n strictness rules
			'@wordpress/i18n-hyphenated-range': 'error',
			'@wordpress/i18n-no-flanking-whitespace': 'error',

			// Additional WordPress rules not in recommended preset
			'@wordpress/dependency-group': 'error',
			'@wordpress/data-no-store-string-literals': 'error',
			'@wordpress/wp-global-usage': 'error',
			'@wordpress/react-no-unsafe-timeout': 'error',
			'@wordpress/use-recommended-components': 'warn',

			// React best practices
			'react/jsx-boolean-value': 'error',
			'react/jsx-curly-brace-presence': [
				'error',
				{
					props: 'never',
					children: 'never',
				},
			],

			// Import rules (plugin is already included by WP recommended)
			'import/default': 'error',
			'import/named': 'error',
			'import/no-extraneous-dependencies': [
				'error',
				{
					devDependencies: [
						'**/*.@(spec|test).@(j|t)s?(x)',
						'**/@(webpack|jest|babel|playwright).config.@(j|t)s',
						'**/scripts/**',
						'**/tests/**',
					],
				},
			],

			// Restricted imports
			'no-restricted-imports': [
				'error',
				{
					paths: [
						{
							name: 'lodash',
							message: 'Please use native functionality instead.',
						},
						{
							name: 'classnames',
							message:
								"Please use `clsx` instead. It's a lighter and faster drop-in replacement for `classnames`.",
						},
						{
							name: 'redux',
							importNames: [ 'combineReducers' ],
							message:
								'Please use `combineReducers` from `@wordpress/data` instead.',
						},
					],
				},
			],

			// Restricted syntax patterns
			'no-restricted-syntax': [
				'error',
				{
					selector:
						'ImportDeclaration[source.value=/^@wordpress\\u002F.+\\u002F/]',
					message:
						'Path access on WordPress dependencies is not allowed.',
				},
				{
					selector:
						'JSXAttribute[name.name="id"][value.type="Literal"]',
					message:
						'Do not use string literals for IDs; use withInstanceId instead.',
				},
				{
					selector:
						'CallExpression[callee.object.name="Math"][callee.property.name="random"]',
					message:
						"Do not use Math.random() to generate unique IDs; use withInstanceId instead. (If you're not generating unique IDs: ignore this message.)",
				},
			],
		},
	},

	// TypeScript-specific overrides
	{
		files: [ '**/*.ts?(x)' ],
		rules: {
			'@typescript-eslint/consistent-type-imports': [
				'error',
				{
					prefer: 'type-imports',
					disallowTypeAnnotations: false,
				},
			],
			'@typescript-eslint/no-shadow': 'error',
			'dot-notation': 'off',
			'no-shadow': 'off',
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/require-returns-type': 'off',
		},
	},

	// Jest unit test files
	{
		files: [
			'**/__tests__/**/*.{ts,tsx}',
			'**/*.{test,spec}.{ts,tsx}',
			'tests/js/**/*.{ts,tsx}',
		],
		...jest.configs[ 'flat/recommended' ],
		rules: {
			...jest.configs[ 'flat/recommended' ].rules,
			'jest/expect-expect': 'error',
			'jest/no-commented-out-tests': 'warn',
			'jest/no-disabled-tests': 'warn',
			'jest/no-focused-tests': 'error',
			'jest/no-identical-title': 'error',
			'jest/prefer-to-have-length': 'warn',
			'jest/valid-expect': 'error',
		},
	},

	// Playwright E2E tests
	{
		files: [ 'tests/e2e/**/*.{ts,tsx}' ],
		rules: {
			'jsdoc/no-undefined-types': 'off',
		},
	},
];
