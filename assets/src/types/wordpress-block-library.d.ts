// The `@wordpress/block-library` version WordPress 6.9 bundles doesn't ship types yet, so declare the part we use.
declare module '@wordpress/block-library' {
	export function registerCoreBlocks(): void;
}
