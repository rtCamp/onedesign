/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BasePatternsTab from '@/admin/patterns/components/BasePatternsTab';

// MemoizedPatternPreview (imported by BasePatternsTab) pulls in the block editor.
jest.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => null,
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	parse: jest.fn( () => [] ),
} ) );

const baseProps = {
	visibleCount: 9,
	selectedPatterns: [],
	handlePatternSelection: jest.fn(),
	hasMorePatterns: false,
	loadMorePatterns: jest.fn(),
	applySelectedPatterns: jest.fn(),
	setSelectedPatterns: jest.fn(),
};

describe( 'BasePatternsTab', () => {
	it( 'shows the loading state', () => {
		render(
			<BasePatternsTab { ...baseProps } isLoading basePatterns={ [] } />
		);
		expect( screen.getByText( /loading patterns/i ) ).toBeInTheDocument();
	} );

	it( 'shows the empty state when there are no base patterns', () => {
		render(
			<BasePatternsTab
				{ ...baseProps }
				isLoading={ false }
				basePatterns={ [] }
			/>
		);
		expect( screen.getByText( 'No patterns found' ) ).toBeInTheDocument();
	} );
} );
