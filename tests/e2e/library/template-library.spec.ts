/**
 * Internal dependencies
 */
import { connectSites, expect, resetSites, test } from '../fixtures';

const LIBRARY = [ 'admin.php', 'page=onedesign-template-library' ] as const;

test.describe( 'template library', () => {
	test.beforeEach( async ( { requestUtils, brandRequestUtils } ) => {
		await resetSites( requestUtils, brandRequestUtils );
	} );

	test(
		'open template library page and check for the presence of tabs',
		{ tag: '@TC-66' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			await connectSites( requestUtils, brandRequestUtils );

			await admin.visitAdminPage( ...LIBRARY );

			await expect(
				page.getByRole( 'heading', {
					name: 'Template Library',
					level: 1,
				} )
			).toBeVisible();
			await expect(
				page.getByRole( 'tab', { name: 'Current Site Templates' } )
			).toBeVisible();
			await expect(
				page.getByRole( 'tab', { name: 'Brand One' } )
			).toBeVisible();
			await expect( page.locator( 'body' ) ).not.toHaveClass(
				/is-fullscreen-mode/
			);
		}
	);

	test(
		"adds an applied template to the brand site's Site Editor, and renders pages with it",
		{ tag: '@TC-73' },
		async ( { brandPage, requestUtils, brandRequestUtils } ) => {
			const [ brand ] = await connectSites(
				requestUtils,
				brandRequestUtils
			);
			const template = await requestUtils.createTemplate( 'wp_template', {
				slug: 'e2e-landing',
				title: 'E2E Landing',
				content:
					'<!-- wp:paragraph --><p>E2E Landing body</p><!-- /wp:paragraph -->',
			} );
			await requestUtils.rest( {
				method: 'POST',
				path: '/onedesign/v1/templates',
				data: { site: brand!.id, ids: [ template.id ] },
			} );

			await brandPage.goto( '/wp-admin/site-editor.php?p=%2Ftemplate' );
			await expect(
				brandPage.getByText( 'E2E Landing', { exact: true } ).first()
			).toBeVisible();

			// The brand site's copy has a slug of its own, naming both sites.
			const copy = (
				await brandRequestUtils.rest<
					Array< { slug: string; title: { rendered: string } } >
				>( { path: '/wp/v2/templates' } )
			).find( ( { title } ) => title.rendered === 'E2E Landing' );
			expect( copy ).toBeDefined();
			const page = await brandRequestUtils.rest< { id: number } >( {
				method: 'POST',
				path: '/wp/v2/pages',
				data: {
					title: 'E2E landing page',
					status: 'publish',
					template: copy!.slug,
				},
			} );
			await brandPage.goto( `/?page_id=${ page.id }` );
			await expect(
				brandPage.getByText( 'E2E Landing body' )
			).toBeVisible();
		}
	);
} );
