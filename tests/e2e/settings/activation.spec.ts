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

		// Helper to dismiss the onboarding modal if present.
		const dismissOnboardingModal = async () => {
			const modal = page.locator( '#onedesign-site-selection-modal' );
			const backdrop = page.locator(
				'body.onedesign-site-selection-modal'
			);

			if ( await modal.isVisible() ) {
				await modal.evaluate( ( el ) => {
					el.remove();
				} );
			}

			if ( await backdrop.isVisible() ) {
				await backdrop.evaluate( ( el ) => {
					el.classList.remove( 'onedesign-site-selection-modal' );
				} );
			}
		};

		const pluginRow = page.locator(
			'tr[data-plugin="onedesign/onedesign.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		// Dismiss modal before interacting with plugin row.
		await dismissOnboardingModal();

		const activateLink = pluginRow.locator( 'a', { hasText: 'Activate' } );

		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			activateLink.click(),
		] );

		await expect(
			pluginRow.locator( 'a', { hasText: 'Deactivate' } )
		).toBeVisible( { timeout: 10000 } );

		// Dismiss modal again after activation.
		await dismissOnboardingModal();

		const deactivateLink = pluginRow.locator( 'a', {
			hasText: 'Deactivate',
		} );
		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			deactivateLink.click(),
		] );

		await expect(
			pluginRow.locator( 'a', { hasText: 'Activate' } )
		).toBeVisible( { timeout: 10000 } );
	} );
} );
