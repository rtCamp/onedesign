/**
 * @type {import('lint-staged').Configuration}
 */
export default {
	'**/*.{js,jsx,ts,tsx}': [ 'wp-scripts lint-js --fix' ],
	// '**/*.{css,scss}': [ 'wp-scripts lint-style --allow-empty-input --fix' ],
	/**
	 * @todo Simplify when we can use PHPCS 4.x's improved exit codes.
	 * @see https://github.com/PHPCSStandards/PHP_CodeSniffer/issues/184
	 */
	'**/*.php': ( filenames ) => {
		const cwd = process.cwd();
		const relativeFilenames = filenames
			.map( ( filename ) => `"${ filename.replace( cwd + '/', '' ) }"` )
			.join( ' ' );

		// phpcbf 3.x exits 1 when it fixed everything;
		return [
			`sh -c "./vendor/bin/phpcbf ${ relativeFilenames } || [ \$? -eq 1 ]"`,
		];
	},
	'**/*.{json,md,css,scss,js,jsx,ts,tsx}': [ 'wp-scripts format --' ],
};
