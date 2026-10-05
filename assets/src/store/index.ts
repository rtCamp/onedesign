/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { createReduxStore, register } from '@wordpress/data';

interface State {
	sitePatterns: Record< string, unknown >;
	isLoadingSitePatterns: boolean;
	error: Error | null;
}

type PatternAction =
	| { type: 'SET_SITE_PATTERNS'; sitePatterns: Record< string, unknown > }
	| { type: 'SET_IS_LOADING_SITE_PATTERNS'; isLoading: boolean }
	| { type: 'SET_ERROR'; error: Error | null };

interface FetchPatternsResponse {
	success: boolean;
	patterns?: Record< string, unknown >;
}

/**
 * Pattern Sync Store
 */
const DEFAULT_STATE: State = {
	sitePatterns: {},
	isLoadingSitePatterns: false,
	error: null,
};

const actions = {
	setSitePatterns( sitePatterns: Record< string, unknown > ) {
		return {
			type: 'SET_SITE_PATTERNS' as const,
			sitePatterns,
		};
	},
	setIsLoadingSitePatterns( isLoading: boolean ) {
		return {
			type: 'SET_IS_LOADING_SITE_PATTERNS' as const,
			isLoading,
		};
	},
	setError( error: Error | null ) {
		return {
			type: 'SET_ERROR' as const,
			error,
		};
	},
	*fetchSitePatterns(): Generator<
		PatternAction | Promise< FetchPatternsResponse >,
		void,
		FetchPatternsResponse
	> {
		try {
			yield { type: 'SET_IS_LOADING_SITE_PATTERNS', isLoading: true };
			yield { type: 'SET_ERROR', error: null };

			const response = yield apiFetch< FetchPatternsResponse >( {
				path: `/onedesign/v1/get-all-brand-site-patterns?timestamp=${ Date.now() }`,
			} );

			if ( response.success ) {
				yield {
					type: 'SET_SITE_PATTERNS',
					sitePatterns: response.patterns || {},
				};
			} else {
				yield {
					type: 'SET_ERROR',
					error: new Error( 'Failed to fetch site patterns' ),
				};
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Error fetching site patterns:', error );
			yield {
				type: 'SET_ERROR',
				error:
					error instanceof Error
						? error
						: new Error( String( error ) ),
			};
		} finally {
			yield { type: 'SET_IS_LOADING_SITE_PATTERNS', isLoading: false };
		}
	},
};

const reducer = (
	state: State = DEFAULT_STATE,
	action: PatternAction
): State => {
	switch ( action.type ) {
		case 'SET_SITE_PATTERNS':
			return {
				...state,
				sitePatterns: action.sitePatterns,
			};
		case 'SET_IS_LOADING_SITE_PATTERNS':
			return {
				...state,
				isLoadingSitePatterns: action.isLoading,
			};
		case 'SET_ERROR':
			return {
				...state,
				error: action.error,
			};
		default:
			return state;
	}
};

const selectors = {
	getSitePatterns( state: State ) {
		return state.sitePatterns;
	},
	isLoadingSitePatterns( state: State ) {
		return state.isLoadingSitePatterns;
	},
	getError( state: State ) {
		return state.error;
	},
};

const store = createReduxStore( 'onedesign/site-patterns', {
	reducer,
	actions,
	selectors,
} );

register( store );

// Named exports of the store internals for unit testing.
export { actions, DEFAULT_STATE, reducer, selectors, store };
export type { FetchPatternsResponse, PatternAction, State };

export default store;
