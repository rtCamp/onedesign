/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import PatternModal from '@/admin/patterns/components/PatternModal';

// Child components and WP data are heavy; stub them for a focused smoke test of PatternModal's composition.
jest.mock( '@/admin/patterns/components/BasePatternsTab', () => ( {
	__esModule: true,
	default: () => <div>base-tab</div>,
} ) );
jest.mock( '@/admin/patterns/components/AppliedPatternsTab', () => ( {
	__esModule: true,
	default: () => <div>applied-tab</div>,
} ) );
jest.mock( '@/admin/patterns/components/Category', () => ( {
	__esModule: true,
	default: () => <div>category</div>,
} ) );
jest.mock( '@/store', () => ( {
	__esModule: true,
	store: {},
} ) );
jest.mock( '@wordpress/icons', () => ( { cog: 'cog' } ) );
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn( ( opts: { path: string } ) => {
		if ( opts.path.includes( 'get-all-brand-site-patterns' ) ) {
			return Promise.resolve( { success: true, patterns: {} } );
		}
		// configured-sites, local-patterns → arrays
		return Promise.resolve( [] );
	} ),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( cb ) =>
		cb( () => ( {
			getSitePatterns: () => ( {} ),
			getEditedPostAttribute: () => ( { brand_site: [] } ),
		} ) )
	),
	useDispatch: jest.fn( () => ( {
		fetchSitePatterns: jest.fn(),
		setSitePatterns: jest.fn(),
		editPost: jest.fn(),
	} ) ),
} ) );
jest.mock( '@wordpress/components', () => ( {
	Modal: ( { title, children }: { title: string; children: ReactNode } ) => (
		<div>
			<h1>{ title }</h1>
			{ children }
		</div>
	),
	SearchControl: () => <input aria-label="search" />,
	TabPanel: ( {
		tabs,
		children,
	}: {
		tabs: Array< { name: string } >;
		children: ( tab: { name: string } ) => ReactNode;
	} ) => <div>{ tabs[ 0 ] ? children( tabs[ 0 ] ) : null }</div>,
	Spinner: () => <div>spinner</div>,
	Button: ( { children }: { children?: ReactNode } ) => (
		<button>{ children }</button>
	),
} ) );

describe( 'PatternModal', () => {
	it( 'renders the patterns library modal shell', async () => {
		render( <PatternModal /> );
		expect(
			await screen.findByText( 'Patterns Library' )
		).toBeInTheDocument();
		expect( screen.getByText( 'base-tab' ) ).toBeInTheDocument();
	} );
} );
