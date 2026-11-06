import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import SiteTable from '@/components/SiteTable';

describe( 'SiteTable on a network', () => {
	it( "doesn't read a site's name twice for its logo", () => {
		render(
			<SiteTable
				sites={ [
					{
						id: '2',
						name: 'MS Brand A',
						url: 'https://example.com/brand-a/',
						logo: 'https://example.com/brand-a/logo.png',
					},
				] }
				onEdit={ vi.fn() }
				onDelete={ vi.fn() }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		const row = screen.getByRole( 'row', { name: /MS Brand A/ } );
		expect( within( row ).queryByRole( 'img' ) ).not.toBeInTheDocument();
		expect( within( row ).getByRole( 'presentation' ) ).toHaveAttribute(
			'src',
			'https://example.com/brand-a/logo.png'
		);
	} );

	it( 'hides the picker when the network has no sites to add', () => {
		// The server sends none to site admins or when no site can be added.
		render(
			<SiteTable
				sites={ [] }
				onEdit={ vi.fn() }
				onDelete={ vi.fn() }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		expect(
			screen.queryByRole( 'button', {
				name: 'Add Brand Sites From This Network',
			} )
		).not.toBeInTheDocument();
	} );
} );
