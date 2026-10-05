/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BrandSiteTemplates from '@/admin/templates/components/BrandSiteTemplates';

// MemoizedTemplatePreview pulls in the block editor.
jest.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => null,
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	parse: jest.fn( () => [] ),
} ) );

const baseProps = {
	currentPage: 1,
	PER_PAGE: 9,
	handleTemplateSelection: jest.fn(),
	setCurrentPage: jest.fn(),
	currentSiteId: 1,
	fetchConnectedSitesTemplates: jest.fn(),
	setSelectedTemplates: jest.fn(),
	allTemplates: [],
	notice: null,
	setNotice: jest.fn(),
};

describe( 'BrandSiteTemplates', () => {
	it( 'shows the empty state when there are no templates', () => {
		render(
			<BrandSiteTemplates
				{ ...baseProps }
				filteredTemplates={ [] }
				selectedTemplates={ [] }
			/>
		);
		expect( screen.getByText( 'No templates found.' ) ).toBeInTheDocument();
	} );

	it( 'shows the selected count and enables removal when templates are selected', () => {
		render(
			<BrandSiteTemplates
				{ ...baseProps }
				filteredTemplates={ [] }
				selectedTemplates={ [ 'a', 'b' ] }
			/>
		);
		expect( screen.getByText( '2' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /^remove template$/i } )
		).toBeEnabled();
	} );
} );
