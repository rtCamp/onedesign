import SiteTypeSettings from '@/components/SiteTypeSettings';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
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

	it( "can't save until a site type is chosen", () => {
		render( <SiteTypeSettings /> );

		const button = screen.getByRole( 'button', {
			name: 'Select Current Site Type',
		} );
		expect( button ).toBeDisabled();

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );
		expect( button ).toBeEnabled();

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: '' },
		} );
		expect( button ).toBeDisabled();
		expect( mockedApiFetch ).not.toHaveBeenCalled();
	} );

	it.each( [ 'governing-site', 'brand-site' ] )(
		'saves %s as the site type and reloads the screen',
		async ( siteType ) => {
			const consoleErrorSpy = vi
				.spyOn( console, 'error' )
				.mockImplementation( () => {} );
			mockedApiFetch.mockResolvedValueOnce( {
				onedesign_site_type: siteType,
			} );

			render( <SiteTypeSettings /> );

			fireEvent.change( screen.getByLabelText( 'Site Type' ), {
				target: { value: siteType },
			} );
			fireEvent.click(
				screen.getByRole( 'button', {
					name: 'Select Current Site Type',
				} )
			);

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
				data: { onedesign_site_type: siteType },
			} );
		}
	);

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
