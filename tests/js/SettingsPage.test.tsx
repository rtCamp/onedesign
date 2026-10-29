import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	type MockedFunction,
	vi,
} from 'vitest';
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';
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

	afterEach( () => {
		vi.useRealTimers();
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

	it( "shows the server's error and leaves the brand site alone when deleting fails", async () => {
		setSharedSites( [
			{
				id: 'one',
				name: 'Brand Site',
				url: 'https://brand.example.com/',
				logo: '',
				logo_id: 0,
			},
		] );
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_not_found',
			message: 'The brand site was not found.',
			data: { status: 404 },
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		const notices = await screen.findAllByText(
			'The brand site was not found.'
		);
		expect( notices.length ).toBeGreaterThanOrEqual( 1 );
		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '1' );
	} );

	it( 'removes a brand site through the server, which disconnects it', async () => {
		setSharedSites( [
			{
				id: 'one',
				name: 'Brand Site',
				url: 'https://brand.example.com/',
				logo: '',
				logo_id: 0,
			},
			{
				id: 'two',
				name: 'Remaining Site',
				url: 'https://remaining.example.com/',
				logo: '',
				logo_id: 0,
			},
		] );
		mockedApiFetch.mockResolvedValueOnce( {
			governing_site_notified: true,
			message: 'Brand site removed successfully.',
		} );
		global.fetch = vi.fn();

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		await waitFor( () => {
			expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent(
				'1'
			);
		} );
		expect( mockedApiFetch ).toHaveBeenLastCalledWith( {
			path: '/onedesign/v1/shared-sites/one',
			method: 'DELETE',
		} );
		expect( global.fetch ).not.toHaveBeenCalled();
		const notices = await screen.findAllByText(
			'Brand site removed successfully.'
		);
		expect( notices.length ).toBeGreaterThanOrEqual( 1 );
		expect(
			screen.queryByText( /was removed, but/, {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'gives each snackbar its own timeout', async () => {
		vi.useFakeTimers();
		const site = {
			id: 'one',
			name: 'Brand Site',
			url: 'https://brand.example.com/',
			logo: '',
			logo_id: 0,
		};
		setSharedSites( [ site ] );
		mockedApiFetch
			.mockResolvedValueOnce( { shared_sites: [ site ] } )
			.mockResolvedValueOnce( {
				governing_site_notified: true,
				message: 'Brand site removed successfully.',
			} );
		const snackbar = ( text: string ) =>
			screen.queryByText( text, {
				selector: '.components-snackbar__content',
			} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);
		await waitFor( () =>
			expect(
				snackbar( 'Brand Site saved successfully.' )
			).toBeInTheDocument()
		);

		act( () => {
			vi.advanceTimersByTime( 6000 );
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Delete Site' } )
		);
		await waitFor( () =>
			expect(
				snackbar( 'Brand site removed successfully.' )
			).toBeInTheDocument()
		);

		// Past the first snackbar's timeout, the second one still shows.
		act( () => {
			vi.advanceTimersByTime( 6000 );
		} );
		expect(
			snackbar( 'Brand site removed successfully.' )
		).toBeInTheDocument();

		act( () => {
			vi.advanceTimersByTime( 4000 );
		} );
		expect(
			snackbar( 'Brand site removed successfully.' )
		).not.toBeInTheDocument();
	} );

	it( 'warns when a deleted brand site could not be notified', async () => {
		vi.useFakeTimers();
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
			governing_site_notified: false,
			message: 'Brand Site was removed, but it could not be notified.',
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		const message = 'Brand Site was removed, but it could not be notified.';
		expect(
			await screen.findByText( message, {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();

		// It outlasts a snackbar's timeout, and the admin dismisses it once they have acted on it.
		act( () => {
			vi.advanceTimersByTime( 20000 );
		} );
		expect(
			screen.getByText( message, {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: 'Close' } ) );
		expect(
			screen.queryByText( message, {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
	} );
} );
