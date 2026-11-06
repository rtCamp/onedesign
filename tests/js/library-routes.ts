import { screen } from '@testing-library/react';
import { vi } from 'vitest';
import apiFetch from '@wordpress/api-fetch';

export type Handler = ( data?: unknown ) => unknown;

/**
 * Answers each apiFetch request by method and path, like the REST routes would, and fails any other request.
 *
 * @param handlers - The routes, keyed by "METHOD /path".
 */
export const routes = ( handlers: Record< string, Handler > ) => {
	vi.mocked( apiFetch ).mockImplementation( ( async ( options: {
		path: string;
		method?: string;
		data?: unknown;
	} ) => {
		const handler =
			handlers[ `${ options.method ?? 'GET' } ${ options.path }` ];
		if ( ! handler ) {
			throw new Error( `Unexpected request ${ options.path }` );
		}
		return handler( options.data );
	} ) as unknown as typeof apiFetch );
};

/**
 * A route that fails like a WP_Error does.
 *
 * @param message - The error's message.
 * @param status  - The error's status.
 */
export const restError =
	( message: string, status = 500 ): Handler =>
	() => {
		throw { code: 'onedesign_error', message, data: { status } };
	};

/**
 * Finds a notice by its text. Notices also echo their text to a screen reader live region, so this looks inside the notice itself.
 *
 * @param text - The notice's text.
 */
export const findNotice = ( text: string ) =>
	screen.findByText( text, {
		selector: '.components-notice__content, .components-notice__content *',
	} );
