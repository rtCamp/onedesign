import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, screen } from '@testing-library/react';
import type { OneDesignSharedSite } from '@/types/global';

// The whole settings screen, with its real table and modal.
const localizedSettings = window.OneDesignSettings;
const sharedSites: OneDesignSharedSite[] = [
	{
		id: 'brand-1',
		name: 'Brand One',
		url: 'https://brand-one.example.com/',
		logo: '',
		logo_id: 0,
	},
];

describe( 'settings screen', () => {
	beforeEach( () => {
		// WordPress component deprecation warnings aren't ours.
		vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
		window.OneDesignSettings = {
			...localizedSettings,
			siteType: 'governing-site',
			sharedSites,
		};
	} );

	afterEach( () => {
		window.OneDesignSettings = localizedSettings;
	} );

	it( 'shows the site type picker on a site that has no type yet', async () => {
		window.OneDesignSettings = {
			...window.OneDesignSettings,
			siteType: '',
		};
		const target = document.createElement( 'div' );
		target.id = 'onedesign-settings-page';
		document.body.append( target );
		// The entry reads the site type and mounts the page when it loads.
		vi.resetModules();

		await act( async () => {
			await import( '@/admin/settings' );
		} );

		expect(
			await screen.findByRole( 'combobox', { name: 'Site Type' } )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Brand One' ) ).not.toBeInTheDocument();
	} );
} );
