import { describe, expect, it, type MockedFunction } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteSettings from '@/components/SiteSettings';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const mockSiteSettingsApi = ( handlers: {
	governingSiteUrl?: string;
	regeneratedKey?: string;
	disconnectResponse?: Record< string, unknown >;
} ) =>
	mockedApiFetch.mockImplementation( async ( { path, method = 'GET' } ) => {
		if ( path === '/onedesign/v1/secret-key' && method === 'POST' ) {
			return { secret_key: handlers.regeneratedKey ?? '' };
		}

		if ( path === '/wp/v2/settings' && method === 'GET' ) {
			return {
				onedesign_parent_site_url: handlers.governingSiteUrl ?? '',
			};
		}

		if ( path === '/onedesign/v1/governing-site' && method === 'DELETE' ) {
			return (
				handlers.disconnectResponse ?? {
					governing_site_notified: true,
				}
			);
		}

		throw new Error( `Unexpected request: ${ method } ${ path }` );
	} );

describe( 'SiteSettings', () => {
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

	it( 'regenerates the api key and reports success', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			regeneratedKey: 'new-secret',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Regenerate API Key' } )
		);

		expect( await screen.findByDisplayValue( 'new-secret' ) ).toBeVisible();
		expect(
			screen.getByText(
				'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );

	it( 'warns when the governing site could not be notified of the disconnection', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			disconnectResponse: {
				governing_site_notified: false,
				message: 'The governing site could not be notified.',
			},
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'https://governing.example.com/' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Disconnect Governing Site' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );

		expect(
			await screen.findByText(
				'The governing site could not be notified.',
				{
					selector: '.components-notice__content',
				}
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Governing site disconnected successfully.' )
		).not.toBeInTheDocument();
		expect( screen.getByDisplayValue( '' ) ).toBeInTheDocument();
	} );

	it( 'shows an error notice when the clipboard api is unavailable', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );
		const clipboard = navigator.clipboard;
		Object.defineProperty( navigator, 'clipboard', {
			value: undefined,
			configurable: true,
		} );

		try {
			render( <SiteSettings /> );

			await screen.findByDisplayValue( 'api-key' );
			fireEvent.click(
				screen.getByRole( 'button', { name: 'Copy API Key' } )
			);

			expect(
				await screen.findByText(
					'Failed to copy api key. Please try again.',
					{ selector: '.components-notice__content' }
				)
			).toBeInTheDocument();
		} finally {
			Object.defineProperty( navigator, 'clipboard', {
				value: clipboard,
				configurable: true,
			} );
		}
	} );

	it( 'loads once on mount without the API key, and does not reload after regenerating', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			regeneratedKey: 'new-secret',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		expect( mockedApiFetch ).toHaveBeenCalledTimes( 1 );
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/settings',
		} );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Regenerate API Key' } )
		);

		expect( await screen.findByDisplayValue( 'new-secret' ) ).toBeVisible();
		// Nothing but the regeneration request was made after the initial load.
		expect( mockedApiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'keeps the key field selectable for a manual copy', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );

		render( <SiteSettings /> );

		const field = await screen.findByDisplayValue( 'api-key' );
		expect( field ).toBe( screen.getByLabelText( 'API Key' ) );
		expect( field ).not.toBeDisabled();
		expect( field ).toHaveAttribute( 'readonly' );
	} );
} );
