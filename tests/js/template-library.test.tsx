import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import TemplateLibrary from '@/admin/templates/page';
import { findNotice, restError, routes, type Handler } from './library-routes';

// The block editor's own packages don't load in jsdom, and the cards' previews aren't what these tests check.
vi.mock( '@wordpress/block-editor', () => ( { BlockPreview: () => null } ) );

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

const content = '<!-- wp:paragraph --><p>Page</p><!-- /wp:paragraph -->';

// Templates from GET /templates have an id and no name.
const home = {
	id: 'twentytwentyfive//home',
	slug: 'home',
	title: 'Home',
	description: '',
	content,
};

const about = {
	id: 'twentytwentyfive//about',
	slug: 'about',
	title: 'About',
	description: '',
	content,
};

const brandCopy = ( { slug, ...template }: typeof home ) => ( {
	...template,
	id: `gov-brand//${ slug }`,
	original_id: template.id,
} );

const templateRoutes = ( overrides: Record< string, Handler > = {} ) =>
	routes( {
		'GET /onedesign/v1/templates': () => [ home ],
		'GET /onedesign/v1/templates?site=brand-1': () => [ brandCopy( home ) ],
		'GET /onedesign/v1/templates?site=brand-2': () => [],
		...overrides,
	} );

const applyToBrandTwo = async () => {
	fireEvent.click( await screen.findByRole( 'checkbox', { name: 'Home' } ) );
	fireEvent.click( screen.getByRole( 'button', { name: 'Apply to Sites' } ) );
	fireEvent.click(
		await screen.findByRole( 'checkbox', { name: /Brand Two/ } )
	);
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Apply Templates' } )
	);
};

const removeFromBrandOne = async ( title = 'Home' ) => {
	fireEvent.click( await screen.findByRole( 'tab', { name: 'Brand One' } ) );
	fireEvent.click( await screen.findByRole( 'checkbox', { name: title } ) );
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Remove Templates' } )
	);
	fireEvent.click(
		within(
			screen.getByRole( 'dialog', { name: 'Remove Templates' } )
		).getByRole( 'button', { name: 'Remove Templates' } )
	);
};

describe( 'template library', () => {
	beforeEach( () => {
		// WordPress component deprecation warnings aren't ours.
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		vi.mocked( apiFetch ).mockReset();
		window.OneDesignSettings = { ...localizedSettings, sharedSites };
	} );

	afterEach( () => {
		window.OneDesignSettings = localizedSettings;
	} );

	it( 'tells the admin to add a brand site when there are none', async () => {
		window.OneDesignSettings = { ...localizedSettings, sharedSites: [] };
		templateRoutes();

		render( <TemplateLibrary /> );

		expect(
			await screen.findByText(
				'There are no brand sites to share templates with. Add one on the settings page, then come back here to apply templates to it.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Add a brand site' } )
		).toHaveAttribute( 'href', localizedSettings.setupUrl );
		expect(
			screen.queryByRole( 'tab', { name: 'Current Site Templates' } )
		).not.toBeInTheDocument();
	} );

	it( 'finds templates by title, and says when none match', async () => {
		templateRoutes( {
			'GET /onedesign/v1/templates': () => [ home, about ],
		} );

		render( <TemplateLibrary /> );
		await screen.findByRole( 'checkbox', { name: 'Home' } );

		const search = screen.getByPlaceholderText( 'Search templates…' );
		fireEvent.change( search, { target: { value: 'abo' } } );
		expect( screen.getAllByRole( 'checkbox' ) ).toEqual( [
			screen.getByRole( 'checkbox', { name: 'About' } ),
		] );

		fireEvent.change( search, { target: { value: 'nothing like it' } } );
		expect( screen.queryAllByRole( 'checkbox' ) ).toEqual( [] );
		expect( screen.getByText( 'No templates found.' ) ).toBeInTheDocument();
	} );

	it( 'counts the selected templates, and applies only once one is picked', async () => {
		templateRoutes( {
			'GET /onedesign/v1/templates': () => [ home, about ],
		} );

		render( <TemplateLibrary /> );
		const homeCard = await screen.findByRole( 'checkbox', {
			name: 'Home',
		} );
		const apply = screen.getByRole( 'button', { name: 'Apply to Sites' } );
		expect( apply ).toBeDisabled();

		fireEvent.click( homeCard );
		expect( screen.getByText( '1 template selected' ) ).toBeInTheDocument();
		await waitFor( () => expect( apply ).toBeEnabled() );

		fireEvent.click( screen.getByRole( 'checkbox', { name: 'About' } ) );
		expect(
			screen.getByText( '2 templates selected' )
		).toBeInTheDocument();

		fireEvent.click( homeCard );
		fireEvent.click( screen.getByRole( 'checkbox', { name: 'About' } ) );
		expect( screen.queryByText( /selected$/ ) ).not.toBeInTheDocument();
		expect( apply ).toBeDisabled();
	} );

	it( 'leaves the brand site as it was when the admin cancels removing', async () => {
		templateRoutes();

		render( <TemplateLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: 'Home' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Remove Templates' } )
		);
		fireEvent.click(
			within(
				screen.getByRole( 'dialog', { name: 'Remove Templates' } )
			).getByRole( 'button', { name: 'Cancel' } )
		);

		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Home' } )
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

	it( "disables a brand site that couldn't answer for applying, with its own message", async () => {
		templateRoutes( {
			'GET /onedesign/v1/templates?site=brand-1': () => [],
			'GET /onedesign/v1/templates?site=brand-2': restError(
				'Bad token',
				403
			),
		} );

		render( <TemplateLibrary /> );

		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: 'Home' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply to Sites' } )
		);
		const brandTwo = await screen.findByRole( 'checkbox', {
			name: /Brand Two/,
		} );
		expect( brandTwo ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( brandTwo ).toHaveTextContent( 'Bad token' );
		expect(
			screen.getByRole( 'checkbox', { name: /Brand One/ } )
		).toHaveAttribute( 'aria-disabled', 'false' );
	} );

	it( 'disables a brand site that already has the selected template', async () => {
		templateRoutes();

		render( <TemplateLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'checkbox', { name: 'Home' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply to Sites' } )
		);

		expect(
			await screen.findByRole( 'checkbox', { name: /Brand One/ } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		expect(
			screen.getByRole( 'checkbox', { name: /Brand Two/ } )
		).toHaveAttribute( 'aria-disabled', 'false' );
	} );

	it( 'applies the selected templates to the picked brand sites, and says so', async () => {
		const apply = vi.fn( () => ( {
			message: 'The templates were applied to Brand Two.',
		} ) );
		templateRoutes( { 'POST /onedesign/v1/templates': apply } );

		render( <TemplateLibrary /> );
		await applyToBrandTwo();

		expect(
			await findNotice( 'Applied the templates to Brand Two.' )
		).toBeInTheDocument();
		expect( apply ).toHaveBeenCalledWith( {
			site: 'brand-2',
			ids: [ home.id ],
		} );
	} );

	it( 'names each brand site that did not get the templates, with its own message', async () => {
		templateRoutes( {
			'POST /onedesign/v1/templates': restError(
				'The brand site could not be reached.'
			),
		} );

		render( <TemplateLibrary /> );
		await applyToBrandTwo();

		const notice = (
			await screen.findByText(
				"These brand sites didn't get the templates."
			)
		).closest( '.components-notice' ) as HTMLElement;
		expect( within( notice ).getByText( 'Brand Two' ) ).toBeInTheDocument();
		expect(
			within( notice ).getByText( 'The brand site could not be reached.' )
		).toBeInTheDocument();
	} );

	it( "shows the server's error when this site's templates don't load", async () => {
		templateRoutes( {
			'GET /onedesign/v1/templates': restError(
				'Sorry, you are not allowed to do that.',
				403
			),
		} );

		render( <TemplateLibrary /> );

		expect(
			await findNotice( 'Sorry, you are not allowed to do that.' )
		).toBeInTheDocument();
	} );

	it( "shows why a brand site's templates didn't load on its tab", async () => {
		templateRoutes( {
			'GET /onedesign/v1/templates?site=brand-1': restError(
				'The brand site could not be reached.'
			),
		} );

		render( <TemplateLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);

		expect(
			await findNotice( 'The brand site could not be reached.' )
		).toBeInTheDocument();
	} );

	it( "re-syncs a brand site by its id, and shows the server's message", async () => {
		const resync = vi.fn( () => ( {
			message: 'Brand One now has the latest version of these templates.',
		} ) );
		templateRoutes( { 'PUT /onedesign/v1/templates': resync } );

		render( <TemplateLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		fireEvent.click(
			await screen.findByRole( 'button', {
				name: 'Sync Shared Templates',
			} )
		);

		expect(
			await findNotice(
				'Brand One now has the latest version of these templates.'
			)
		).toBeInTheDocument();
		expect( resync ).toHaveBeenCalledWith( { site: 'brand-1' } );
	} );

	it( "shows the brand site's error when re-syncing fails", async () => {
		templateRoutes( {
			'PUT /onedesign/v1/templates': restError( 'Bad token', 403 ),
		} );

		render( <TemplateLibrary /> );
		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		fireEvent.click(
			await screen.findByRole( 'button', {
				name: 'Sync Shared Templates',
			} )
		);

		expect( await findNotice( 'Bad token' ) ).toBeInTheDocument();
	} );

	it( "removes templates from a brand site and shows the server's message", async () => {
		const remove = vi.fn( () => ( {
			message: 'The templates were removed from Brand One.',
		} ) );
		templateRoutes( { 'DELETE /onedesign/v1/templates': remove } );

		render( <TemplateLibrary /> );
		await removeFromBrandOne();

		expect(
			await findNotice( 'The templates were removed from Brand One.' )
		).toBeInTheDocument();
		// The brand site removes its copy by the id of this site's template.
		expect( remove ).toHaveBeenCalledWith( {
			site: 'brand-1',
			ids: [ home.id ],
		} );
	} );

	it( "shows the server's error when removing fails", async () => {
		templateRoutes( {
			'DELETE /onedesign/v1/templates': restError(
				'Sorry, you are not allowed to do that.',
				403
			),
		} );

		render( <TemplateLibrary /> );
		await removeFromBrandOne();

		expect(
			await findNotice( 'Sorry, you are not allowed to do that.' )
		).toBeInTheDocument();
	} );

	it( 'keeps each template on its own card when another is removed', async () => {
		let brandOne = [ brandCopy( home ), brandCopy( about ) ];
		templateRoutes( {
			'GET /onedesign/v1/templates': () => [ home, about ],
			'GET /onedesign/v1/templates?site=brand-1': () => brandOne,
			'DELETE /onedesign/v1/templates': () => {
				brandOne = [ brandCopy( about ) ];
				return {
					message: 'The templates were removed from Brand One.',
				};
			},
		} );

		render( <TemplateLibrary /> );
		expect(
			await screen.findByRole( 'checkbox', { name: 'Home' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'About' } )
		).toBeInTheDocument();

		fireEvent.click(
			await screen.findByRole( 'tab', { name: 'Brand One' } )
		);
		const aboutCard = await screen.findByRole( 'checkbox', {
			name: 'About',
		} );
		await removeFromBrandOne( 'Home' );

		expect(
			await findNotice( 'The templates were removed from Brand One.' )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'checkbox', { name: 'Home' } )
		).not.toBeInTheDocument();
		// The card that showed About still does, rather than the removed template's card being given About.
		expect( screen.getByRole( 'checkbox', { name: 'About' } ) ).toBe(
			aboutCard
		);
	} );
} );
