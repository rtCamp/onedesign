/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import TemplateModal from '@/admin/templates/components/TemplateModal';

// Children + WP components are heavy; stub them for a focused smoke test.
jest.mock( '@/admin/templates/components/BaseSiteTemplates', () => ( {
	__esModule: true,
	default: () => <div>base-templates</div>,
} ) );
jest.mock( '@/admin/templates/components/BrandSiteTemplates', () => ( {
	__esModule: true,
	default: () => <div>brand-templates</div>,
} ) );
jest.mock( '@/admin/templates/components/SiteSelection', () => ( {
	__esModule: true,
	default: () => <div>site-selection</div>,
} ) );
jest.mock( '@/hooks/useSitesManagement', () => {
	// Return a STABLE reference; a fresh object each render churns the `[siteInfo]` effect and loops forever.
	const value = {
		siteInfo: {},
		sitesHealthCheckResult: {},
		isLoading: false,
	};
	return { __esModule: true, default: () => value };
} );
jest.mock( '@wordpress/icons', () => ( { cog: 'cog' } ) );
jest.mock( '@wordpress/components', () => ( {
	Modal: ( { title, children }: { title?: string; children: ReactNode } ) => (
		<div>
			{ title ? <h1>{ title }</h1> : null }
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

describe( 'TemplateModal', () => {
	beforeEach( () => {
		( global.fetch as jest.Mock ).mockResolvedValue( {
			json: async () => ( { success: true, templates: [] } ),
		} );
	} );

	it( 'renders the template library modal shell', async () => {
		render( <TemplateModal /> );
		expect(
			await screen.findByText( 'Template Library' )
		).toBeInTheDocument();
		expect(
			await screen.findByText( 'base-templates' )
		).toBeInTheDocument();
	} );
} );
