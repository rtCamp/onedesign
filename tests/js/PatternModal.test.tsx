/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import PatternModal from '@/admin/patterns/components/PatternModal';

// The real tabs load @wordpress/blocks, which Vitest can't import.
vi.mock( '@/admin/patterns/components/BasePatternsTab', () => ( {
	default: () => <div>base-tab</div>,
} ) );
vi.mock( '@/admin/patterns/components/AppliedPatternsTab', () => ( {
	default: () => <div>applied-tab</div>,
} ) );
vi.mock( '@/store', () => ( {
	store: {},
} ) );
vi.mock( '@wordpress/data' );

describe( 'PatternModal', () => {
	beforeEach( () => {
		vi.mocked( apiFetch ).mockImplementation( async ( options ) => {
			if ( options.path?.includes( 'get-all-brand-site-patterns' ) ) {
				return { success: true, patterns: {} };
			}
			// configured-sites, local-patterns → arrays
			return [];
		} );
	} );

	it( 'renders the patterns library modal shell', async () => {
		render( <PatternModal /> );
		expect(
			await screen.findByText( 'Patterns Library' )
		).toBeInTheDocument();
		expect( screen.getByText( 'base-tab' ) ).toBeInTheDocument();
	} );
} );
