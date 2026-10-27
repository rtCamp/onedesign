import { describe, expect, it, type MockedFunction } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import NetworkSettingsPage from '@/admin/network-settings/page';
import type { OneDesignMultisite } from '@/types/global';
import { findNotice } from './library-routes';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

const governingSite: OneDesignMultisite = {
	id: '1',
	name: 'Design HQ',
	url: 'https://example.com/',
	siteType: 'governing-site',
	canAddAsBrandSite: false,
	manageUrl: 'https://example.com/wp-admin/admin.php?page=onedesign-settings',
};

const salesSite: OneDesignMultisite = {
	id: '2',
	name: 'Sales & Marketing',
	url: 'https://example.com/sales/',
	siteType: '',
	canAddAsBrandSite: true,
	manageUrl:
		'https://example.com/sales/wp-admin/admin.php?page=onedesign-settings',
};

const unconnectedBrand: OneDesignMultisite = {
	id: '3',
	name: 'Network Brand',
	url: 'https://example.com/network-brand/',
	siteType: 'brand-site',
	canAddAsBrandSite: true,
	manageUrl:
		'https://example.com/network-brand/wp-admin/admin.php?page=onedesign-settings',
};

const connectedBrand: OneDesignMultisite = {
	id: '4',
	name: 'Connected Brand',
	url: 'https://example.com/connected-brand/',
	siteType: 'brand-site',
	canAddAsBrandSite: false,
	manageUrl:
		'https://example.com/connected-brand/wp-admin/admin.php?page=onedesign-settings',
};

const supportSite: OneDesignMultisite = {
	id: '5',
	name: 'Support',
	url: 'https://example.com/support/',
	siteType: '',
	canAddAsBrandSite: true,
	manageUrl:
		'https://example.com/support/wp-admin/admin.php?page=onedesign-settings',
};

// The network's sites, as the server localizes them on Network Admin > Settings > OneDesign.
const renderScreen = ( multisites: OneDesignMultisite[] ) => {
	window.OneDesignSettings.multisites = multisites;
	render( <NetworkSettingsPage /> );
};

const checkbox = ( site: OneDesignMultisite ) =>
	screen.queryByRole( 'checkbox', {
		name: `${ site.name } ( ${ site.url } )`,
	} );

const addGoverningSiteButton = () =>
	screen.getByRole( 'button', { name: 'Add a Governing Site' } );

const addBrandSitesButton = () =>
	screen.getByRole( 'button', { name: 'Add Brand Sites' } );

const governingSiteModal = () =>
	screen.queryByRole( 'dialog', { name: 'Add a Governing Site' } );

const brandSitesModal = () =>
	screen.queryByRole( 'dialog', { name: 'Select Sites From This Network' } );

const makeGoverning = ( site: OneDesignMultisite ) => {
	fireEvent.change( screen.getByLabelText( 'Site' ), {
		target: { value: site.id },
	} );
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Make Governing Site' } )
	);
};

const addSites = ( sites: OneDesignMultisite[] ) => {
	fireEvent.click( addBrandSitesButton() );
	fireEvent.change( screen.getByLabelText( 'Governing Site' ), {
		target: { value: governingSite.id },
	} );
	sites.forEach( ( site ) => fireEvent.click( checkbox( site )! ) );
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Add Selected Sites' } )
	);
};

// Each site's name, URL, type and action, as the table shows them.
const tableRows = () =>
	screen
		.getAllByRole( 'row' )
		.slice( 1 )
		.map( ( row ) =>
			within( row )
				.getAllByRole( 'cell' )
				.map( ( cell ) => cell.textContent )
		);

const row = ( site: OneDesignMultisite, type: string ): ( string | null )[] => [
	site.name,
	site.url,
	type,
	'Manage Site',
];

describe( 'network settings screen', () => {
	it( 'sets up the first governing site inline while no site has a type', () => {
		renderScreen( [ salesSite ] );

		expect(
			screen.getByRole( 'heading', { name: 'Add a Governing Site' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'option', { name: salesSite.name } )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Add Brand Sites' } )
		).not.toBeInTheDocument();
	} );

	it( 'lists every site on the network with its type and a link to manage it, once a site has a type', () => {
		renderScreen( [
			governingSite,
			salesSite,
			unconnectedBrand,
			connectedBrand,
		] );

		expect(
			screen.queryByRole( 'heading', { name: 'Add a Governing Site' } )
		).not.toBeInTheDocument();
		expect( addGoverningSiteButton() ).toBeEnabled();
		expect( addBrandSitesButton() ).toBeEnabled();
		expect( tableRows() ).toEqual( [
			row( governingSite, 'Governing Site' ),
			row( salesSite, 'Not set' ),
			row( unconnectedBrand, 'Brand Site' ),
			row( connectedBrand, 'Brand Site' ),
		] );
		expect(
			screen
				.getAllByRole( 'link', { name: 'Manage Site' } )
				.map( ( link ) => link.getAttribute( 'href' ) )
		).toEqual( [
			governingSite.manageUrl,
			salesSite.manageUrl,
			unconnectedBrand.manageUrl,
			connectedBrand.manageUrl,
		] );
	} );

	it( 'disables adding a governing site once every site has a type', () => {
		renderScreen( [ governingSite, unconnectedBrand ] );

		expect( addGoverningSiteButton() ).toBeDisabled();
		expect( addBrandSitesButton() ).toBeEnabled();
	} );

	it( 'disables adding brand sites without a governing site to add them to', () => {
		renderScreen( [ salesSite, unconnectedBrand ] );

		expect( addBrandSitesButton() ).toBeDisabled();
		expect( addGoverningSiteButton() ).toBeEnabled();
	} );

	it( 'disables adding brand sites when no site can be added as one', () => {
		renderScreen( [ governingSite, connectedBrand ] );

		expect( addBrandSitesButton() ).toBeDisabled();
	} );

	it( 'adds the picked sites to the picked governing site, and updates the table and buttons in place', async () => {
		mockedApiFetch.mockResolvedValueOnce( { shared_sites: [] } );

		renderScreen( [ governingSite, salesSite, unconnectedBrand ] );
		addSites( [ salesSite, unconnectedBrand ] );

		expect(
			await findNotice(
				'Sales & Marketing, Network Brand are now brand sites.'
			)
		).toBeInTheDocument();
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/onedesign/v1/multisite/brand-sites',
			method: 'POST',
			data: { governing_site_id: 1, site_ids: [ 2, 3 ] },
		} );
		expect( brandSitesModal() ).not.toBeInTheDocument();
		expect( tableRows() ).toEqual( [
			row( governingSite, 'Governing Site' ),
			row( salesSite, 'Brand Site' ),
			row( unconnectedBrand, 'Brand Site' ),
		] );
		// Every site has a type now, and none is left to add.
		expect( addGoverningSiteButton() ).toBeDisabled();
		expect( addBrandSitesButton() ).toBeDisabled();
	} );

	it( "shows the server's message in the open modal when adding sites fails, and keeps the selection", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_cannot_be_brand',
			message: "Site 3 can't be added as a brand site.",
			data: { status: 400 },
		} );

		renderScreen( [ governingSite, unconnectedBrand ] );
		addSites( [ unconnectedBrand ] );

		const notice = await findNotice(
			"Site 3 can't be added as a brand site."
		);
		expect( brandSitesModal() ).toContainElement( notice );
		expect( screen.getByLabelText( 'Governing Site' ) ).toHaveValue(
			governingSite.id
		);
		expect( checkbox( unconnectedBrand ) ).toBeChecked();
		expect(
			screen.getByRole( 'button', { name: 'Add Selected Sites' } )
		).toBeEnabled();
	} );

	it( 'makes the first governing site inline, then lists the sites and picks it for adding brand sites', async () => {
		mockedApiFetch.mockResolvedValueOnce( { shared_sites: [] } );

		renderScreen( [ salesSite, supportSite ] );
		makeGoverning( salesSite );

		expect(
			await findNotice( 'Sales & Marketing is now a governing site.' )
		).toBeInTheDocument();
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/onedesign/v1/multisite/governing-sites',
			method: 'POST',
			data: { site_id: 2 },
		} );
		expect(
			screen.queryByRole( 'heading', { name: 'Add a Governing Site' } )
		).not.toBeInTheDocument();
		expect( tableRows() ).toEqual( [
			row( salesSite, 'Governing Site' ),
			row( supportSite, 'Not set' ),
		] );
		expect( addGoverningSiteButton() ).toBeEnabled();

		fireEvent.click( addBrandSitesButton() );
		expect( screen.getByLabelText( 'Governing Site' ) ).toHaveValue(
			salesSite.id
		);
		expect( checkbox( salesSite ) ).not.toBeInTheDocument();
		expect( checkbox( supportSite ) ).toBeInTheDocument();
	} );

	it( 'makes a governing site from its modal, and updates the table and buttons in place', async () => {
		mockedApiFetch.mockResolvedValueOnce( { shared_sites: [] } );

		renderScreen( [ salesSite, supportSite, connectedBrand ] );
		expect( addBrandSitesButton() ).toBeDisabled();

		fireEvent.click( addGoverningSiteButton() );
		// A brand site's type can't change, so it can't become a governing site.
		expect(
			screen.queryByRole( 'option', { name: connectedBrand.name } )
		).not.toBeInTheDocument();
		makeGoverning( salesSite );

		expect(
			await findNotice( 'Sales & Marketing is now a governing site.' )
		).toBeInTheDocument();
		expect( governingSiteModal() ).not.toBeInTheDocument();
		expect( tableRows() ).toEqual( [
			row( salesSite, 'Governing Site' ),
			row( supportSite, 'Not set' ),
			row( connectedBrand, 'Brand Site' ),
		] );
		expect( addGoverningSiteButton() ).toBeEnabled();
		expect( addBrandSitesButton() ).toBeEnabled();
	} );

	it( "shows the server's message in the open modal when a site isn't made a governing site, and keeps the selection", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_has_type',
			message: 'Site 2 already has a OneDesign site type.',
			data: { status: 400 },
		} );

		renderScreen( [ governingSite, salesSite ] );
		fireEvent.click( addGoverningSiteButton() );
		makeGoverning( salesSite );

		const notice = await findNotice(
			'Site 2 already has a OneDesign site type.'
		);
		expect( governingSiteModal() ).toContainElement( notice );
		expect( screen.getByLabelText( 'Site' ) ).toHaveValue( salesSite.id );
		expect(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		).toBeEnabled();
	} );

	it( "shows the server's message when the first governing site isn't made", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_has_type',
			message: 'Site 2 already has a OneDesign site type.',
			data: { status: 400 },
		} );

		renderScreen( [ salesSite ] );
		makeGoverning( salesSite );

		expect(
			await findNotice( 'Site 2 already has a OneDesign site type.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		).toBeEnabled();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );
} );
