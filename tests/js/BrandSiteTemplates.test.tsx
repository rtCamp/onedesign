/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BrandSiteTemplates from '@/admin/templates/components/BrandSiteTemplates';

// MemoizedTemplatePreview pulls in the block editor.
vi.mock( '@wordpress/block-editor' );
vi.mock( '@wordpress/blocks' );

const baseProps = {
	currentPage: 1,
	PER_PAGE: 9,
	handleTemplateSelection: vi.fn(),
	setCurrentPage: vi.fn(),
	currentSiteId: 1,
	fetchConnectedSitesTemplates: vi.fn(),
	setSelectedTemplates: vi.fn(),
	allTemplates: [],
	notice: null,
	setNotice: vi.fn(),
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
