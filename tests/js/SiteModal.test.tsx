import { defaultBrandSite } from '@/admin/settings/page';
import SiteModal from '@/components/SiteModal';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import {
	describe,
	expect,
	it,
	type Mock,
	type MockedFunction,
	vi,
} from 'vitest';
import { findNotice } from './library-routes';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const baseFormData = {
	...defaultBrandSite,
	name: 'Brand Site',
	url: 'https://brand.example.com',
	api_key: 'secret-key',
};

const baseProps = {
	setFormData: vi.fn(),
	onSubmit: vi.fn().mockResolvedValue( true ),
	onClose: vi.fn(),
	sites: [],
	originalData: undefined,
};

function SiteModalHarness( {
	initialData = baseFormData,
	editing = false,
	sites = [],
	originalData,
	onSubmit = vi.fn().mockResolvedValue( true ),
}: {
	initialData?: typeof defaultBrandSite;
	editing?: boolean;
	sites?: Array< typeof defaultBrandSite >;
	originalData?: typeof defaultBrandSite;
	onSubmit?: Mock< () => Promise< boolean | string > >;
} ) {
	const [ formData, setFormData ] = useState( initialData );

	return (
		<SiteModal
			formData={ formData }
			setFormData={ setFormData }
			onSubmit={ onSubmit }
			onClose={ vi.fn() }
			editing={ editing }
			sites={ sites }
			originalData={ originalData }
		/>
	);
}

describe( 'SiteModal', () => {
	it( 'renders the add-site action when not editing', () => {
		render(
			<SiteModal
				formData={ {
					name: 'X',
					url: 'https://x.example',
					api_key: 'k',
				} }
				editing={ false }
				{ ...baseProps }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: /add site/i } )
		).toBeInTheDocument();
	} );

	it( 'validates the URL before submitting', async () => {
		render(
			<SiteModal
				formData={ { name: 'X', url: 'not a url', api_key: 'k' } }
				editing={ false }
				{ ...baseProps }
			/>
		);
		fireEvent.click( screen.getByRole( 'button', { name: /add site/i } ) );
		// The message renders both in the Notice and WP's a11y live-region.
		const matches = await screen.findAllByText( /enter a valid url/i );
		expect( matches.length ).toBeGreaterThan( 0 );
		expect( baseProps.onSubmit ).not.toHaveBeenCalled();
	} );

	it.each( [ 'abc', 'ftp://test.com' ] )(
		'refuses %s as the site URL',
		async ( url ) => {
			const onSubmit = vi.fn().mockResolvedValue( true );

			render(
				<SiteModalHarness
					onSubmit={ onSubmit }
					initialData={ { ...baseFormData, url } }
				/>
			);

			fireEvent.click(
				screen.getByRole( 'button', { name: 'Add Site' } )
			);

			expect(
				await findNotice(
					'Enter a valid URL (must start with http or https).'
				)
			).toBeInTheDocument();
			expect( onSubmit ).not.toHaveBeenCalled();
		}
	);

	it.each( [ 'name', 'url', 'api_key' ] )(
		"can't add a site without its %s",
		( field ) => {
			render(
				<SiteModalHarness
					initialData={ { ...baseFormData, [ field ]: '' } }
				/>
			);

			expect(
				screen.getByRole( 'button', { name: 'Add Site' } )
			).toBeDisabled();
		}
	);

	it( "can't add a site with a key of only spaces", () => {
		render(
			<SiteModalHarness
				initialData={ { ...baseFormData, api_key: '   ' } }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Add Site' } )
		).toBeDisabled();
	} );

	it.each( [
		[ 'name', 'Site Name is required.' ],
		[ 'url', 'Site URL is required.' ],
	] )(
		'refuses a %s of only spaces, and says it is required',
		async ( field, message ) => {
			const onSubmit = vi.fn().mockResolvedValue( true );

			render(
				<SiteModalHarness
					onSubmit={ onSubmit }
					initialData={ { ...baseFormData, [ field ]: '   ' } }
				/>
			);

			fireEvent.click(
				screen.getByRole( 'button', { name: 'Add Site' } )
			);

			expect( await findNotice( message ) ).toBeInTheDocument();
			expect( onSubmit ).not.toHaveBeenCalled();
		}
	);

	it( 'submits without calling the brand site, which the server checks on save', async () => {
		const onSubmit = vi.fn().mockResolvedValue( true );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		await waitFor( () => {
			expect( onSubmit ).toHaveBeenCalled();
		} );

		expect( mockedApiFetch ).not.toHaveBeenCalled();
		expect( global.fetch ).not.toHaveBeenCalled();
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	it( 'saves an edit without a new key', async () => {
		const onSubmit = vi.fn().mockResolvedValue( true );
		const stored = { ...baseFormData, id: 'one', api_key: '' };

		render(
			<SiteModalHarness
				editing
				onSubmit={ onSubmit }
				initialData={ { ...stored, name: 'Renamed' } }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Update Site' } )
		);

		await waitFor( () => {
			expect( onSubmit ).toHaveBeenCalled();
		} );
	} );

	it( 'needs the key again when an edit changes the URL', () => {
		const stored = { ...baseFormData, id: 'one', api_key: '' };

		render(
			<SiteModalHarness
				editing
				initialData={ {
					...stored,
					url: 'https://moved.example.com',
				} }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeDisabled();
		expect( screen.getByText( 'API Key*' ) ).toBeInTheDocument();
	} );

	it( "doesn't need the key again when an edit only adds or drops the trailing slash", () => {
		const stored = {
			...baseFormData,
			id: 'one',
			url: 'https://brand.example.com/',
			api_key: '',
		};

		render(
			<SiteModalHarness
				editing
				initialData={ {
					...stored,
					url: 'https://brand.example.com',
				} }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeEnabled();
		expect( screen.queryByText( 'API Key*' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the server message when saving fails', async () => {
		const onSubmit = vi.fn().mockResolvedValue( 'Invalid site data.' );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText( 'Invalid site data.' )
		).toHaveLength( 2 );
	} );
} );
