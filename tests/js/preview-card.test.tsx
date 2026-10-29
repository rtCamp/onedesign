import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { registerBlockType } from '@wordpress/blocks';
import PreviewCard from '@/components/preview-card';

// The block editor's own packages don't load in jsdom.
vi.mock( '@wordpress/block-editor', () => ( {
	BlockPreview: ( { blocks }: { blocks: Array< { name: string } > } ) => (
		<div data-testid="preview">
			{ blocks.map( ( block ) => block.name ).join( ',' ) }
		</div>
	),
} ) );

registerBlockType( 'onedesign-test/banner', {
	title: 'Banner',
	category: 'text',
	edit: () => null,
	save: () => null,
} );

const hero = {
	key: 'theme/hero',
	title: 'Hero',
	content: '<!-- wp:onedesign-test/banner /-->',
};

describe( 'PreviewCard', () => {
	it( "previews the item's blocks and selects it by its key", () => {
		const onToggle = vi.fn();
		render(
			<PreviewCard
				item={ hero }
				isSelected={ false }
				onToggle={ onToggle }
			/>
		);

		expect( screen.getByTestId( 'preview' ) ).toHaveTextContent(
			'onedesign-test/banner'
		);
		fireEvent.click( screen.getByRole( 'checkbox', { name: 'Hero' } ) );
		expect( onToggle ).toHaveBeenCalledWith( 'theme/hero' );
	} );

	it( 'shows the category labels', () => {
		render(
			<PreviewCard
				item={ {
					...hero,
					categoryLabels: {
						banner: 'Banners',
						'lab-demo': 'Lab Demo',
					},
				} }
				isSelected
				onToggle={ vi.fn() }
			/>
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'Hero' } )
		).toBeChecked();
		expect( screen.getByText( /Categories:/ ) ).toHaveTextContent(
			'Categories:BannersLab Demo'
		);
	} );

	it( 'leaves out the categories when there are none', () => {
		render(
			<PreviewCard
				item={ hero }
				isSelected={ false }
				onToggle={ vi.fn() }
			/>
		);

		expect( screen.queryByText( /Categories:/ ) ).not.toBeInTheDocument();
	} );
} );
