/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MemoizedTemplatePreview from '@/admin/templates/components/MemoizedTemplatePreview';

vi.mock( '@wordpress/block-editor' );
vi.mock( '@wordpress/blocks' );

const TEMPLATE = {
	name: 't1',
	title: 'My Template',
	content: '<!-- wp:paragraph /-->',
	category_labels: { a: 'Alpha' },
};

describe( 'MemoizedTemplatePreview', () => {
	it( 'renders the title, block preview, and categories', () => {
		render(
			<MemoizedTemplatePreview
				template={ TEMPLATE }
				isSelected={ false }
				onSelect={ vi.fn() }
			/>
		);
		expect( screen.getByText( 'My Template' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'block-preview' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Alpha' ) ).toBeInTheDocument();
	} );

	it( 'calls onSelect when the card is activated', () => {
		const onSelect = vi.fn();
		render(
			<MemoizedTemplatePreview
				template={ TEMPLATE }
				isSelected={ false }
				onSelect={ onSelect }
			/>
		);
		fireEvent.click( screen.getByText( 'My Template' ) );
		expect( onSelect ).toHaveBeenCalledWith( TEMPLATE );
	} );

	it( 'omits the checkbox when isCheckBoxRequired is false', () => {
		const { container } = render(
			<MemoizedTemplatePreview
				template={ TEMPLATE }
				isSelected={ false }
				onSelect={ vi.fn() }
				isCheckBoxRequired={ false }
			/>
		);
		expect(
			container.querySelector( 'input[type="checkbox"]' )
		).not.toBeInTheDocument();
	} );

	it( 'shows provider site info when provided', () => {
		render(
			<MemoizedTemplatePreview
				template={ TEMPLATE }
				isSelected={ false }
				onSelect={ vi.fn() }
				providerSite="Brand A"
			/>
		);
		expect( screen.getByText( 'Brand A' ) ).toBeInTheDocument();
	} );
} );
