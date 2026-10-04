/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AppliedPatternsTab from '@/admin/patterns/components/AppliedPatternsTab';

// MemoizedPatternPreview (a child) pulls in the block editor, whose transitive
// ESM deps Jest can't transform — stub them out.
jest.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => null,
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	parse: jest.fn( () => [] ),
} ) );

const baseProps = {
	currentPage: 1,
	PER_PAGE: 9,
	handlePatternSelection: jest.fn(),
	setCurrentPage: jest.fn(),
	siteInfo: { value: '1' },
	applySelectedPatterns: jest.fn(),
	setSelectedPatterns: jest.fn(),
	notice: null,
	setNotice: jest.fn(),
};

describe( 'AppliedPatternsTab', () => {
	it( 'shows the empty state when there are no applied patterns', () => {
		render(
			<AppliedPatternsTab
				{ ...baseProps }
				appliedPatterns={ [] }
				selectedPatterns={ [] }
			/>
		);
		expect( screen.getByText( 'No patterns found.' ) ).toBeInTheDocument();
	} );

	it( 'shows the selected count and enables removal when patterns are selected', () => {
		render(
			<AppliedPatternsTab
				{ ...baseProps }
				appliedPatterns={ [] }
				selectedPatterns={ [ 'a', 'b' ] }
			/>
		);
		expect( screen.getByText( '2' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /^remove pattern$/i } )
		).toBeEnabled();
	} );
} );
