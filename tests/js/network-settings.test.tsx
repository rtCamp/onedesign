import { describe, expect, it, type MockedFunction } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
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
};

const salesSite: OneDesignMultisite = {
	id: '2',
	name: 'Sales & Marketing',
	url: 'https://example.com/sales/',
	siteType: '',
};

const unconnectedBrand: OneDesignMultisite = {
	id: '3',
	name: 'Network Brand',
	url: 'https://example.com/network-brand/',
	siteType: 'brand-site',
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

const openPicker = () =>
	fireEvent.click(
		screen.getByRole( 'button', {
			name: 'Add Brand Sites From This Network',
		} )
	);

const addSites = ( sites: OneDesignMultisite[] ) => {
	fireEvent.change( screen.getByLabelText( 'Governing Site' ), {
		target: { value: governingSite.id },
	} );
	openPicker();
	sites.forEach( ( site ) => fireEvent.click( checkbox( site )! ) );
	fireEvent.click(
		screen.getByRole( 'button', { name: 'Add Selected Sites' } )
	);
};

describe( 'network settings screen', () => {
	it( 'adds the picked sites to the picked governing site, and says so once every site is set up', async () => {
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
		// A site that became a brand site can't become a governing site either.
		expect(
			screen.queryByRole( 'button', { name: 'Make Governing Site' } )
		).not.toBeInTheDocument();

		expect(
			screen.queryByRole( 'heading', { name: 'Add Brand Sites' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( 'Every site on this network is already set up.' )
		).toBeInTheDocument();
	} );

	it( 'drops a site picked to become a governing site once it is added as a brand site', async () => {
		const supportSite: OneDesignMultisite = {
			id: '4',
			name: 'Support',
			url: 'https://example.com/support/',
			siteType: '',
		};
		mockedApiFetch.mockResolvedValueOnce( { shared_sites: [] } );

		renderScreen( [ governingSite, salesSite, supportSite ] );
		fireEvent.change( screen.getByLabelText( 'Site' ), {
			target: { value: salesSite.id },
		} );
		addSites( [ salesSite ] );

		expect(
			await findNotice( 'Sales & Marketing is now a brand site.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		).toBeDisabled();
	} );

	it( "shows the server's message when adding sites fails, and keeps the selection", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_cannot_be_brand',
			message: "Site 3 can't be added as a brand site.",
			data: { status: 400 },
		} );

		renderScreen( [ governingSite, unconnectedBrand ] );
		addSites( [ unconnectedBrand ] );

		expect(
			await findNotice( "Site 3 can't be added as a brand site." )
		).toBeInTheDocument();
		expect( checkbox( unconnectedBrand ) ).toBeChecked();
		expect(
			screen.getByRole( 'button', { name: 'Add Selected Sites' } )
		).toBeEnabled();
	} );

	it( 'leads with making a governing site when there is none, then picks it for adding brand sites', async () => {
		renderScreen( [ salesSite, unconnectedBrand ] );

		expect(
			screen.queryByLabelText( 'Governing Site' )
		).not.toBeInTheDocument();
		// A brand site's type can't change, so it can't become a governing site.
		expect(
			screen.queryByRole( 'option', { name: unconnectedBrand.name } )
		).not.toBeInTheDocument();

		mockedApiFetch.mockResolvedValueOnce( { shared_sites: [] } );
		fireEvent.change( screen.getByLabelText( 'Site' ), {
			target: { value: salesSite.id },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		);

		expect(
			await findNotice( 'Sales & Marketing is now a governing site.' )
		).toBeInTheDocument();
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/onedesign/v1/multisite/governing-sites',
			method: 'POST',
			data: { site_id: 2 },
		} );
		expect( screen.getByLabelText( 'Governing Site' ) ).toHaveValue(
			salesSite.id
		);

		openPicker();
		expect( checkbox( salesSite ) ).not.toBeInTheDocument();
	} );

	it( "shows the server's message when a site isn't made a governing site", async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onedesign_site_has_type',
			message: 'Site 2 already has a OneDesign site type.',
			data: { status: 400 },
		} );

		renderScreen( [ salesSite ] );
		fireEvent.change( screen.getByLabelText( 'Site' ), {
			target: { value: salesSite.id },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		);

		expect(
			await findNotice( 'Site 2 already has a OneDesign site type.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Make Governing Site' } )
		).toBeEnabled();
		expect(
			screen.queryByLabelText( 'Governing Site' )
		).not.toBeInTheDocument();
	} );
} );
