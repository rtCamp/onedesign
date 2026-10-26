import {
	beforeEach,
	describe,
	expect,
	it,
	type MockedFunction,
	vi,
} from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

// Mock child components to isolate SettingsPage behavior
const mockSubmitResults: unknown[] = [];

vi.mock( '@/components/SiteTable', () => ( {
	__esModule: true,
	default: ( {
		sites,
		onEdit,
		onDelete,
		setFormData,
		setShowModal,
	}: {
		sites: Array< { name: string; url: string; api_key: string } >;
		onEdit: ( index: number ) => void;
		onDelete: ( index: number | null ) => void;
		setFormData: ( data: {
			name: string;
			url: string;
			api_key: string;
		} ) => void;
		setShowModal: ( show: boolean ) => void;
	} ) => (
		<div data-testid="site-table">
			<span data-testid="site-count">{ sites.length }</span>
			<button
				type="button"
				onClick={ () => {
					onEdit( 0 );
					setFormData( {
						name: 'Edited Brand Site',
						url: 'https://edited.example.com/',
						api_key: 'edited-key',
					} );
				} }
			>
				Edit Site
			</button>
			<button type="button" onClick={ () => setShowModal( true ) }>
				Open Modal
			</button>
			<button type="button" onClick={ () => onDelete( 0 ) }>
				Delete Site
			</button>
		</div>
	),
} ) );

vi.mock( '@/components/SiteModal', () => ( {
	__esModule: true,
	default: ( {
		onSubmit,
		onClose,
	}: {
		onSubmit: () => Promise< boolean | string >;
		onClose: () => void;
	} ) => (
		<div data-testid="site-modal">
			<button
				type="button"
				onClick={ () =>
					void onSubmit().then( ( result ) =>
						mockSubmitResults.push( result )
					)
				}
			>
				Submit Modal
			</button>
			<button type="button" onClick={ onClose }>
				Close Modal
			</button>
		</div>
	),
} ) );

vi.mock( '@/components/SiteSettings', () => ( {
	__esModule: true,
	default: () => <div data-testid="site-settings">Brand Site Settings</div>,
} ) );

import SettingsPage from '@/admin/settings/page';
import type { OneDesignSharedSite } from '@/types/global';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

// The page starts from the brand sites the server localized.
const setSharedSites = ( sharedSites: OneDesignSharedSite[] ) => {
	window.OneDesignSettings = { ...window.OneDesignSettings, sharedSites };
};

describe( 'SettingsPage', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.OneDesignSettings = {
			...window.OneDesignSettings,
			siteType: 'governing-site',
			sharedSites: [],
		};
		document.body.className = '';
		mockSubmitResults.length = 0;
	} );

	it( 'loads shared sites and renders the page structure', async () => {
		setSharedSites( [
			{
				id: 'one',
				name: 'Brand Site',
				url: 'https://brand.example.com/',
				logo: '',
				logo_id: 0,
			},
		] );

		render( <SettingsPage /> );

		await waitFor( () => {
			expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent(
				'1'
			);
		} );
	} );

	it( 'saves site changes and shows success feedback', async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [
				{
					name: 'Brand Site',
					url: 'https://brand.example.com/',
					api_key: 'key',
				},
			],
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);

		// Snackbar renders in both accessibility region and visible content
		const notices = await screen.findAllByText(
			'Brand Site saved successfully.'
		);
		expect( notices.length ).toBeGreaterThanOrEqual( 1 );
	} );

	it( 'keeps the modal open and returns the server message when saving fails', async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			message: 'Invalid site data.',
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ 'Invalid site data.' ] );
		} );
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();
	} );

	it( 'keeps the modal open when the response lacks shared sites', async () => {
		setSharedSites( [
			{
				id: 'one',
				name: 'Brand Site',
				url: 'https://brand.example.com/',
				logo: '',
				logo_id: 0,
			},
		] );
		mockedApiFetch.mockResolvedValueOnce( {} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ false ] );
		} );
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();
	} );

	it( 'submits edit changes correctly', async () => {
		setSharedSites( [
			{
				id: 'one',
				name: 'Brand Site',
				url: 'https://brand.example.com/',
				logo: '',
				logo_id: 0,
			},
		] );
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [
				{
					name: 'Edited Brand Site',
					url: 'https://edited.example.com/',
					api_key: 'edited-key',
				},
			],
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Edit Site' } )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Open Modal' } ) );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					method: 'POST',
					data: {
						sites_data: [
							{
								name: 'Edited Brand Site',
								url: 'https://edited.example.com/',
								api_key: 'edited-key',
							},
						],
					},
				} )
			);
		} );
	} );

	it( 'closes the modal via close action', async () => {
		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Close Modal' } )
		);

		expect( screen.queryByTestId( 'site-modal' ) ).not.toBeInTheDocument();
	} );
} );
