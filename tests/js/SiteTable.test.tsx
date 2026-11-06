import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import SiteTable from '@/components/SiteTable';

const sites = [
	{
		name: 'Brand One',
		url: 'https://brand-one.example.com/',
	},
];

describe( 'SiteTable', () => {
	it( 'renders an empty state when there are no brand sites', () => {
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
			screen.getByText( 'No Brand Sites found.' )
		).toBeInTheDocument();
	} );

	it( 'opens the add modal when the add button is clicked', () => {
		const setShowModal = vi.fn();

		render(
			<SiteTable
				sites={ [] }
				onEdit={ vi.fn() }
				onDelete={ vi.fn() }
				setFormData={ vi.fn() }
				setShowModal={ setShowModal }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Add Brand Site' } )
		);

		expect( setShowModal ).toHaveBeenCalledWith( true );
	} );

	it( 'passes the selected site to edit handlers', () => {
		const onEdit = vi.fn();
		const setFormData = vi.fn();
		const setShowModal = vi.fn();

		render(
			<SiteTable
				sites={ sites }
				onEdit={ onEdit }
				onDelete={ vi.fn() }
				setFormData={ setFormData }
				setShowModal={ setShowModal }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Edit' } ) );

		// The key field starts empty, since the stored key never reaches the browser.
		expect( setFormData ).toHaveBeenCalledWith( {
			...sites[ 0 ],
			api_key: '',
		} );
		expect( onEdit ).toHaveBeenCalledWith( 0 );
		expect( setShowModal ).toHaveBeenCalledWith( true );
	} );

	it( 'confirms deletion before calling onDelete', () => {
		const onDelete = vi.fn();

		render(
			<SiteTable
				sites={ sites }
				onEdit={ vi.fn() }
				onDelete={ onDelete }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete' } ) );

		const dialog = screen.getByRole( 'dialog', {
			name: 'Delete Brand Site',
		} );
		expect(
			within( dialog ).getByText(
				'Are you sure you want to delete this Brand Site? This action cannot be undone.'
			)
		).toBeInTheDocument();

		fireEvent.click(
			within( dialog ).getByRole( 'button', { name: 'Delete' } )
		);

		expect( onDelete ).toHaveBeenCalledWith( 0 );
	} );

	it( 'lets the user cancel deletion', () => {
		const onDelete = vi.fn();

		render(
			<SiteTable
				sites={ sites }
				onEdit={ vi.fn() }
				onDelete={ onDelete }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ vi.fn() }
				setNotice={ vi.fn() }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete' } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect( onDelete ).not.toHaveBeenCalled();
		expect(
			screen.queryByRole( 'dialog', { name: 'Delete Brand Site' } )
		).not.toBeInTheDocument();
	} );
} );
