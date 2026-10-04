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

		// `hasText` does a substring match, so filtering by "Activate" would
		// also match a "Deactivate" link — key off the action in the href instead.
		const activateLink = pluginRow.locator( 'a[href*="action=activate"]' );
		const deactivateLink = pluginRow.locator(
			'a[href*="action=deactivate"]'
		);

		// wp-env activates mapped plugins on start, so the plugin may already
		// be active — normalize to a known "inactive" starting state first.
		if ( await deactivateLink.isVisible() ) {
			await Promise.all( [
				page.waitForURL( /plugins.php/ ),
				deactivateLink.click(),
			] );
			await expect( activateLink ).toBeVisible( { timeout: 10000 } );
			await dismissOnboardingModal();
		}

		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			activateLink.click(),
		] );

		await expect( deactivateLink ).toBeVisible( { timeout: 10000 } );

		// Dismiss modal again after activation.
		await dismissOnboardingModal();

		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			deactivateLink.click(),
		] );

		await expect( activateLink ).toBeVisible( { timeout: 10000 } );
	} );
} );
