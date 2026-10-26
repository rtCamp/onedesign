import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import PatternLibrary from '@/admin/patterns/page';
import { findNotice, restError, routes, type Handler } from './library-routes';

// The block editor's own packages don't load in jsdom, so a card renders no preview.
vi.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => null,
} ) );

const localizedSettings = window.OneDesignSettings;

const sharedSites = [
	{
		id: 'brand-1',
		name: 'Brand One',
		url: 'https://one.example.com/',
		logo: '',
		logo_id: 0,
	},
	{
		id: 'brand-2',
		name: 'Brand Two',
		url: 'https://two.example.com/',
		logo: '',
		logo_id: 0,
	},
];

// A brand site sends its patterns without category labels.
const brandHero = {
	name: 'theme/hero',
	title: 'Hero',
	content: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->',
	categories: [ 'banner' ],
};

const brandFooter = {
	name: 'user-pattern-7',
	title: 'Footer',
	content: '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->',
	categories: [ 'footer' ],
};

const hero = { ...brandHero, category_labels: { banner: 'Banners' } };

const footer = { ...brandFooter, category_labels: { footer: 'Footers' } };

const patternRoutes = ( overrides: Record< string, Handler > = {} ) =>
	routes( {
		'GET /onedesign/v1/patterns': () => [ hero, footer ],
		'GET /onedesign/v1/patterns?site=brand-1': () => [ brandHero ],
		'GET /onedesign/v1/patterns?site=brand-2': () => [],
		...overrides,
	} );

const openPicker = async ( title = 'Hero' ) => {
	fireEvent.click( await screen.findByRole( 'checkbox', { name: title } ) );
	fireEvent.click( screen.getByRole( 'button', { name: 'Apply to Sites' } ) );
};

const removeFromBrandOne = async () => {
	fireEvent.click( await screen.findByRole( 'tab', { name: 'Brand One' } ) );
	fireEvent.click( await screen.findByRole( 'checkbox', { name: 'Hero' } ) );
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Remove Patterns' } )
	);
	fireEvent.click(
		within(
			screen.getByRole( 'dialog', { name: 'Remove Patterns' } )
		).getByRole( 'button', { name: 'Remove Patterns' } )
	);
};

describe( 'pattern library', () => {
	beforeEach( () => {
		// WordPress component deprecation warnings aren't ours.
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		vi.mocked( apiFetch ).mockReset();
		window.OneDesignSettings = { ...localizedSettings, sharedSites };
	} );

	afterEach( () => {
		window.OneDesignSettings = localizedSettings;
	} );

	it( 'each tab lists its own patterns and categories', async () => {
		patternRoutes();

		render( <PatternLibrary /> );

		expect(
			await screen.findByRole( 'checkbox', { name: 'Footer' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Footers' } )
		).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand One' } ) );
		expect(
			await screen.findByRole( 'checkbox', { name: 'Hero' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', { name: 'Footer' } )
		).not.toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand Two' } ) );
		expect(
			await screen.findByText( 'No patterns found.' )
		).toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'tab', { name: 'Current Site Patterns' } )
		);
		expect(
			await screen.findByRole( 'checkbox', { name: 'Footer' } )
		).toBeInTheDocument();
	} );

	it( "lists a brand site's patterns while another brand site is still loading", async () => {
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-1': () =>
				new Promise( () => {} ),
		} );

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand Two' } )
		);
		expect(
			await screen.findByText( 'No patterns found.' )
		).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand One' } ) );
		expect( screen.getByText( 'Loading patterns…' ) ).toBeInTheDocument();
	} );

	it( 'shows only the patterns in the picked category', async () => {
		patternRoutes();

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Banners' } )
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'Hero' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', { name: 'Footer' } )
		).not.toBeInTheDocument();
	} );

	it( 'tells a screen reader which category is picked', async () => {
		patternRoutes();

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Banners' } )
		);

		expect(
			screen.getByRole( 'button', { name: 'Banners', pressed: true } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'All', pressed: false } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Footers', pressed: false } )
		).toBeInTheDocument();
	} );

	it( 'shows nine patterns at a time, and finds patterns by title', async () => {
		patternRoutes( {
			'GET /onedesign/v1/patterns': () =>
				Array.from( { length: 10 }, ( _, index ) => ( {
					...footer,
					name: `user-pattern-${ index }`,
					title: `Pattern ${ index }`,
				} ) ),
		} );

		render( <PatternLibrary /> );
		await screen.findByRole( 'checkbox', { name: 'Pattern 0' } );

		expect(
			screen.queryByRole( 'checkbox', { name: 'Pattern 9' } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Show More (9/10)' } )
		);
		expect(
			screen.getByRole( 'checkbox', { name: 'Pattern 9' } )
		).toBeInTheDocument();

		fireEvent.change( screen.getByPlaceholderText( 'Search patterns…' ), {
			target: { value: 'pattern 3' },
		} );
		expect( screen.getAllByRole( 'checkbox' ) ).toEqual( [
			screen.getByRole( 'checkbox', { name: 'Pattern 3' } ),
		] );
	} );

	it( 'leaves the brand site as it was when the admin cancels removing', async () => {
		patternRoutes();

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: 'Hero' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Remove Patterns' } )
		);
		fireEvent.click(
			within(
				screen.getByRole( 'dialog', { name: 'Remove Patterns' } )
			).getByRole( 'button', { name: 'Cancel' } )
		);

		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Hero' } )
		).toBeChecked();
		expect(
			vi
				.mocked( apiFetch )
				.mock.calls.filter(
					( [ options ] ) =>
						( options as { method?: string } ).method === 'DELETE'
				)
		).toEqual( [] );
	} );

	it( "can't apply patterns before every brand site's patterns load", async () => {
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-2': () =>
				new Promise( () => {} ),
		} );

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: 'Hero' } )
		);

		expect(
			screen.getByRole( 'button', { name: 'Apply to Sites' } )
		).toBeDisabled();
	} );

	it( 'disables a brand site that already has the selected namespaced pattern', async () => {
		patternRoutes();

		render( <PatternLibrary /> );
		await openPicker();

		const brandOne = await screen.findByRole( 'checkbox', {
			name: /Brand One/,
		} );
		expect( brandOne ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( brandOne ).toHaveTextContent(
			'Already has all the selected patterns'
		);
		expect(
			screen.getByRole( 'checkbox', { name: /Brand Two/ } )
		).toHaveAttribute( 'aria-disabled', 'false' );
	} );

	it( 'shares the selected patterns with the picked brand sites, and says so', async () => {
		let brandTwo: Array< typeof brandHero > = [];
		const share = vi.fn( () => {
			brandTwo = [ brandHero ];
			return { message: 'The patterns were shared with Brand Two.' };
		} );
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-2': () => brandTwo,
			'POST /onedesign/v1/patterns': share,
		} );

		render( <PatternLibrary /> );
		await openPicker();
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: /Brand Two/ } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		expect(
			await findNotice( 'Shared the patterns with Brand Two.' )
		).toBeInTheDocument();
		expect( share ).toHaveBeenCalledWith( {
			site: 'brand-2',
			names: [ 'theme/hero' ],
		} );
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand Two' } ) );
		expect(
			await screen.findByRole( 'checkbox', { name: 'Hero' } )
		).toBeInTheDocument();
	} );

	it( 'shares with each picked brand site on its own, and names each one that did not get the patterns with its own message', async () => {
		let brandOne: object[] = [ brandHero ];
		const share = vi.fn( ( data ) => {
			if ( ( data as { site: string } ).site === 'brand-2' ) {
				return restError( 'The brand site could not be reached.' )();
			}
			brandOne = [ brandHero, brandFooter ];
			return { message: 'The patterns were shared with Brand One.' };
		} );
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-1': () => brandOne,
			'POST /onedesign/v1/patterns': share,
		} );

		render( <PatternLibrary /> );
		await openPicker( 'Footer' );
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: /Brand One/ } )
		);
		fireEvent.click(
			screen.getByRole( 'checkbox', { name: /Brand Two/ } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		const notice = (
			await screen.findByText(
				"These brand sites didn't get the patterns."
			)
		).closest( '.components-notice' ) as HTMLElement;
		expect( within( notice ).getByText( 'Brand Two' ) ).toBeInTheDocument();
		expect(
			within( notice ).getByText( 'The brand site could not be reached.' )
		).toBeInTheDocument();
		expect(
			within( notice ).queryByText( 'Brand One' )
		).not.toBeInTheDocument();
		expect( share ).toHaveBeenCalledTimes( 2 );

		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand One' } ) );
		expect(
			await screen.findByRole( 'checkbox', { name: 'Footer' } )
		).toBeInTheDocument();
	} );

	it( "can't close the picker while it applies, so the brand sites that didn't get the patterns still show", async () => {
		let failShare: ( reason: unknown ) => void = () => {};
		patternRoutes( {
			'POST /onedesign/v1/patterns': () =>
				new Promise( ( _, reject ) => {
					failShare = reject;
				} ),
		} );

		render( <PatternLibrary /> );
		await openPicker();
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: /Brand Two/ } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		const picker = screen.getByRole( 'dialog', {
			name: 'Select Brand Sites',
		} );
		expect(
			within( picker ).getByRole( 'button', { name: 'Cancel' } )
		).toBeDisabled();
		expect(
			within( picker ).queryByRole( 'button', { name: 'Close' } )
		).not.toBeInTheDocument();

		failShare( {
			code: 'onedesign_error',
			message: 'The brand site could not be reached.',
		} );
		expect(
			await findNotice( 'The brand site could not be reached.' )
		).toBeInTheDocument();
	} );

	it( "clears a brand site's error once it answers again", async () => {
		let brandTwoLoads = 0;
		let brandOneShares = 0;
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-2': () => {
				brandTwoLoads++;
				if ( brandTwoLoads === 2 ) {
					return restError( 'Brand Two could not be reached.' )();
				}
				return brandTwoLoads === 1 ? [] : [ brandFooter ];
			},
			'POST /onedesign/v1/patterns': ( data ) => {
				if (
					( data as { site: string } ).site === 'brand-1' &&
					++brandOneShares === 1
				) {
					return restError( 'Brand One could not be reached.' )();
				}
				return { message: 'Saved.' };
			},
		} );

		render( <PatternLibrary /> );
		await openPicker( 'Footer' );
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: /Brand One/ } )
		);
		fireEvent.click(
			screen.getByRole( 'checkbox', { name: /Brand Two/ } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);
		await screen.findByText( "These brand sites didn't get the patterns." );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		await findNotice( 'Shared the patterns with Brand One, Brand Two.' );
		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand Two' } ) );
		expect(
			await screen.findByRole( 'checkbox', { name: 'Footer' } )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Brand Two could not be reached.' )
		).not.toBeInTheDocument();
	} );

	it( "shows the server's error when this site's patterns don't load", async () => {
		patternRoutes( {
			'GET /onedesign/v1/patterns': restError(
				'Sorry, you are not allowed to do that.',
				403
			),
		} );

		render( <PatternLibrary /> );

		expect(
			await findNotice( 'Sorry, you are not allowed to do that.' )
		).toBeInTheDocument();
	} );

	it( "shows why a brand site's patterns didn't load on its tab and in the picker", async () => {
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-1': restError(
				'Brand One could not be reached.'
			),
		} );

		render( <PatternLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		expect(
			await findNotice( 'Brand One could not be reached.' )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'tab', { name: 'Brand Two' } ) );
		expect(
			await screen.findByText( 'No patterns found.' )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'tab', { name: 'Current Site Patterns' } )
		);
		await openPicker( 'Footer' );
		expect(
			screen.getByRole( 'checkbox', { name: /Brand One/ } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		expect(
			screen.getByRole( 'checkbox', { name: /Brand Two/ } )
		).toHaveAttribute( 'aria-disabled', 'false' );
	} );

	it( "removes patterns from a brand site and shows the server's message", async () => {
		let brandOne = [ brandHero ];
		const remove = vi.fn( () => {
			brandOne = [];
			return { message: 'The patterns were removed from Brand One.' };
		} );
		patternRoutes( {
			'GET /onedesign/v1/patterns?site=brand-1': () => brandOne,
			'DELETE /onedesign/v1/patterns': remove,
		} );

		render( <PatternLibrary /> );
		await removeFromBrandOne();

		expect(
			await findNotice( 'The patterns were removed from Brand One.' )
		).toBeInTheDocument();
		expect( remove ).toHaveBeenCalledWith( {
			site: 'brand-1',
			names: [ 'theme/hero' ],
		} );
		expect( screen.getByText( 'No patterns found.' ) ).toBeInTheDocument();
	} );

	it( "shows the server's error when removing fails", async () => {
		patternRoutes( {
			'DELETE /onedesign/v1/patterns': restError(
				'Sorry, you are not allowed to do that.',
				403
			),
		} );

		render( <PatternLibrary /> );
		await removeFromBrandOne();

		expect(
			await findNotice( 'Sorry, you are not allowed to do that.' )
		).toBeInTheDocument();
	} );
} );
