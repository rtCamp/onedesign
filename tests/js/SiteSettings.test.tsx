/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SiteSettings from '@/components/SiteSettings';

const fetchMock = global.fetch as jest.Mock;

describe( 'SiteSettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the settings cards once loading resolves', async () => {
		fetchMock.mockResolvedValue( {
			ok: true,
			json: async () => ( {
				secret_key: 'secret-abc',
				governing_site_url: 'https://gov.example',
			} ),
		} );

		render( <SiteSettings /> );

		// Spinner shows first; findBy waits for the post-fetch re-render.
		expect( await screen.findByText( 'API Key' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Governing Site Connection' )
		).toBeInTheDocument();
	} );

	it( 'surfaces an error notice when a request fails', async () => {
		fetchMock.mockResolvedValue( { ok: false } );

		render( <SiteSettings /> );

		expect(
			await screen.findByText( /failed to fetch/i )
		).toBeInTheDocument();
	} );
} );
