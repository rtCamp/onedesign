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
			'eslint.config.mjs',
			'.lintstagedrc.mjs',
			'.prettierrc.js',
		],
	},

	...wordpress.configs.recommended,

	{
		rules: {
			'@wordpress/no-unsafe-wp-apis': 'off',

			'@wordpress/i18n-text-domain': [
				'error',
				{
					allowedTextDomain: 'onedesign',
				},
			],

			'@wordpress/i18n-hyphenated-range': 'error',
			'@wordpress/i18n-no-flanking-whitespace': 'error',

			'@wordpress/dependency-group': 'error',
			'@wordpress/data-no-store-string-literals': 'error',
			'@wordpress/wp-global-usage': 'error',
			'@wordpress/react-no-unsafe-timeout': 'error',
			'@wordpress/use-recommended-components': 'warn',

			'react/jsx-boolean-value': 'error',
			'react/jsx-curly-brace-presence': [
				'error',
				{
					props: 'never',
					children: 'never',
				},
			],

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

	{
		files: [ 'tests/e2e/**/*.{ts,tsx}' ],
		rules: {
			'jsdoc/no-undefined-types': 'off',
		},
	},
];
