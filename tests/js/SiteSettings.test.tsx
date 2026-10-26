import { describe, expect, it, type MockedFunction } from 'vitest';
import { render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteSettings from '@/components/SiteSettings';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const mockSiteSettingsApi = ( handlers: { governingSiteUrl?: string } ) =>
	mockedApiFetch.mockImplementation( async ( { path, method = 'GET' } ) => {
		if ( path === '/wp/v2/settings' && method === 'GET' ) {
			return {
				onedesign_parent_site_url: handlers.governingSiteUrl ?? '',
			};
		}

		throw new Error( `Unexpected request: ${ method } ${ path }` );
	} );

describe( 'SiteSettings', () => {
	it( 'renders the settings cards once loading resolves', async () => {
		mockSiteSettingsApi( { governingSiteUrl: 'https://gov.example' } );

		render( <SiteSettings /> );

		// Spinner shows first; findBy waits for the post-fetch re-render.
		expect( await screen.findByText( 'API Key' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Governing Site Connection' )
		).toBeInTheDocument();
	} );

	it( "shows the server's message when a request fails", async () => {
		mockedApiFetch.mockRejectedValue( {
			code: 'rest_forbidden',
			message: 'Sorry, you are not allowed to do that.',
			data: { status: 403 },
		} );

		render( <SiteSettings /> );

		expect(
			await screen.findByText( 'Sorry, you are not allowed to do that.' )
		).toBeInTheDocument();
	} );

	it( 'loads the api key and governing site details', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );

		render( <SiteSettings /> );

		expect(
			await screen.findByDisplayValue( 'api-key' )
		).toBeInTheDocument();
		expect(
			screen.getByDisplayValue( 'https://governing.example.com/' )
		).toBeInTheDocument();
	} );
} );
