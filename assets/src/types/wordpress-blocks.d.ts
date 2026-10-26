// The `@wordpress/blocks` version WordPress 6.9 bundles doesn't ship types yet, so declare the part we use.
declare module '@wordpress/blocks' {
	export function parse(
		content: string
	): Array< Record< string, unknown > >;
	export function registerBlockType(
		name: string,
		settings: Record< string, unknown >
	): unknown;
}
