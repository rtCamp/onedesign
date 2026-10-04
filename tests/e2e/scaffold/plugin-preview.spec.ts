/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Scaffold validation E2E.
 *
 * This is the local stand-in for the Playground PR preview: it proves the
 * artifact the build/release/preview workflows produce actually loads in a
 * real WordPress install — the plugin activates and its admin surface renders.
 * If this passes, the scaffold's build output is functional end-to-end.
 */
test.describe( 'scaffold: built plugin loads', () => {
	test( 'plugin is present and can be activated', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/plugins.php' );

		const pluginRow = page.locator(
			'tr[data-plugin="onedesign/onedesign.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		// Dismiss the onboarding modal if the plugin shows one on load.
		const modal = page.locator( '#onedesign-site-selection-modal' );
		if ( await modal.isVisible() ) {
			await modal.evaluate( ( el ) => el.remove() );
		}

		const activate = pluginRow.locator( 'a', { hasText: 'Activate' } );
		if ( await activate.isVisible() ) {
			await Promise.all( [
				page.waitForURL( /plugins.php/ ),
				activate.click(),
			] );
		}

		await expect(
			pluginRow.locator( 'a', { hasText: 'Deactivate' } )
		).toBeVisible( { timeout: 10000 } );
	} );

	test( 'admin surface renders without a fatal error', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/index.php' );

		// A WordPress fatal renders "There has been a critical error".
		await expect( page.locator( 'body' ) ).not.toContainText(
			'There has been a critical error'
		);
		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	} );
} );
