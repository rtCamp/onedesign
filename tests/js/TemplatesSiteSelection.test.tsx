/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SiteSelection from '@/admin/templates/components/SiteSelection';

const baseProps = {
	isApplying: false,
	setIsApplying: jest.fn(),
	onApply: jest.fn(),
	setIsApplyModalOpen: jest.fn(),
	setSelectedSites: jest.fn(),
	selectedSites: [],
	notice: null,
	brandSiteTemplates: {},
	selectedTemplates: [],
	sitesHealthCheckResult: {},
};

describe( 'templates/SiteSelection', () => {
	it( 'shows the empty state when there are no connected sites', () => {
		render( <SiteSelection { ...baseProps } siteInfo={ [] } /> );
		expect(
			screen.getByText( 'No connected sites found.' )
		).toBeInTheDocument();
	} );

	it( 'renders each connected site', () => {
		render(
			<SiteSelection
				{ ...baseProps }
				siteInfo={ [
					{ id: 1, name: 'Alpha', url: 'https://alpha.example' },
				] }
			/>
		);
		expect( screen.getByText( 'Alpha' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /apply templates/i } )
		).toBeInTheDocument();
	} );
} );
