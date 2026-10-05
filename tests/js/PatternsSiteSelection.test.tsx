/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteSelection from '@/admin/patterns/components/SiteSelection';

jest.mock( '@wordpress/components', () => ( {
	Button: ( {
		children,
		onClick,
		disabled,
	}: {
		children: ReactNode;
		onClick?: () => void;
		disabled?: boolean;
	} ) => (
		<button onClick={ onClick } disabled={ disabled }>
			{ children }
		</button>
	),
	Notice: ( { children }: { children: ReactNode } ) => (
		<div>{ children }</div>
	),
	Spinner: () => <div role="status">loading</div>,
} ) );
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( () => ( { BrandSite: [] } ) ),
	useDispatch: jest.fn( () => ( { editPost: jest.fn() } ) ),
} ) );
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );
jest.mock( '@/hooks/useSitesManagement', () => ( {
	__esModule: true,
	default: () => ( { sitesHealthCheckResult: {}, isLoading: false } ),
} ) );

const apiFetchMock = apiFetch as unknown as jest.Mock;

describe( 'patterns/SiteSelection', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'shows the empty state when no brand sites are configured', async () => {
		apiFetchMock.mockResolvedValue( [] );

		render( <SiteSelection setIsSiteSelected={ jest.fn() } /> );

		expect(
			await screen.findByText( /no brand sites configured/i )
		).toBeInTheDocument();
	} );

	it( 'renders configured sites once loaded', async () => {
		apiFetchMock.mockResolvedValue( [
			{ id: 1, name: 'Alpha', url: 'https://alpha.example' },
		] );

		render( <SiteSelection setIsSiteSelected={ jest.fn() } /> );

		expect( await screen.findByText( 'Alpha' ) ).toBeInTheDocument();
	} );
} );
