/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteSelection from '@/admin/patterns/components/SiteSelection';

vi.mock( '@wordpress/components' );
vi.mock( '@wordpress/data' );
vi.mock( '@/hooks/useSitesManagement', () => ( {
	default: () => ( { sitesHealthCheckResult: {}, isLoading: false } ),
} ) );

const apiFetchMock = vi.mocked( apiFetch );

describe( 'patterns/SiteSelection', () => {
	it( 'shows the empty state when no brand sites are configured', async () => {
		apiFetchMock.mockResolvedValue( [] );

		render( <SiteSelection setIsSiteSelected={ vi.fn() } /> );

		expect(
			await screen.findByText( /no brand sites configured/i )
		).toBeInTheDocument();
	} );

	it( 'renders configured sites once loaded', async () => {
		apiFetchMock.mockResolvedValue( [
			{ id: 1, name: 'Alpha', url: 'https://alpha.example' },
		] );

		render( <SiteSelection setIsSiteSelected={ vi.fn() } /> );

		expect( await screen.findByText( 'Alpha' ) ).toBeInTheDocument();
	} );
} );
