/**
 * External dependencies
 */
import path from 'path';

/**
 * Internal dependencies
 */
import {
	BRAND_TWO_URL,
	BRAND_URL,
	connectSites,
	expect,
	resetSites,
	test,
} from '../fixtures';

const SETTINGS = [ 'admin.php', 'page=onedesign-settings' ] as const;

test.describe( 'brand site connection', () => {
	test.beforeEach( async ( { requestUtils, brandRequestUtils } ) => {
		await resetSites( requestUtils, brandRequestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test(
		'disconnects the brand site from its governing site, which drops it',
		{ tag: '@TC-29' },
		async ( {
			admin,
			page,
			brandAdmin,
			brandPage,
			requestUtils,
			brandRequestUtils,
		} ) => {
			await connectSites( requestUtils, brandRequestUtils );

			await brandAdmin.visitAdminPage( ...SETTINGS );
			await brandPage
				.getByRole( 'button', { name: 'Disconnect Governing Site' } )
				.click();
			await brandPage
				.getByRole( 'dialog' )
				.getByRole( 'button', { name: 'Disconnect', exact: true } )
				.click();

			await expect(
				brandPage
					.locator( '.components-notice__content' )
					.getByText( 'Governing site disconnected successfully.' )
			).toBeVisible();
			await expect(
				brandPage.getByLabel( 'Governing Site URL' )
			).toHaveValue( '' );

			await admin.visitAdminPage( ...SETTINGS );
			await expect(
				page.getByText( 'No Brand Sites found.' )
			).toBeVisible();
		}
	);

	test(
		'opens the Media Library for the logo, with the current logo picked on edit',
		{ tag: '@TC-38' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			const logo = await requestUtils.uploadMedia(
				path.join( __dirname, '../assets/logo.png' )
			);
			await connectSites( requestUtils, brandRequestUtils, [
				{
					name: 'Brand One',
					url: BRAND_URL,
					logo: logo.source_url,
					logo_id: logo.id,
				},
			] );
			const mediaModal = page.getByRole( 'dialog', {
				name: 'Select Site Logo',
			} );

			await admin.visitAdminPage( ...SETTINGS );
			await page
				.getByRole( 'button', { name: 'Add Brand Site' } )
				.click();
			await page.getByRole( 'button', { name: 'Select Logo' } ).click();
			await expect( mediaModal ).toBeVisible();

			await admin.visitAdminPage( ...SETTINGS );
			await page
				.getByRole( 'row', { name: /Brand One/ } )
				.getByRole( 'button', { name: 'Edit' } )
				.click();
			await page.getByRole( 'button', { name: 'Replace Logo' } ).click();
			// The frame opens on "Upload files" until the library has loaded.
			await mediaModal
				.getByRole( 'tab', { name: 'Media Library' } )
				.click();
			await expect(
				mediaModal.locator( `li.attachment[data-id="${ logo.id }"]` )
			).toHaveAttribute( 'aria-checked', 'true' );
		}
	);

	test(
		'deletes a brand site, which disconnects it',
		{ tag: '@TC-43' },
		async ( {
			admin,
			page,
			brandAdmin,
			brandPage,
			requestUtils,
			brandRequestUtils,
		} ) => {
			await connectSites( requestUtils, brandRequestUtils, [
				{ name: 'Brand One', url: BRAND_URL },
				{ name: 'Brand Two', url: BRAND_TWO_URL },
			] );

			await admin.visitAdminPage( ...SETTINGS );
			await page
				.getByRole( 'row', { name: /Brand One/ } )
				.getByRole( 'button', { name: 'Delete' } )
				.click();
			await page
				.getByRole( 'dialog', { name: 'Delete Brand Site' } )
				.getByRole( 'button', { name: 'Delete' } )
				.click();

			await expect(
				page
					.locator( '.components-snackbar__content' )
					.getByText( 'Brand site removed successfully.' )
			).toBeVisible();
			await expect(
				page.getByRole( 'row', { name: /Brand One/ } )
			).toBeHidden();
			await expect(
				page.getByRole( 'row', { name: /Brand Two/ } )
			).toBeVisible();

			for ( const library of [
				'onedesign-pattern-library',
				'onedesign-template-library',
			] ) {
				await admin.visitAdminPage( 'admin.php', `page=${ library }` );
				await expect(
					page.getByRole( 'tab', { name: 'Brand Two' } )
				).toBeVisible();
				await expect(
					page.getByRole( 'tab', { name: 'Brand One' } )
				).toBeHidden();
			}

			await brandAdmin.visitAdminPage( ...SETTINGS );
			await expect(
				brandPage.getByLabel( 'Governing Site URL' )
			).toHaveValue( '' );
		}
	);
} );
