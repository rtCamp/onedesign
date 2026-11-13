/**
 * External dependencies
 */
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
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

import DeactivationSettings from '@/components/deactivation-settings';

const mockedApiFetch = apiFetch as MockedFunction< typeof apiFetch >;

describe( 'DeactivationSettings', () => {
	let consoleWarnSpy: MockInstance;

	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.OneDesignSettings = {
			...window.OneDesignSettings,
			deleteDataOnDeactivation: false,
		};
		// Suppress WordPress component deprecation warnings (outside our control)
		consoleWarnSpy = vi
			.spyOn( console, 'warn' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => {
		consoleWarnSpy.mockRestore();
	} );

	it( 'saves the setting when toggled on', async () => {
		const setNotice = vi.fn();
		mockedApiFetch.mockResolvedValueOnce( {
			onedesign_delete_data_on_deactivation: true,
		} );

		render( <DeactivationSettings setNotice={ setNotice } /> );

		const toggle = screen.getByLabelText(
			'Delete plugin data on deactivation'
		);
		expect( toggle ).not.toBeChecked();

		fireEvent.click( toggle );

		await waitFor( () => expect( toggle ).toBeChecked() );
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { onedesign_delete_data_on_deactivation: true },
		} );
		expect( setNotice ).toHaveBeenCalledWith(
			expect.objectContaining( { type: 'success' } )
		);
	} );

	it( 'keeps the saved value and shows an error when saving fails', async () => {
		const setNotice = vi.fn();
		window.OneDesignSettings.deleteDataOnDeactivation = true;
		mockedApiFetch.mockRejectedValueOnce( new Error( 'Forbidden' ) );

		render( <DeactivationSettings setNotice={ setNotice } /> );

		const toggle = screen.getByLabelText(
			'Delete plugin data on deactivation'
		);
		fireEvent.click( toggle );

		await waitFor( () =>
			expect( setNotice ).toHaveBeenCalledWith(
				expect.objectContaining( { type: 'error' } )
			)
		);
		expect( toggle ).toBeChecked();
	} );
} );
