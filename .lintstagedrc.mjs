/**
 * @type {import('lint-staged').Configuration}
 */
export default {
	'**/*.{js,jsx,ts,tsx}': [ 'wp-scripts lint-js --fix' ],
	// '**/*.{css,scss}': [ 'wp-scripts lint-style --allow-empty-input --fix' ],
	/**
	 * @param filenames
	 * @todo Simplify when we can use PHPCS 4.x's improved exit codes.
	 * @see https://github.com/PHPCSStandards/PHP_CodeSniffer/issues/184
	 */
	'**/*.php': ( filenames ) => {
		const cwd = process.cwd();
		const relativeFilenames = filenames
			.map( ( filename ) => `"${ filename.replace( cwd + '/', '' ) }"` )
			.join( ' ' );

		// Only fail if phpcbf itself failed (exit code 3).
		return [
			`sh -c "./vendor/bin/phpcbf ${ relativeFilenames } || [ \$? -eq 3 ]"`,
		];
	},
	'**/*.{json,md,css,scss,js,jsx,ts,tsx}': [ 'wp-scripts format --' ],
};
