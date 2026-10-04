/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SiteModal from '@/components/SiteModal';

const baseProps = {
	setFormData: jest.fn(),
	onSubmit: jest.fn(),
	onClose: jest.fn(),
};

describe( 'SiteModal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

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
} );
