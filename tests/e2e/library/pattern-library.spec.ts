/**
 * WordPress dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	BRAND_TWO_URL,
	BRAND_URL,
	connectSites,
	createPattern,
	expect,
	resetSites,
	test,
} from '../fixtures';

const LIBRARY = [ 'admin.php', 'page=onedesign-pattern-library' ] as const;

const HERO = {
	title: 'E2E Hero',
	content:
		'<!-- wp:heading --><h2 class="wp-block-heading">E2E Hero heading</h2><!-- /wp:heading -->',
	category: 'E2E Heroes',
};

/**
 * Shares a governing site's user pattern with a brand site, as the library does.
 *
 * @param requestUtils - The governing site's request utils.
 * @param site         - The brand site's id.
 * @param patternId    - The pattern's post id.
 */
const sharePattern = (
	requestUtils: RequestUtils,
	site: string,
	patternId: number
) =>
	requestUtils.rest( {
		method: 'POST',
		path: '/onedesign/v1/patterns',
		data: { site, names: [ `user-pattern-${ patternId }` ] },
	} );

/**
 * The patterns a brand site registered, by title.
 *
 * @param requestUtils - The brand site's request utils.
 */
const registeredPatterns = async ( requestUtils: RequestUtils ) =>
	(
		await requestUtils.rest< Array< { name: string; title: string } > >( {
			path: '/wp/v2/block-patterns/patterns',
		} )
	).map( ( { name, title } ) => ( { name, title } ) );

test.describe( 'pattern library', () => {
	test.beforeEach( async ( { requestUtils, brandRequestUtils } ) => {
		await resetSites( requestUtils, brandRequestUtils );
	} );

	test(
		'open pattern library page and check for the presence of tabs',
		{ tag: '@TC-45' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			await connectSites( requestUtils, brandRequestUtils );

			await admin.visitAdminPage( ...LIBRARY );

			await expect(
				page.getByRole( 'heading', {
					name: 'Pattern Library',
					level: 1,
				} )
			).toBeVisible();
			await expect(
				page.getByRole( 'tab', { name: 'Current Site Patterns' } )
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
		'shares patterns with several brand sites at once',
		{ tag: '@TC-56' },
		async ( { admin, page, requestUtils, brandRequestUtils } ) => {
			await connectSites( requestUtils, brandRequestUtils, [
				{ name: 'Brand One', url: BRAND_URL },
				{ name: 'Brand Two', url: BRAND_TWO_URL },
			] );
			await createPattern( requestUtils, HERO );

			await admin.visitAdminPage( ...LIBRARY );
			// The library shows nine patterns at a time, and the theme's come first.
			await page
				.getByPlaceholder( 'Search patterns…' )
				.fill( HERO.title );
			await page.getByRole( 'checkbox', { name: HERO.title } ).click();
			await page
				.getByRole( 'button', { name: 'Apply to Sites' } )
				.click();
			const picker = page.getByRole( 'dialog', {
				name: 'Select Brand Sites',
			} );
			await picker.getByRole( 'checkbox', { name: /Brand One/ } ).click();
			await picker.getByRole( 'checkbox', { name: /Brand Two/ } ).click();
			await picker
				.getByRole( 'button', { name: 'Apply Patterns' } )
				.click();

			await expect(
				page
					.locator( '.components-notice__content' )
					.getByText(
						'Shared the patterns with Brand One, Brand Two.'
					)
			).toBeVisible();
			for ( const brand of [ 'Brand One', 'Brand Two' ] ) {
				await page.getByRole( 'tab', { name: brand } ).click();
				await expect(
					page.getByRole( 'checkbox', { name: HERO.title } )
				).toBeVisible();
			}
		}
	);

	test(
		"puts shared patterns in the brand site's inserter, under the governing site's categories",
		{ tag: '@TC-60' },
		async ( {
			brandAdmin,
			brandEditor,
			brandPage,
			requestUtils,
			brandRequestUtils,
		} ) => {
			const [ brand ] = await connectSites(
				requestUtils,
				brandRequestUtils
			);
			const pattern = await createPattern( requestUtils, HERO );
			await sharePattern( requestUtils, brand!.id, pattern.id );

			await brandAdmin.createNewPost();
			await brandPage
				.getByRole( 'button', { name: /block inserter/i } )
				.click();
			await brandPage.getByRole( 'tab', { name: 'Patterns' } ).click();
			await brandPage
				.getByRole( 'tabpanel', { name: 'Patterns' } )
				.getByRole( 'tab', { name: HERO.category } )
				.click();

			const option = brandPage.getByRole( 'option', {
				name: HERO.title,
			} );
			await expect( option ).toBeVisible();
			await option.click();
			await expect(
				brandPage
					.locator( '.components-snackbar__content' )
					.getByText( `Pattern "${ HERO.title }" inserted.` )
			).toBeVisible();
			await expect
				.poll( async () =>
					JSON.stringify(
						await brandEditor.getBlocks( { full: true } )
					)
				)
				.toContain( 'E2E Hero heading' );
		}
	);

	test(
		"renders a shared pattern on the brand site's front end",
		{ tag: '@TC-61' },
		async ( { brandPage, requestUtils, brandRequestUtils } ) => {
			const [ brand ] = await connectSites(
				requestUtils,
				brandRequestUtils
			);
			const pattern = await createPattern( requestUtils, HERO );
			await sharePattern( requestUtils, brand!.id, pattern.id );
			const shared = (
				await registeredPatterns( brandRequestUtils )
			).find( ( { title } ) => title === HERO.title );
			expect( shared ).toBeDefined();

			// A pattern block is what the editor inserts the pattern as.
			const post = await brandRequestUtils.createPage( {
				title: 'E2E pattern page',
				content: `<!-- wp:pattern {"slug":"${ shared!.name }"} /-->`,
				status: 'publish',
			} );
			await brandPage.goto( `?page_id=${ post.id }` );

			await expect(
				brandPage.getByRole( 'heading', { name: 'E2E Hero heading' } )
			).toBeVisible();
		}
	);

	test(
		'removes patterns from a brand site, and from its inserter',
		{ tag: '@TC-63' },
		async ( {
			admin,
			page,
			brandAdmin,
			brandPage,
			requestUtils,
			brandRequestUtils,
		} ) => {
			const [ brand ] = await connectSites(
				requestUtils,
				brandRequestUtils
			);
			const pattern = await createPattern( requestUtils, HERO );
			await sharePattern( requestUtils, brand!.id, pattern.id );

			await admin.visitAdminPage( ...LIBRARY );
			await page.getByRole( 'tab', { name: 'Brand One' } ).click();
			await page.getByRole( 'checkbox', { name: HERO.title } ).click();
			await page
				.getByRole( 'button', { name: 'Remove Patterns' } )
				.click();
			const confirm = page.getByRole( 'dialog', {
				name: 'Remove Patterns',
			} );
			await expect(
				confirm.getByText(
					"Editors on the brand site won't be able to insert these patterns any more, so check it doesn't need them first."
				)
			).toBeVisible();
			await confirm
				.getByRole( 'button', { name: 'Remove Patterns' } )
				.click();

			await expect(
				page
					.locator( '.components-notice__content' )
					.getByText( 'The patterns were removed from Brand One.' )
			).toBeVisible();
			await expect(
				page.getByRole( 'checkbox', { name: HERO.title } )
			).toBeHidden();
			expect(
				( await registeredPatterns( brandRequestUtils ) ).map(
					( { title } ) => title
				)
			).not.toContain( HERO.title );

			await brandAdmin.createNewPost();
			await brandPage
				.getByRole( 'button', { name: /block inserter/i } )
				.click();
			await brandPage
				.getByRole( 'searchbox', { name: /search/i } )
				.fill( HERO.title );
			await expect(
				brandPage.getByText( 'No results found.' )
			).toBeVisible();
		}
	);

	test(
		"lists a pattern with a block the brand site doesn't have, which its inserter hides",
		{ tag: '@TC-65' },
		async ( {
			admin,
			page,
			brandAdmin,
			brandPage,
			requestUtils,
			brandRequestUtils,
		} ) => {
			const [ brand ] = await connectSites(
				requestUtils,
				brandRequestUtils
			);
			const pattern = await createPattern( requestUtils, {
				title: 'E2E Missing Block',
				content: '<!-- wp:onedesign-e2e/not-on-brand /-->',
			} );
			await sharePattern( requestUtils, brand!.id, pattern.id );
			// A pattern the inserter does show, so its search results have loaded when the other one is missing.
			const hero = await createPattern( requestUtils, HERO );
			await sharePattern( requestUtils, brand!.id, hero.id );

			await admin.visitAdminPage( ...LIBRARY );
			await page.getByRole( 'tab', { name: 'Brand One' } ).click();
			await expect(
				page.getByRole( 'checkbox', { name: 'E2E Missing Block' } )
			).toBeVisible();

			// The brand site registered it, and the block editor leaves it out of the inserter.
			expect(
				( await registeredPatterns( brandRequestUtils ) ).map(
					( { title } ) => title
				)
			).toContain( 'E2E Missing Block' );
			await brandAdmin.createNewPost();
			await brandPage
				.getByRole( 'button', { name: /block inserter/i } )
				.click();
			await brandPage
				.getByRole( 'searchbox', { name: /search/i } )
				.fill( 'E2E' );
			await expect(
				brandPage.getByRole( 'option', { name: HERO.title } )
			).toBeVisible();
			await expect(
				brandPage.getByRole( 'option', { name: 'E2E Missing Block' } )
			).toBeHidden();
		}
	);
} );
