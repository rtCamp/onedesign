import {
	describe,
	expect,
	it,
	type Mock,
	type MockedFunction,
	vi,
} from 'vitest';
import { useState } from '@wordpress/element';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteModal from '@/components/SiteModal';
import { defaultBrandSite } from '@/admin/settings/page';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const baseFormData = {
	...defaultBrandSite,
	name: 'Brand Site',
	url: 'https://brand.example.com',
	api_key: 'secret-key',
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
	it( 'disables submit when editing without changes', () => {
		// The key field starts empty when editing.
		const stored = { ...baseFormData, api_key: '' };

		render(
			<SiteModalHarness
				editing
				initialData={ stored }
				originalData={ stored }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeDisabled();
	} );

	it( 'shows validation feedback for invalid input', async () => {
		render(
			<SiteModalHarness
				initialData={ {
					name: 'A very long site name beyond',
					url: 'invalid-url',
					api_key: 'secret-key',
				} }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'Site Name must be under 20 characters.'
			)
		).toHaveLength( 2 );
	} );

	it( 'prevents duplicate site urls', async () => {
		const onSubmit = vi.fn().mockResolvedValue( true );

		render(
			<SiteModalHarness
				onSubmit={ onSubmit }
				sites={ [
					{
						name: 'Existing',
						url: 'https://brand.example.com/',
						api_key: 'existing-key',
					},
				] }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'Site URL already exists. Please use a different URL.'
			)
		).toHaveLength( 2 );
		expect( onSubmit ).not.toHaveBeenCalled();
	} );

	it( 'shows an error when saving the site fails', async () => {
		const onSubmit = vi.fn().mockResolvedValue( false );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'An error occurred while saving the site. Please try again.'
			)
		).toHaveLength( 2 );
		expect( onSubmit ).toHaveBeenCalled();
	} );

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
