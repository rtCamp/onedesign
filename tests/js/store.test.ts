/**
 * External dependencies
 */
import { DEFAULT_STATE, actions, reducer, selectors } from '@/store';
import type { PatternAction } from '@/store';

// The store's async resolver calls apiFetch; mock it so the generator can be driven without the network.
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

describe( 'store reducer', () => {
	it( 'returns the default state for an unknown action', () => {
		expect(
			reducer( undefined, {
				type: '@@INIT',
			} as unknown as PatternAction )
		).toEqual( DEFAULT_STATE );
	} );

	it( 'handles SET_SITE_PATTERNS', () => {
		const next = reducer( DEFAULT_STATE, {
			type: 'SET_SITE_PATTERNS',
			sitePatterns: { 1: [ 'a' ] },
		} );
		expect( next.sitePatterns ).toEqual( { 1: [ 'a' ] } );
	} );

	it( 'handles SET_IS_LOADING_SITE_PATTERNS', () => {
		const next = reducer( DEFAULT_STATE, {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: true,
		} );
		expect( next.isLoadingSitePatterns ).toBe( true );
	} );

	it( 'handles SET_ERROR', () => {
		const error = new Error( 'boom' );
		const next = reducer( DEFAULT_STATE, { type: 'SET_ERROR', error } );
		expect( next.error ).toBe( error );
	} );

	it( 'does not mutate the previous state', () => {
		const next = reducer( DEFAULT_STATE, {
			type: 'SET_SITE_PATTERNS',
			sitePatterns: { 1: [ 'a' ] },
		} );
		expect( next ).not.toBe( DEFAULT_STATE );
		expect( DEFAULT_STATE.sitePatterns ).toEqual( {} );
	} );
} );

describe( 'store actions', () => {
	it( 'setSitePatterns creates the expected action', () => {
		expect( actions.setSitePatterns( { 1: [] } ) ).toEqual( {
			type: 'SET_SITE_PATTERNS',
			sitePatterns: { 1: [] },
		} );
	} );

	it( 'setIsLoadingSitePatterns creates the expected action', () => {
		expect( actions.setIsLoadingSitePatterns( true ) ).toEqual( {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: true,
		} );
	} );

	it( 'setError creates the expected action', () => {
		const error = new Error( 'x' );
		expect( actions.setError( error ) ).toEqual( {
			type: 'SET_ERROR',
			error,
		} );
	} );
} );

describe( 'store selectors', () => {
	const state = {
		sitePatterns: { 1: [ 'a' ] },
		isLoadingSitePatterns: true,
		error: new Error( 'e' ),
	};

	it( 'getSitePatterns returns sitePatterns', () => {
		expect( selectors.getSitePatterns( state ) ).toBe( state.sitePatterns );
	} );

	it( 'isLoadingSitePatterns returns the loading flag', () => {
		expect( selectors.isLoadingSitePatterns( state ) ).toBe( true );
	} );

	it( 'getError returns the error', () => {
		expect( selectors.getError( state ) ).toBe( state.error );
	} );
} );

describe( 'fetchSitePatterns generator', () => {
	it( 'dispatches patterns on a successful response', () => {
		const gen = actions.fetchSitePatterns();

		expect( gen.next().value ).toEqual( {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: true,
		} );
		expect( gen.next().value ).toEqual( {
			type: 'SET_ERROR',
			error: null,
		} );

		// The apiFetch call is yielded; the resolved response is injected back.
		gen.next();
		const step = gen.next( { success: true, patterns: { 1: [ 'a' ] } } );
		expect( step.value ).toEqual( {
			type: 'SET_SITE_PATTERNS',
			sitePatterns: { 1: [ 'a' ] },
		} );

		expect( gen.next().value ).toEqual( {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: false,
		} );
		expect( gen.next().done ).toBe( true );
	} );

	it( 'defaults to an empty patterns object when none are returned', () => {
		const gen = actions.fetchSitePatterns();
		gen.next();
		gen.next();
		gen.next();
		const step = gen.next( { success: true } );
		expect( step.value ).toEqual( {
			type: 'SET_SITE_PATTERNS',
			sitePatterns: {},
		} );
	} );

	it( 'dispatches an error when the response is unsuccessful', () => {
		const gen = actions.fetchSitePatterns();
		gen.next();
		gen.next();
		gen.next();
		const step = gen.next( { success: false } );
		const action = step.value as Extract<
			PatternAction,
			{ type: 'SET_ERROR' }
		>;
		expect( action.type ).toBe( 'SET_ERROR' );
		expect( action.error ).toBeInstanceOf( Error );
		expect( action.error?.message ).toBe( 'Failed to fetch site patterns' );
		// finally block always resets loading.
		expect( gen.next().value ).toEqual( {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: false,
		} );
	} );

	it( 'catches thrown errors and still resets the loading flag', () => {
		const consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
		const gen = actions.fetchSitePatterns();
		gen.next();
		gen.next();
		gen.next(); // yields apiFetch control value

		const thrown = new Error( 'network down' );
		const step = gen.throw( thrown );
		expect( step.value ).toEqual( { type: 'SET_ERROR', error: thrown } );
		expect( gen.next().value ).toEqual( {
			type: 'SET_IS_LOADING_SITE_PATTERNS',
			isLoading: false,
		} );
		expect( consoleError ).toHaveBeenCalled();

		consoleError.mockRestore();
	} );
} );
