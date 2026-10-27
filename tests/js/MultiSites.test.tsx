import { describe, expect, it, type MockedFunction, vi } from 'vitest';
import { useState } from '@wordpress/element';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteTable from '@/components/SiteTable';
import type { BrandSite, NoticeType } from '@/admin/settings/page';
import { findNotice } from './library-routes';

// The network sites a network admin can add, as the governing site's settings screen localizes them before the component loads.
vi.hoisted( () => {
	window.OneDesignSettings.multisites = [
		{
			id: '2',
			name: 'Network Brand',
			url: 'https://example.com/network-brand/',
			siteType: '',
			canAddAsBrandSite: true,
			manageUrl:
				'https://example.com/network-brand/wp-admin/admin.php?page=onedesign-settings',
		},
	];
	window.OneDesignSettings.currentSiteId = '1';
} );

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const brandOne: BrandSite = {
	id: 'brand-1',
	name: 'Brand One',
	url: 'https://brand-one.example.com/',
};

const networkBrand: BrandSite = {
	id: '2',
	name: 'Network Brand',
	url: 'https://example.com/network-brand/',
};

// Holds the brand sites and the notice the way the settings page does.
const SettingsHarness = ( { initial }: { initial: BrandSite[] } ) => {
	const [ brandSites, setBrandSites ] = useState( initial );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	return (
		<>
			<SiteTable
				sites={ brandSites }
				onEdit={ vi.fn() }
				onDelete={ vi.fn() }
				setFormData={ vi.fn() }
				setShowModal={ vi.fn() }
				setSites={ setBrandSites }
				setNotice={ setNotice }
			/>
			{ notice && <p role="status">{ notice.message }</p> }
			<ul>
				{ brandSites.map( ( site ) => (
					<li key={ site.url }>{ site.name }</li>
				) ) }
			</ul>
		</>
	);
};

const openPicker = () =>
	fireEvent.click(
		screen.getByRole( 'button', {
			name: 'Add Brand Sites From This Network',
		} )
	);

const addNetworkBrand = async () => {
	openPicker();
	fireEvent.click(
		await screen.findByRole( 'checkbox', {
			name: 'Network Brand ( https://example.com/network-brand/ )',
		} )
	);
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Add Selected Sites' } )
	);
};

const picker = () =>
	screen.queryByRole( 'dialog', { name: 'Select Sites From This Network' } );

// An open modal hides the rest of the page from the accessibility tree.
const brandSiteNames = () =>
	screen
		.getAllByRole( 'listitem', { hidden: true } )
		.map( ( item ) => item.textContent );

describe( 'MultiSites', () => {
	it( 'hides the picker when every network site is already a brand site', () => {
		render( <SettingsHarness initial={ [ networkBrand ] } /> );

		expect(
			screen.queryByRole( 'button', {
				name: 'Add Brand Sites From This Network',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'forgets the picked sites when the picker is cancelled', () => {
		render( <SettingsHarness initial={ [ brandOne ] } /> );

		openPicker();
		fireEvent.click(
			screen.getByRole( 'checkbox', {
				name: 'Network Brand ( https://example.com/network-brand/ )',
			} )
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		openPicker();

		expect(
			screen.getByRole( 'checkbox', {
				name: 'Network Brand ( https://example.com/network-brand/ )',
			} )
		).not.toBeChecked();
	} );

	it( 'shows the brand sites the server stored, and names the site it added', async () => {
		// Another admin added Brand Two meanwhile, so the server's list is the one to show.
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [
				brandOne,
				{
					id: 'brand-2',
					name: 'Brand Two',
					url: 'https://brand-two.example.com/',
				},
				networkBrand,
			],
		} );

		render( <SettingsHarness initial={ [ brandOne ] } /> );
		await addNetworkBrand();

		await waitFor( () => {
			expect( brandSiteNames() ).toEqual( [
				'Brand One',
				'Brand Two',
				'Network Brand',
			] );
		} );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Network Brand is now a brand site.'
		);
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/onedesign/v1/multisite/brand-sites',
			method: 'POST',
			data: { governing_site_id: 1, site_ids: [ 2 ] },
		} );
		expect( picker() ).not.toBeInTheDocument();
		// That was the network's last site to add.
		expect(
			screen.queryByRole( 'button', {
				name: 'Add Brand Sites From This Network',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'keeps the picker open and busy until the sites are added', async () => {
		let respond: ( value: {
			shared_sites: BrandSite[];
		} ) => void = () => {};
		mockedApiFetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				respond = resolve;
			} )
		);

		render( <SettingsHarness initial={ [ brandOne ] } /> );
		await addNetworkBrand();

		expect( picker() ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Add Selected Sites' } )
		).toHaveClass( 'is-busy' );

		respond( { shared_sites: [ brandOne, networkBrand ] } );

		await waitFor( () => expect( picker() ).not.toBeInTheDocument() );
		expect( brandSiteNames() ).toEqual( [ 'Brand One', 'Network Brand' ] );
	} );

	it( "shows the server's message in the open picker when adding sites fails, and keeps the list", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'rest_forbidden',
			message: 'Sorry, you are not allowed to do that.',
			data: { status: 403 },
		} );

		render( <SettingsHarness initial={ [ brandOne ] } /> );
		await addNetworkBrand();

		const notice = await findNotice(
			'Sorry, you are not allowed to do that.'
		);
		expect( picker() ).toContainElement( notice );
		expect( brandSiteNames() ).toEqual( [ 'Brand One' ] );
	} );
} );
