/**
 * External dependencies
 */
import { act, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MultiSites from '@/components/MultiSites';

const fetchMock = global.fetch as jest.Mock;

describe( 'MultiSites', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the "add brand sites" trigger button', async () => {
		fetchMock.mockResolvedValue( {
			ok: true,
			json: async () => ( { sites: [] } ),
		} );

		// The mount effect fires an async fetch; wrap in act so the state update is flushed before assertions.
		await act( async () => {
			render(
				<MultiSites
					brandSites={ [] }
					setBrandSites={ jest.fn() }
					setNotice={ jest.fn() }
				/>
			);
		} );

		expect(
			screen.getByRole( 'button', {
				name: /add brand sites from current mu/i,
			} )
		).toBeInTheDocument();
	} );

	it( 'surfaces an error notice when fetching sites fails', async () => {
		fetchMock.mockResolvedValue( { ok: false } );
		const setNotice = jest.fn();

		render(
			<MultiSites
				brandSites={ [] }
				setBrandSites={ jest.fn() }
				setNotice={ setNotice }
			/>
		);

		await waitFor( () =>
			expect( setNotice ).toHaveBeenCalledWith(
				expect.objectContaining( { type: 'error' } )
			)
		);
	} );
} );
