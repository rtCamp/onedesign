/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const sharedConfig = {
	...defaultConfig,
	output: {
		path: path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
		chunkFilename: '[name].js',
	},
	plugins: [ ...defaultConfig.plugins, new RemoveEmptyScriptsPlugin() ],
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			...defaultConfig.optimization.splitChunks,
		},
		minimizer: defaultConfig.optimization.minimizer.concat( [
			new CssMinimizerPlugin(),
		] ),
	},
};

// Extract css/scss out of the bundle into a build/css directory.
const styles = {
	...sharedConfig,
	output: {
		path: path.resolve( process.cwd(), 'build' ),
		filename: '[name].js',
		chunkFilename: '[name].js',
	},
	entry: () => {
		const entries = {};

		const dir = './assets/src/css';
		fs.readdirSync( dir ).forEach( ( fileName ) => {
			const fullPath = `${ dir }/${ fileName }`;
			if (
				! fs.lstatSync( fullPath ).isDirectory() &&
				fileName.match( /\.(scss|css)$/ )
			) {
				entries[ fileName.replace( /\.[^/.]+$/, '' ) ] = fullPath;
			}
		} );

		return entries;
	},
	plugins: [
		...sharedConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
	],
};

const scripts = {
	...sharedConfig,
	entry: {
		main: path.resolve( process.cwd(), 'assets', 'src', 'js', 'main.ts' ),
		editor: path.resolve(
			process.cwd(),
			'assets',
			'src',
			'js',
			'editor.ts'
		),
		admin: path.resolve( process.cwd(), 'assets', 'src', 'js', 'admin.ts' ),
		'templates-library': path.resolve(
			process.cwd(),
			'assets',
			'src',
			'admin',
			'templates',
			'index.ts'
		),
		'patterns-library': path.resolve(
			process.cwd(),
			'assets',
			'src',
			'admin',
			'patterns',
			'index.ts'
		),
		settings: path.resolve(
			process.cwd(),
			'assets',
			'src',
			'admin',
			'settings',
			'index.tsx'
		),
		onboarding: path.resolve(
			process.cwd(),
			'assets',
			'src',
			'admin',
			'onboarding',
			'index.tsx'
		),
		'multisite-plugin': path.resolve(
			process.cwd(),
			'assets',
			'src',
			'admin',
			'multisite-plugin',
			'index.tsx'
		),
	},
	module: {
		rules:
			sharedConfig?.module?.rules?.filter( ( rule ) => {
				return (
					! rule.test ||
					( ! rule.test.toString().includes( 'scss' ) &&
						! rule.test.toString().includes( 'css' ) )
				);
			} ) || [],
	},
	resolve: {
		...sharedConfig.resolve,
		extensions: [ '.tsx', '.ts', '.jsx', '.js' ],
		alias: {
			...( sharedConfig.resolve?.alias || {} ),
			'@': path.resolve( process.cwd(), 'assets', 'src' ),
		},
	},
};

module.exports = [
	scripts,
	styles, // Do not remove this.
];
