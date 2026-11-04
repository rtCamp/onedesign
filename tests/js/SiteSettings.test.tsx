import { describe, expect, it, type MockedFunction, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteSettings from '@/components/SiteSettings';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

// Each fail option is what apiFetch rejects with.
const mockSiteSettingsApi = ( handlers: {
	governingSiteUrl?: string;
	regeneratedKey?: string;
	disconnectResponse?: Record< string, unknown >;
	failGoverningSite?: unknown;
	failRegenerate?: unknown;
	failDisconnect?: unknown;
} ) =>
	mockedApiFetch.mockImplementation( async ( { path, method = 'GET' } ) => {
		if ( path === '/onedesign/v1/secret-key' && method === 'POST' ) {
			if ( handlers.failRegenerate ) {
				throw handlers.failRegenerate;
			}

			return { secret_key: handlers.regeneratedKey ?? '' };
		}

		if ( path === '/wp/v2/settings' && method === 'GET' ) {
			if ( handlers.failGoverningSite ) {
				throw handlers.failGoverningSite;
			}

			return {
				onedesign_parent_site_url: handlers.governingSiteUrl ?? '',
			};
		}

		if ( path === '/onedesign/v1/governing-site' && method === 'DELETE' ) {
			if ( handlers.failDisconnect ) {
				throw handlers.failDisconnect;
			}

			return (
				handlers.disconnectResponse ?? {
					governing_site_notified: true,
				}
			);
		}

		throw new Error( `Unexpected request: ${ method } ${ path }` );
	} );

describe( 'SiteSettings', () => {
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

	it( 'copies the api key to the clipboard and shows success feedback', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Copy API Key' } )
		);

		await waitFor( () => {
			expect( navigator.clipboard.writeText ).toHaveBeenCalledWith(
				'api-key'
			);
		} );

		expect(
			await screen.findByText( 'API key copied to clipboard.', {
				selector: '.components-notice__content',
			} )
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

	it( 'disconnects from the governing site after confirmation', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'https://governing.example.com/' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Disconnect Governing Site' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );

		expect(
			await screen.findByText(
				'Governing site disconnected successfully.',
				{
					selector: '.components-notice__content',
				}
			)
		).toBeInTheDocument();
		expect( screen.getByDisplayValue( '' ) ).toBeInTheDocument();
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

	it( 'shows an error notice when copying the api key fails', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );
		navigator.clipboard.writeText = vi
			.fn()
			.mockRejectedValueOnce( new Error( 'clipboard failed' ) );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Copy API Key' } )
		);

		expect(
			await screen.findByText(
				'Failed to copy api key. Please try again. Error: clipboard failed',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
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

	it( "shows the server's message when disconnecting the governing site fails", async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			failDisconnect: {
				code: 'rest_forbidden',
				message: 'Sorry, you are not allowed to do that.',
				data: { status: 403 },
			},
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'https://governing.example.com/' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Disconnect Governing Site' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Disconnect' } ) );

		expect(
			await screen.findByText( 'Sorry, you are not allowed to do that.', {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
	} );

	it( 'shows an error notice when loading the governing site fails', async () => {
		mockSiteSettingsApi( {
			failGoverningSite: {},
		} );

		render( <SiteSettings /> );

		expect(
			await screen.findByText(
				'Failed to fetch governing site. Please try again later.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );

	it( 'shows an error notice when regeneration returns no secret key', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			regeneratedKey: '',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Regenerate API Key' } )
		);

		expect(
			await screen.findByText(
				'Failed to regenerate API key. Please try again later.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );

	it( 'shows an error notice when regenerating the api key fails', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
			failRegenerate: {},
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'api-key' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Regenerate API Key' } )
		);

		expect(
			await screen.findByText(
				'Error regenerating API key. Please try again later.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
	} );

	it( 'closes the disconnect modal when cancel is clicked', async () => {
		mockSiteSettingsApi( {
			governingSiteUrl: 'https://governing.example.com/',
		} );

		render( <SiteSettings /> );

		await screen.findByDisplayValue( 'https://governing.example.com/' );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Disconnect Governing Site' } )
		);

		expect(
			screen.getByText(
				'Are you sure you want to disconnect from the governing site? This action cannot be undone.'
			)
		).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		await waitFor( () => {
			expect(
				screen.queryByText(
					'Are you sure you want to disconnect from the governing site? This action cannot be undone.'
				)
			).not.toBeInTheDocument();
		} );
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
