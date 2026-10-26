/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import BasePatternsTab from '@/admin/patterns/components/BasePatternsTab';

// MemoizedPatternPreview (imported by BasePatternsTab) pulls in the block editor.
vi.mock( '@wordpress/block-editor' );
vi.mock( '@wordpress/blocks' );

const baseProps = {
	visibleCount: 9,
	selectedPatterns: [],
	handlePatternSelection: vi.fn(),
	hasMorePatterns: false,
	loadMorePatterns: vi.fn(),
	applySelectedPatterns: vi.fn(),
	setSelectedPatterns: vi.fn(),
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
