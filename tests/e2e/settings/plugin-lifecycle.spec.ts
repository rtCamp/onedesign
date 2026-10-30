/**
 * Internal dependencies
 */
import {
	connectSites,
	expect,
	governingCli,
	installPlugin,
	PLUGIN_ZIP,
	resetSites,
	test,
	watchFatalErrors,
} from '../fixtures';

test.describe( 'plugin lifecycle', () => {
	test.beforeEach( async ( { requestUtils, brandRequestUtils } ) => {
		await resetSites( requestUtils, brandRequestUtils );
	} );

	// The install and delete tests take the plugin off the governing site.
	test.afterEach( () => {
		installPlugin();
	} );

	test(
		'installs from an uploaded zip',
		{ tag: '@TC-1' },
		async ( { admin, page, requestUtils } ) => {
			const fatalErrors = watchFatalErrors();
			await requestUtils.deactivatePlugin( 'onedesign' );
			await requestUtils.rest( {
				method: 'DELETE',
				path: '/wp/v2/plugins/onedesign/onedesign',
			} );

			await admin.visitAdminPage( 'plugin-install.php', 'tab=upload' );
			await page
				.getByLabel( 'Plugin zip file' )
				.setInputFiles( PLUGIN_ZIP );
			await page.getByRole( 'button', { name: 'Install Now' } ).click();

			await expect(
				page.getByText( 'Plugin installed successfully.' )
			).toBeVisible( { timeout: 60_000 } );
			await page.getByRole( 'link', { name: 'Activate Plugin' } ).click();
			await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();
			await expect(
				page
					.getByRole( 'navigation', { name: 'Main menu' } )
					.getByRole( 'link', { name: 'OneDesign', exact: true } )
			).toBeVisible();
			expect( fatalErrors() ).toEqual( [] );
		}
	);

	test(
		'activates and adds the OneDesign menu',
		{ tag: '@TC-2' },
		async ( { admin, page, requestUtils } ) => {
			const fatalErrors = watchFatalErrors();
			await requestUtils.deactivatePlugin( 'onedesign' );
			await admin.visitAdminPage( 'plugins.php' );

			await page
				.locator( 'tr[data-plugin="onedesign/onedesign.php"]' )
				.getByRole( 'link', { name: /^Activate/ } )
				.click();

			await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();
			await expect(
				page
					.getByRole( 'navigation', { name: 'Main menu' } )
					.getByRole( 'link', { name: 'OneDesign', exact: true } )
			).toBeVisible();
			expect( fatalErrors() ).toEqual( [] );
		}
	);

	test(
		'keeps the site type and brand sites when deactivated and activated again',
		{ tag: '@TC-5' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			const fatalErrors = watchFatalErrors();
			await connectSites( requestUtils, brandRequestUtils );
			const row = page.locator(
				'tr[data-plugin="onedesign/onedesign.php"]'
			);

			await admin.visitAdminPage( 'plugins.php' );
			await row.getByRole( 'link', { name: /^Deactivate/ } ).click();
			await expect(
				page.getByText( 'Plugin deactivated.' )
			).toBeVisible();
			await row.getByRole( 'link', { name: /^Activate/ } ).click();
			await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();

			await admin.visitAdminPage(
				'admin.php',
				'page=onedesign-settings'
			);
			await expect(
				page.getByRole( 'cell', { name: 'Brand One' } )
			).toBeVisible();
			await expect( page.getByLabel( 'Site Type' ) ).toBeHidden();
			expect( fatalErrors() ).toEqual( [] );
		}
	);

	test(
		'deletes the plugin, and its settings with it',
		{ tag: '@TC-6' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			const fatalErrors = watchFatalErrors();
			await connectSites( requestUtils, brandRequestUtils );
			const row = page.locator(
				'tr[data-plugin="onedesign/onedesign.php"]'
			);

			await admin.visitAdminPage( 'plugins.php' );
			await row.getByRole( 'link', { name: /^Deactivate/ } ).click();
			await expect(
				page.getByText( 'Plugin deactivated.' )
			).toBeVisible();
			// WordPress asks to confirm with a browser dialog.
			page.once( 'dialog', ( dialog ) => dialog.accept() );
			await row.getByRole( 'link', { name: /^Delete/ } ).click();

			await expect(
				page.getByText( 'OneDesign was successfully deleted.' )
			).toBeVisible();
			// uninstall.php took the site type and brand sites with it.
			expect(
				governingCli(
					'option',
					'list',
					'--search=onedesign_*',
					'--field=option_name'
				).trim()
			).toBe( '' );
			expect( fatalErrors() ).toEqual( [] );
		}
	);
} );
