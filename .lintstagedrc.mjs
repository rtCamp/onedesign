/**
 * @type {import('lint-staged').Configuration}
 */
export default {
	'**/*.{js,jsx,ts,tsx}': [ 'wp-scripts lint-js --fix' ],
	// '**/*.{css,scss}': [ 'wp-scripts lint-style --allow-empty-input --fix' ],
	'**/*.{json,md,css,scss,js,jsx,ts,tsx}': [ 'wp-scripts format --' ],
};
