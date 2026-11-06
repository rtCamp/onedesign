import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	type MockedFunction,
	type MockInstance,
	vi,
} from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SiteTypeSettings from '@/components/SiteTypeSettings';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

describe( 'SiteTypeSettings', () => {
	let consoleWarnSpy: MockInstance;

	beforeEach( () => {
		mockedApiFetch.mockReset();
		// Suppress WordPress component deprecation warnings (outside our control)
		consoleWarnSpy = vi
			.spyOn( console, 'warn' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => {
		consoleWarnSpy.mockRestore();
	} );

	it( 'saves the chosen site type and reloads the screen', async () => {
		const consoleErrorSpy = vi
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		mockedApiFetch.mockResolvedValueOnce( {
			onedesign_site_type: 'governing-site',
		} );

		render( <SiteTypeSettings /> );

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'governing-site' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Select Current Site Type' } )
		);

		// jsdom can't reload, so the reload shows up as its "not implemented" error.
		await waitFor( () => {
			expect( consoleErrorSpy ).toHaveBeenCalledWith(
				expect.objectContaining( {
					message: expect.stringContaining( 'navigation' ),
				} )
			);
		} );
		consoleErrorSpy.mockRestore();
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { onedesign_site_type: 'governing-site' },
		} );
	} );

	it( 'shows an error notice when saving fails', async () => {
		mockedApiFetch.mockRejectedValueOnce( new Error( 'save failed' ) );

		render( <SiteTypeSettings /> );

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Select Current Site Type' } )
		);

		// WordPress renders notices in both accessibility region and visible content
		expect( await screen.findAllByText( 'save failed' ) ).toHaveLength( 2 );
	} );
} );
