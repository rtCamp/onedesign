/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import MemoizedTemplatePreview from '@/admin/templates/components/MemoizedTemplatePreview';

jest.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: () => <div data-testid="block-preview" />,
} ) );
jest.mock( '@wordpress/blocks', () => ( {
	parse: jest.fn( () => [] ),
} ) );

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
				onSelect={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'My Template' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'block-preview' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Alpha' ) ).toBeInTheDocument();
	} );

	it( 'calls onSelect when the card is activated', () => {
		const onSelect = jest.fn();
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
			<MemoizedTemplatePreview
				template={ TEMPLATE }
				isSelected={ false }
				onSelect={ jest.fn() }
				providerSite="Brand A"
			/>
		);
		expect( screen.getByText( 'Brand A' ) ).toBeInTheDocument();
	} );
} );
