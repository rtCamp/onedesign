/**
 * External dependencies
 */
import { renderHook, act, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import useSitesManagement from '@/hooks/useSitesManagement';

const fetchMock = global.fetch as unknown as jest.Mock;

const PROPS = {
	NONCE: 'nonce',
	API_NAMESPACE: 'https://api.test/onedesign/v1',
};
const CONFIGURED_SITES = {
	1: { id: 's1', url: 'https://siteone.test', api_key: 'k1' },
};

/**
 * Route the shared fetch mock by URL: the hook calls `/configured-sites` on
 * mount and `/health-check` per site afterwards.
 */
function routeFetch( healthResponse: unknown, healthRejects = false ): void {
	fetchMock.mockImplementation( ( url: string ) => {
		if ( url.includes( '/configured-sites' ) ) {
			return Promise.resolve( {
				json: () => Promise.resolve( CONFIGURED_SITES ),
			} );
		}
		if ( url.includes( '/health-check' ) ) {
			return healthRejects
				? Promise.reject( new Error( 'unreachable' ) )
				: Promise.resolve( {
						json: () => Promise.resolve( healthResponse ),
				  } );
		}
		return Promise.reject( new Error( `unexpected url: ${ url }` ) );
	} );
}

afterEach( () => {
	fetchMock.mockReset();
} );

describe( 'useSitesManagement', () => {
	it( 'fetches configured sites on mount and runs the health check', async () => {
		routeFetch( { success: true, message: 'ok' } );

		const { result } = renderHook( () => useSitesManagement( PROPS ) );

		await waitFor( () =>
			expect( result.current.siteInfo ).toEqual( CONFIGURED_SITES )
		);
		await waitFor( () =>
			expect( result.current.sitesHealthCheckResult ).toEqual( {
				s1: { success: true, message: 'ok' },
			} )
		);
		expect( result.current.isLoading ).toBe( false );
		expect( result.current.error ).toBeNull();
	} );

	it( 'marks a site unhealthy when the health check reports failure', async () => {
		routeFetch( { success: false, message: 'down' } );

		const { result } = renderHook( () => useSitesManagement( PROPS ) );

		await waitFor( () =>
			expect( result.current.sitesHealthCheckResult ).toEqual( {
				s1: { success: false, message: 'down' },
			} )
		);
	} );

	it( 'reports the site as unreachable when the health-check fetch throws', async () => {
		routeFetch( null, true );

		const { result } = renderHook( () => useSitesManagement( PROPS ) );

		await waitFor( () =>
			expect( result.current.sitesHealthCheckResult ).toEqual( {
				s1: { success: false, message: 'Failed to reach the site.' },
			} )
		);
	} );

	it( 'records an error message when the sites fetch rejects', async () => {
		fetchMock.mockRejectedValue( new Error( 'offline' ) );

		const { result } = renderHook( () => useSitesManagement( PROPS ) );

		await waitFor( () => expect( result.current.error ).toBe( 'offline' ) );
	} );

	it( 'reset clears all managed state', async () => {
		routeFetch( { success: true } );

		const { result } = renderHook( () => useSitesManagement( PROPS ) );

		await waitFor( () =>
			expect( result.current.siteInfo ).toEqual( CONFIGURED_SITES )
		);

		act( () => {
			result.current.reset();
		} );

		expect( result.current.siteInfo ).toEqual( {} );
		expect( result.current.sitesHealthCheckResult ).toEqual( {} );
		expect( result.current.error ).toBeNull();
		expect( result.current.isLoading ).toBe( false );
	} );
} );
