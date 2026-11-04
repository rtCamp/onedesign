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
	within,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SettingsPage from '@/admin/settings/page';
import type { OneDesignSharedSite } from '@/types/global';

// The whole settings screen, with its real table and modal.
const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;
const localizedSettings = window.OneDesignSettings;
const sharedSites: OneDesignSharedSite[] = [
	{
		id: 'brand-1',
		name: 'Brand One',
		url: 'https://brand-one.example.com/',
		logo: '',
		logo_id: 0,
	},
];

const fillModal = ( name: string, url: string, apiKey: string ) => {
	const dialog = screen.getByRole( 'dialog', { name: 'Add Brand Site' } );
	fireEvent.change( within( dialog ).getByLabelText( 'Site Name*' ), {
		target: { value: name },
	} );
	fireEvent.change( within( dialog ).getByLabelText( 'Site URL*' ), {
		target: { value: url },
	} );
	fireEvent.change( within( dialog ).getByLabelText( 'API Key*' ), {
		target: { value: apiKey },
	} );
	return dialog;
};

describe( 'settings screen', () => {
	beforeEach( () => {
		// WordPress component deprecation warnings aren't ours.
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		mockedApiFetch.mockReset();
		window.OneDesignSettings = {
			...localizedSettings,
			siteType: 'governing-site',
			sharedSites,
		};
	} );

	afterEach( () => {
		window.OneDesignSettings = localizedSettings;
	} );

	it( 'lists the brand sites the server localized, without their keys', () => {
		render( <SettingsPage /> );

		const row = screen.getByText( 'Brand One' ).closest( 'tr' );
		expect( row ).toHaveTextContent( 'https://brand-one.example.com/' );
		expect( row ).toHaveTextContent( '••••••••' );
	} );

	it( 'shows the brand site the server stored', async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [
				{
					id: 'brand-1',
					name: 'Brand One',
					url: 'https://brand-one.example.com/',
				},
				{
					id: 'brand-2',
					name: 'Brand Two',
					url: 'https://brand-two.example.com/',
				},
			],
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add Brand Site' } )
		);
		const dialog = fillModal(
			'Brand Two',
			'https://brand-two.example.com',
			'brand-two-key'
		);
		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Add Site' } )
		);

		await waitFor( () => {
			expect(
				screen.queryByRole( 'dialog', { name: 'Add Brand Site' } )
			).not.toBeInTheDocument();
		} );
		const row = screen.getByText( 'Brand Two' ).closest( 'tr' );
		expect( row ).toHaveTextContent( 'https://brand-two.example.com/' );
		expect( screen.queryByText( 'brand-two-key' ) ).not.toBeInTheDocument();
	} );

	it( "keeps what the admin typed and shows the server's error when saving fails", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_brand_unreachable',
			message:
				'The brand site could not be reached. Check its URL and API key.',
			data: { status: 400 },
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add Brand Site' } )
		);
		const dialog = fillModal(
			'Brand Two',
			'https://brand-two.example.com',
			'brand-two-key'
		);
		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Add Site' } )
		);

		expect(
			await within( dialog ).findByText(
				'The brand site could not be reached. Check its URL and API key.',
				{ selector: '.components-notice__content' }
			)
		).toBeInTheDocument();
		expect( within( dialog ).getByLabelText( 'Site Name*' ) ).toHaveValue(
			'Brand Two'
		);
		expect( within( dialog ).getByLabelText( 'Site URL*' ) ).toHaveValue(
			'https://brand-two.example.com'
		);
		expect( within( dialog ).getByLabelText( 'API Key*' ) ).toHaveValue(
			'brand-two-key'
		);
	} );

	it( 'shows the site type picker on a site that has no type yet', async () => {
		window.OneDesignSettings = {
			...window.OneDesignSettings,
			siteType: '',
		};
		const target = document.createElement( 'div' );
		target.id = 'onedesign-settings-page';
		document.body.append( target );
		// The entry reads the site type and mounts the page when it loads.
		vi.resetModules();

		await act( async () => {
			await import( '@/admin/settings' );
		} );

		expect(
			await screen.findByRole( 'combobox', { name: 'Site Type' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Brand One' ) ).not.toBeInTheDocument();
	} );
} );
