/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test( 'should activate and deactivate the plugin', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/plugins.php' );

		const pluginRow = page.locator(
			'tr[data-plugin="onedesign/onedesign.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		const activateLink = pluginRow.locator( 'a[href*="action=activate"]' );
		const deactivateLink = pluginRow.locator(
			'a[href*="action=deactivate"]'
		);

		// wp-env activates mapped plugins on start, so the plugin may already be active — normalize to a known "inactive" starting state first.
		if ( await deactivateLink.isVisible() ) {
			await Promise.all( [
				page.waitForURL( /plugins.php/ ),
				deactivateLink.click(),
			] );
			await expect( activateLink ).toBeVisible( { timeout: 10000 } );
		}

		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			activateLink.click(),
		] );

		await expect( deactivateLink ).toBeVisible( { timeout: 10000 } );

		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			deactivateLink.click(),
		] );

		await expect( activateLink ).toBeVisible( { timeout: 10000 } );
	} );
} );
