/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SiteTable from '@/components/SiteTable';

const baseProps = {
	onEdit: jest.fn(),
	onDelete: jest.fn(),
	setFormData: jest.fn(),
	setShowModal: jest.fn(),
	setSites: jest.fn(),
	setNotice: jest.fn(),
};

describe( 'SiteTable', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'shows an empty-state row when there are no sites', () => {
		render( <SiteTable sites={ [] } { ...baseProps } /> );
		expect(
			screen.getByText( /no brand sites found/i )
		).toBeInTheDocument();
	} );

	it( 'renders a row per site with name and url', () => {
		const sites = [
			{
				id: 1,
				name: 'Alpha',
				url: 'https://alpha.example',
				api_key: 'abcdefghij1234',
				is_editable: true,
			},
		];
		render( <SiteTable sites={ sites } { ...baseProps } /> );
		expect( screen.getByText( 'Alpha' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'https://alpha.example' )
		).toBeInTheDocument();
	} );

	it( 'opens the edit flow for a site', () => {
		const sites = [
			{
				id: 7,
				name: 'Beta',
				url: 'https://beta.example',
				api_key: 'key1234567',
			},
		];
		render( <SiteTable sites={ sites } { ...baseProps } /> );
		fireEvent.click( screen.getByRole( 'button', { name: /^edit$/i } ) );
		expect( baseProps.setFormData ).toHaveBeenCalledWith( sites[ 0 ] );
		expect( baseProps.onEdit ).toHaveBeenCalledWith( 0 );
		expect( baseProps.setShowModal ).toHaveBeenCalledWith( true );
	} );

	it( 'confirms deletion through the modal', () => {
		const sites = [
			{
				id: 3,
				name: 'Gamma',
				url: 'https://gamma.example',
				api_key: 'key1234567',
			},
		];
		render( <SiteTable sites={ sites } { ...baseProps } /> );
		fireEvent.click( screen.getByRole( 'button', { name: /^delete$/i } ) );
		// Modal is open; confirm the deletion.
		const confirm = screen
			.getAllByRole( 'button', { name: /^delete$/i } )
			.at( -1 );
		fireEvent.click( confirm as HTMLElement );
		expect( baseProps.onDelete ).toHaveBeenCalledWith( 0 );
	} );
} );
