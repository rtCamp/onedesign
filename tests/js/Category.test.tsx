/**
 * External dependencies
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Category from '@/admin/patterns/components/Category';

const mockedApiFetch = vi.mocked( apiFetch );

const BASE_PATTERNS = [ { categories: [ 'featured' ] } ];

afterEach( () => {
	mockedApiFetch.mockReset();
} );

describe( 'Category', () => {
	it( 'renders only the categories used by the base patterns', async () => {
		mockedApiFetch.mockResolvedValue( {
			categories: [
				{ name: 'featured', label: 'Featured' },
				{ name: 'unused', label: 'Unused' },
			],
		} );

		render(
			<Category
				activeCategory="All"
				setActiveCategory={ vi.fn() }
				isOpen
				basePatterns={ BASE_PATTERNS }
			/>
		);

		await waitFor( () =>
			expect( screen.getByText( 'Featured' ) ).toBeInTheDocument()
		);
		expect( screen.queryByText( 'Unused' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'All' ) ).toBeInTheDocument();
	} );

	it( 'shows the empty message when there are no base patterns', async () => {
		render(
			<Category
				activeCategory="All"
				setActiveCategory={ vi.fn() }
				isOpen
				basePatterns={ [] }
			/>
		);

		await waitFor( () =>
			expect(
				screen.getByText( 'No categories found' )
			).toBeInTheDocument()
		);
		expect( mockedApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'reports an error when the categories request fails', async () => {
		mockedApiFetch.mockRejectedValue( new Error( 'boom' ) );

		render(
			<Category
				activeCategory="All"
				setActiveCategory={ vi.fn() }
				isOpen
				basePatterns={ BASE_PATTERNS }
			/>
		);

		await waitFor( () =>
			expect(
				screen.getByText( 'Error fetching pattern categories' )
			).toBeInTheDocument()
		);
	} );

	it( 'does not fetch while closed', () => {
		render(
			<Category
				activeCategory="All"
				setActiveCategory={ vi.fn() }
				isOpen={ false }
				basePatterns={ BASE_PATTERNS }
			/>
		);

		expect( mockedApiFetch ).not.toHaveBeenCalled();
	} );
} );
