import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import BrandSitePicker, {
	type SiteError,
} from '@/components/brand-site-picker';
import { findNotice } from './library-routes';

const sites = [
	{ id: 'brand-1', name: 'Alpha', url: 'https://alpha.example/' },
	{ id: 'brand-2', name: 'Beta', url: 'https://beta.example/' },
	{ id: 'brand-3', name: 'Gamma', url: 'https://gamma.example/' },
];

const copy = {
	apply: 'Apply Patterns',
	notApplied: "These brand sites didn't get the patterns.",
	allPresent: 'Already has all the selected patterns',
	somePresent: ( present: number, selected: number ) =>
		`Already has ${ present } of the ${ selected } selected patterns`,
	disabledHint: "Some brand sites can't be picked.",
};

// Alpha has both selected patterns, Beta has one of them and Gamma has neither.
const present: Record< string, number > = {
	'brand-1': 2,
	'brand-2': 1,
	'brand-3': 0,
};

const renderPicker = (
	onApply = vi.fn( async (): Promise< SiteError[] > => [] )
) =>
	render(
		<BrandSitePicker
			sites={ sites }
			siteErrors={ {} }
			selectedCount={ 2 }
			countPresent={ ( siteId ) => present[ siteId ] ?? 0 }
			copy={ copy }
			onApply={ onApply }
			onClose={ vi.fn() }
		/>
	);

describe( 'BrandSitePicker', () => {
	it( 'says what each brand site already has, and disables the ones that have everything', () => {
		renderPicker();

		const alpha = screen.getByRole( 'checkbox', { name: /Alpha/ } );
		expect( alpha ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( alpha ).toHaveTextContent(
			'Already has all the selected patterns'
		);
		expect(
			screen.getByRole( 'checkbox', { name: /Beta/ } )
		).toHaveTextContent( 'Already has 1 of the 2 selected patterns' );
		expect(
			screen.getByText( "Some brand sites can't be picked." )
		).toBeInTheDocument();
	} );

	it( 'counts every brand site, including the ones that already have all the selected items', () => {
		render(
			<BrandSitePicker
				sites={ sites }
				siteErrors={ {} }
				selectedCount={ 2 }
				countPresent={ () => 2 }
				copy={ copy }
				onApply={ vi.fn( async () => [] ) }
				onClose={ vi.fn() }
			/>
		);

		expect( screen.getByText( '0 of 3 selected' ) ).toBeInTheDocument();
	} );

	it( 'selects every site that can be picked, and applies to them', async () => {
		const onApply = vi.fn( async () => [] );
		renderPicker( onApply );

		fireEvent.click( screen.getByRole( 'button', { name: 'Select All' } ) );
		expect( screen.getByText( '2 of 3 selected' ) ).toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		expect( onApply ).toHaveBeenCalledWith( [ 'brand-2', 'brand-3' ] );
	} );

	it( 'picks and unpicks a site from the keyboard', () => {
		renderPicker();

		const gamma = screen.getByRole( 'checkbox', { name: /Gamma/ } );
		fireEvent.keyDown( gamma, { key: ' ' } );
		expect( gamma ).toHaveAttribute( 'aria-checked', 'true' );
		fireEvent.keyDown( gamma, { key: 'Enter' } );
		expect( gamma ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	it( "doesn't read a site's name twice for its logo", () => {
		render(
			<BrandSitePicker
				sites={ [
					{
						id: 'brand-1',
						name: 'Alpha',
						url: 'https://alpha.example/',
						logo: 'https://alpha.example/logo.png',
					},
				] }
				siteErrors={ {} }
				selectedCount={ 1 }
				countPresent={ () => 0 }
				copy={ copy }
				onApply={ vi.fn( async () => [] ) }
				onClose={ vi.fn() }
			/>
		);

		const alpha = screen.getByRole( 'checkbox', { name: /Alpha/ } );
		expect( within( alpha ).queryByRole( 'img' ) ).not.toBeInTheDocument();
		expect( within( alpha ).getByRole( 'presentation' ) ).toHaveAttribute(
			'src',
			'https://alpha.example/logo.png'
		);
	} );

	it( 'names each site that did not get the items, with its own message', async () => {
		renderPicker(
			vi.fn( async () => [
				{
					site_id: 'brand-3',
					message: 'Sorry, you are not allowed to do that.',
				},
			] )
		);

		fireEvent.click( screen.getByRole( 'checkbox', { name: /Gamma/ } ) );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply Patterns' } )
		);

		const notice = (
			await findNotice( "These brand sites didn't get the patterns." )
		).closest( '.components-notice' ) as HTMLElement;
		expect( within( notice ).getByText( 'Gamma' ) ).toBeInTheDocument();
		expect(
			within( notice ).getByText(
				'Sorry, you are not allowed to do that.'
			)
		).toBeInTheDocument();
	} );
} );
