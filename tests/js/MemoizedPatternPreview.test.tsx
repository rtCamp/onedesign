/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MemoizedPatternPreview from '@/admin/patterns/components/MemoizedPatternPreview';

jest.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => <div data-testid="block-preview" />,
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	parse: jest.fn( () => [] ),
} ) );

const PATTERN = {
	name: 'p1',
	title: 'My Pattern',
	content: '<!-- wp:paragraph /-->',
	category_labels: { a: 'Alpha' },
};

describe( 'MemoizedPatternPreview', () => {
	it( 'renders the title, block preview, and categories', () => {
		render(
			<MemoizedPatternPreview
				pattern={ PATTERN }
				isSelected={ false }
				onSelect={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'My Pattern' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'block-preview' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Alpha' ) ).toBeInTheDocument();
	} );

	it( 'calls onSelect when the card is activated', () => {
		const onSelect = jest.fn();
		render(
			<MemoizedPatternPreview
				pattern={ PATTERN }
				isSelected={ false }
				onSelect={ onSelect }
			/>
		);
		fireEvent.click( screen.getByText( 'My Pattern' ) );
		expect( onSelect ).toHaveBeenCalledWith( PATTERN );
	} );

	it( 'omits the checkbox when isCheckBoxRequired is false', () => {
		const { container } = render(
			<MemoizedPatternPreview
				pattern={ PATTERN }
				isSelected={ false }
				onSelect={ jest.fn() }
				isCheckBoxRequired={ false }
			/>
		);
		expect(
			container.querySelector( 'input[type="checkbox"]' )
		).not.toBeInTheDocument();
	} );

	it( 'shows provider site info when provided', () => {
		render(
			<MemoizedPatternPreview
				pattern={ PATTERN }
				isSelected={ false }
				onSelect={ jest.fn() }
				providerSite="Brand A"
			/>
		);
		expect( screen.getByText( 'Brand A' ) ).toBeInTheDocument();
	} );
} );
