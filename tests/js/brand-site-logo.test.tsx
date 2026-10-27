import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useState } from '@wordpress/element';
import { fireEvent, render, screen } from '@testing-library/react';
import SiteModal from '@/components/SiteModal';
import SiteTable from '@/components/SiteTable';
import type { BrandSite } from '@/admin/settings/page';

const stored: BrandSite = {
	id: 'one',
	name: 'Brand One',
	url: 'https://brand-one.example.com/',
	logo: 'https://governing.example.com/logo.png',
	logo_id: 12,
};

// Stands in for wp.media, where the admin picks the given image.
const mockMediaLibrary = ( picked: { id: number; url: string } ) => {
	const handlers: { open?: () => void; select?: () => void } = {};
	const selection = {
		first: () => ( { toJSON: () => picked } ),
		add: () => {},
	};
	const frame = {
		on: ( event: 'open' | 'select', callback: () => void ) => {
			handlers[ event ] = callback;
		},
		state: () => ( { get: () => selection } ),
		open: () => {
			handlers.open?.();
			handlers.select?.();
		},
	};
	const media = Object.assign( () => frame, {
		attachment: ( id: number ) => ( { id, fetch: () => {} } ),
	} );

	( window as unknown as { wp: unknown } ).wp = { media };
};

const EditHarness = ( {
	onSubmit,
}: {
	onSubmit: () => Promise< boolean >;
} ) => {
	// The key field starts empty when editing.
	const [ formData, setFormData ] = useState< BrandSite >( {
		...stored,
		api_key: '',
	} );

	return (
		<>
			<SiteModal
				formData={ formData }
				setFormData={ setFormData }
				onSubmit={ onSubmit }
				onClose={ vi.fn() }
				editing
				sites={ [ stored ] }
				originalData={ stored }
			/>
			<output data-testid="form-data">
				{ JSON.stringify( formData ) }
			</output>
		</>
	);
};

describe( 'brand site logo', () => {
	beforeEach( () => {
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		delete ( window as unknown as { wp?: unknown } ).wp;
	} );

	it( 'replaces the logo from the media library', () => {
		mockMediaLibrary( {
			id: 34,
			url: 'https://governing.example.com/new-logo.png',
		} );

		render( <EditHarness onSubmit={ vi.fn() } /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Replace Logo' } )
		);

		expect(
			JSON.parse( screen.getByTestId( 'form-data' ).textContent ?? '' )
		).toMatchObject( {
			logo: 'https://governing.example.com/new-logo.png',
			logo_id: 34,
		} );
		// A new logo is a change, so the edit can be saved without a new key.
		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeEnabled();
	} );

	it( 'removes the logo', () => {
		render( <EditHarness onSubmit={ vi.fn() } /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Remove Logo' } )
		);

		expect(
			JSON.parse( screen.getByTestId( 'form-data' ).textContent ?? '' )
		).toMatchObject( { logo: '', logo_id: null } );
		expect(
			screen.getByRole( 'button', { name: 'Select Logo' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeEnabled();
	} );

	it( "shows each brand site's logo, or its initials, and never a key", () => {
		render(
			<SiteTable
				sites={ [
					{ ...stored, api_key: 'secret-brand-key' },
					{
						id: 'two',
						name: 'Second Brand',
						url: 'https://brand-two.example.com/',
					},
				] }
				onEdit={ vi.fn() }
				onDelete={ vi.fn() }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		expect(
			screen
				.getByText( 'Brand One' )
				.closest( 'tr' )
				?.querySelector( 'img' )
		).toHaveAttribute( 'src', stored.logo );
		expect(
			screen.getByText( 'Second Brand' ).closest( 'tr' )
		).toHaveTextContent( 'SB' );
		expect( screen.queryByText( /secret/ ) ).not.toBeInTheDocument();
	} );
} );
