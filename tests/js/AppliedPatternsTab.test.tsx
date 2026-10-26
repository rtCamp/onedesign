/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AppliedPatternsTab from '@/admin/patterns/components/AppliedPatternsTab';

// MemoizedPatternPreview (a child) pulls in the block editor, which doesn't load outside WordPress, so we stub it out.
vi.mock( '@wordpress/block-editor' );
vi.mock( '@wordpress/blocks' );

const baseProps = {
	currentPage: 1,
	PER_PAGE: 9,
	handlePatternSelection: vi.fn(),
	setCurrentPage: vi.fn(),
	siteInfo: { value: '1' },
	applySelectedPatterns: vi.fn(),
	setSelectedPatterns: vi.fn(),
	notice: null,
	setNotice: vi.fn(),
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
