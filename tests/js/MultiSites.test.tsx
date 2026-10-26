/**
 * External dependencies
 */
import { describe, expect, it, type Mock, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MultiSites from '@/components/MultiSites';

const fetchMock = global.fetch as Mock;

describe( 'MultiSites', () => {
	it( 'surfaces an error notice when fetching sites fails', async () => {
		fetchMock.mockResolvedValue( { ok: false } );
		const setNotice = vi.fn();

		render(
			<MultiSites
				brandSites={ [] }
				setBrandSites={ vi.fn() }
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
