/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

interface SiteInfo {
	id?: string | number;
	url?: string;
	api_key?: string;
}

interface HealthCheckResult {
	success: boolean;
	message?: string;
	[ key: string ]: unknown;
}

type SitesHealthCheckResult = Record< string, HealthCheckResult >;

interface UseSitesManagementProps {
	NONCE: string;
	API_NAMESPACE: string;
}

/**
 * Custom hook for managing sites info and health check state.
 *
 * @param props               - Properties for authentication.
 * @param props.NONCE         - Nonce for secure API requests.
 * @param props.API_NAMESPACE - API namespace for REST endpoints.
 *
 * @return State and methods for site management.
 */
const useSitesManagement = ( {
	NONCE,
	API_NAMESPACE,
}: UseSitesManagementProps ) => {
	const [ siteInfo, setSiteInfo ] = useState< Record< string, SiteInfo > >(
		{}
	);
	const [ sitesHealthCheckResult, setSitesHealthCheckResult ] = useState<
		SitesHealthCheckResult | undefined
	>( undefined );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );
	const [ isInitialized, setIsInitialized ] = useState( false );

	const performHealthCheckOnSites = useCallback( async () => {
		setError( null );

		try {
			for ( const siteId of Object.keys( siteInfo ) ) {
				const site = siteInfo[ siteId ];
				const siteUrl = site?.url;
				const siteApiKey = site?.api_key;

				/**
				 * `site.id` (not the `siteInfo` object key) is the identifier consumers key their
				 * own lookups by, so skip a site without one rather than collapsing several into a
				 * single "undefined" entry.
				 */
				if ( site?.id === undefined ) {
					continue;
				}
				const resultKey = String( site.id );

				if ( siteUrl ) {
					try {
						const response = await fetch(
							`${ siteUrl }/wp-json/onedesign/v1/health-check?timestamp=${ Date.now() }`,
							{
								method: 'GET',
								headers: {
									'Content-Type': 'application/json',
									'X-OneDesign-Token': siteApiKey || '',
									'X-OneDesign-Source':
										'Patterns-Templates-Sharing',
								},
							}
						);
						const data =
							( await response.json() ) as HealthCheckResult;

						if ( ! data.success ) {
							setSitesHealthCheckResult( ( prevResults ) => ( {
								...( prevResults || {} ),
								[ resultKey ]: {
									success: false,
									message:
										data.message ||
										__(
											'Health check failed.',
											'onedesign'
										),
								},
							} ) );
							continue;
						}

						setSitesHealthCheckResult( ( prevResults ) => ( {
							...( prevResults || {} ),
							[ resultKey ]: data.success
								? data
								: {
										success: false,
										message:
											data.message ||
											__(
												'Health check failed.',
												'onedesign'
											),
								  },
						} ) );
					} catch {
						setSitesHealthCheckResult( ( prevResults ) => ( {
							...( prevResults || {} ),
							[ resultKey ]: {
								success: false,
								message: __(
									'Failed to reach the site.',
									'onedesign'
								),
							},
						} ) );
					}
				}
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : String( err ) );
		} finally {
			setIsLoading( false );
		}
	}, [ siteInfo ] );

	const fetchBrandSitesInfo = useCallback( async () => {
		setError( null );

		try {
			const response = await fetch(
				`${ API_NAMESPACE }/configured-sites?timestamp=${ Date.now() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);
			const data = ( await response.json() ) as Record<
				string,
				SiteInfo
			>;
			const sites = data || {};
			setSiteInfo(sites);

			if (Object.keys(sites).length === 0) {
				setIsLoading(false);
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : String( err ) );
			setIsLoading(false);
		}
	}, [ API_NAMESPACE, NONCE ] );

	useEffect( () => {
		fetchBrandSitesInfo();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		if ( ! isInitialized && Object.keys( siteInfo ).length > 0 ) {
			performHealthCheckOnSites();
			setIsInitialized( true );
		}
	}, [ siteInfo, isInitialized, performHealthCheckOnSites ] );

	const reset = useCallback( () => {
		setSiteInfo( {} );
		setSitesHealthCheckResult( {} );
		setError( null );
		setIsLoading( false );
		setIsInitialized( false );
	}, [] );

	return {
		siteInfo,
		sitesHealthCheckResult,
		isLoading,
		error,

		setSiteInfo,
		setSitesHealthCheckResult,

		performHealthCheckOnSites,
		fetchBrandSitesInfo,
		reset,
	};
};

export default useSitesManagement;
