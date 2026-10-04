/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner, Notice, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * External dependencies
 */
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';

/**
 * Internal dependencies
 */
import useSitesManagement from '../../../hooks/useSitesManagement';
import { getInitials } from '../../../js/utils';
import { API_NAMESPACE, NONCE } from '../../../js/constants';
import { renderIcon } from '../../../components/Dashicons';

type SiteId = number | string;

interface Site {
	id: SiteId;
	name?: string;
	url?: string;
	logo?: string;
}

interface SitePatternEntry {
	name?: string;
	[ key: string ]: unknown;
}

interface BasePattern {
	name?: string;
	title?: string;
	[ key: string ]: unknown;
}

interface FetchError {
	message: string;
	details?: string;
}

interface SiteSelectionProps {
	setIsSiteSelected: ( selected: boolean ) => void;
	selectedPatterns?: string[];
	basePatterns?: BasePattern[];
	sitePatterns?: Record< string, SitePatternEntry[] >;
}

/**
 * Component to render the brand site selection with enhanced UX.
 *
 * @param props                   - Component properties.
 * @param props.setIsSiteSelected - Function to set the site selection state.
 * @param props.selectedPatterns  - Array of selected pattern names.
 * @param props.basePatterns      - Array of base patterns for the current site.
 * @param props.sitePatterns      - Object mapping site IDs to their patterns.
 *
 * @return JSX Element
 */
const SiteSelection = ( {
	setIsSiteSelected,
	selectedPatterns = [],
	basePatterns = [],
	sitePatterns = {},
}: SiteSelectionProps ): JSX.Element => {
	const { sitesHealthCheckResult, isLoading: isSitesLoading } =
		useSitesManagement( { NONCE, API_NAMESPACE } );

	/**
	 * Get the current value of the brand_site meta field.
	 */
	const { BrandSite } = useSelect( ( select ) => {
		// eslint-disable-next-line @wordpress/data-no-store-string-literals -- `@wordpress/editor` isn't a project dependency; `core/editor` is provided globally by WP core in the block editor.
		const editor = select( 'core/editor' ) as {
			getEditedPostAttribute: (
				name: string
			) => { brand_site?: SiteId[] } | undefined;
		};
		const meta = editor.getEditedPostAttribute( 'meta' );
		return {
			BrandSite: meta?.brand_site || [],
		};
	}, [] );

	/**
	 * Dispatch the action to update the brand_site meta field.
	 */
	// eslint-disable-next-line @wordpress/data-no-store-string-literals -- `@wordpress/editor` isn't a project dependency; `core/editor` is provided globally by WP core in the block editor.
	const { editPost } = useDispatch( 'core/editor' ) as {
		editPost: ( data: { meta: { brand_site: SiteId[] } } ) => void;
	};

	const [ siteOptions, setSiteOptions ] = useState< Site[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState< FetchError | null >( null );

	const onBrandSiteChange = ( siteId: SiteId ) => {
		const newBrandSite = BrandSite.includes( siteId )
			? BrandSite.filter( ( site ) => site !== siteId )
			: [ ...BrandSite, siteId ];
		setIsSiteSelected( newBrandSite.length > 0 );
		editPost( { meta: { brand_site: newBrandSite } } );
	};

	const isSiteReachable = ( siteId: SiteId ): boolean => {
		return Boolean( sitesHealthCheckResult?.[ siteId ]?.success );
	};

	const selectAllSites = () => {
		const selectableSiteIds = siteOptions
			.filter( ( site ) => {
				if ( ! isSiteReachable( site.id ) ) {
					return false;
				}

				if ( selectedPatterns.length > 0 && sitePatterns[ site.id ] ) {
					const sitePatternsArray = sitePatterns[ site.id ] || [];
					const presentPatterns = selectedPatterns.filter(
						( patternName ) =>
							sitePatternsArray.some(
								( pattern ) =>
									pattern.name ===
									patternName.replace( /\//g, '' )
							)
					);

					return ! (
						presentPatterns.length === selectedPatterns.length &&
						selectedPatterns.length > 0
					);
				}
				return true;
			} )
			.map( ( site ) => site.id );

		setIsSiteSelected( selectableSiteIds.length > 0 );
		editPost( { meta: { brand_site: selectableSiteIds } } );
	};

	const deselectAllSites = () => {
		setIsSiteSelected( false );
		editPost( { meta: { brand_site: [] } } );
	};

	const fetchSites = async () => {
		try {
			const response = await apiFetch< Site[] >( {
				path: `/onedesign/v1/configured-sites`,
			} );

			const data = response;
			setSiteOptions( data );
			setError( null );
		} catch ( fetchError ) {
			const details =
				fetchError instanceof Error ? fetchError.message : undefined;
			setError( {
				message: __(
					'Failed to load brand sites. Please check your connection and try again.',
					'onedesign'
				),
				...( details ? { details } : {} ),
			} );
		} finally {
			setIsLoading( false );
		}
	};

	const retryFetch = () => {
		setIsLoading( true );
		setError( null );
		fetchSites();
	};

	useEffect( () => {
		fetchSites();
		setError( null );
		setIsLoading( true );
		editPost( { meta: { brand_site: [] } } );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const totalCount = siteOptions.length;

	const selectableSites = siteOptions.filter( ( site ) => {
		if ( ! isSiteReachable( site.id ) ) {
			return false;
		}

		if ( selectedPatterns.length > 0 && sitePatterns[ site.id ] ) {
			const sitePatternsArray = sitePatterns[ site.id ] || [];
			const presentPatterns = selectedPatterns.filter( ( patternName ) =>
				sitePatternsArray.some(
					( pattern ) =>
						pattern.name === patternName.replace( /\//g, '' )
				)
			);

			return ! (
				presentPatterns.length === selectedPatterns.length &&
				selectedPatterns.length > 0
			);
		}
		return true;
	} );

	const selectableSiteCount = selectableSites.length;
	const selectedSelectableSiteCount = BrandSite.filter( ( siteId ) =>
		selectableSites.some( ( site ) => site.id === siteId )
	).length;

	const selectedCount = BrandSite.length;

	if ( isLoading || isSitesLoading || sitesHealthCheckResult === undefined ) {
		return (
			<div className="onedesign-site-loading">
				<div className="onedesign-loading-content">
					<Spinner />
					<p>{ __( 'Loading brand sites…', 'onedesign' ) }</p>
				</div>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="onedesign-site-error">
				<Notice status="error" isDismissible={ false }>
					<p>{ error.message }</p>
					{ error.details && (
						<details className="onedesign-error-details">
							<summary>
								{ __( 'Technical details', 'onedesign' ) }
							</summary>
							<p>{ error.details }</p>
						</details>
					) }
				</Notice>
				<Button
					variant="secondary"
					onClick={ retryFetch }
					className="onedesign-retry-button"
				>
					{ __( 'Retry', 'onedesign' ) }
				</Button>
			</div>
		);
	}

	if ( siteOptions.length === 0 ) {
		return (
			<div className="onedesign-no-sites">
				<Notice status="warning" isDismissible={ false }>
					<p>{ __( 'No brand sites configured.', 'onedesign' ) }</p>
					<p>
						{ __(
							'Please configure brand sites first to apply patterns.',
							'onedesign'
						) }
					</p>
				</Notice>
			</div>
		);
	}

	return (
		<div className="onedesign-brand-site-selection">
			<div className="onedesign-selection-header">
				<div className="onedesign-selection-summary">
					<h4>{ __( 'Select Brand Sites', 'onedesign' ) }</h4>
					<span className="onedesign-selection-count">
						{ selectedCount > 0
							? sprintf(
									/* translators: %1$d: Number of selected sites, %2$d: Total number of sites. */
									__( '%1$d of %2$d selected', 'onedesign' ),
									selectedCount,
									selectableSiteCount
							  )
							: sprintf(
									/* translators: %1$d: Number of available sites, %2$d: Total number of sites. */
									__(
										'%1$d of %2$d sites available',
										'onedesign'
									),
									selectableSiteCount,
									totalCount
							  ) }
					</span>
				</div>

				{ totalCount > 1 && (
					<div className="onedesign-bulk-actions">
						<Button
							variant="link"
							onClick={ selectAllSites }
							disabled={
								selectedSelectableSiteCount ===
									selectableSiteCount ||
								selectableSiteCount === 0
							}
							className="onedesign-bulk-action"
						>
							{ __( 'Select All', 'onedesign' ) }
						</Button>
						<span className="onedesign-bulk-separator">|</span>
						<Button
							variant="link"
							onClick={ deselectAllSites }
							disabled={ selectedCount === 0 }
							className="onedesign-bulk-action"
						>
							{ __( 'Deselect All', 'onedesign' ) }
						</Button>
					</div>
				) }
			</div>

			{ /* Message explaining disabled sites if there are any */ }
			{ selectedPatterns.length > 0 && (
				<div className="onedesign-selection-hint">
					<p>
						<span className="dashicons dashicons-info"></span>
						{ totalCount !== selectableSiteCount
							? sprintf(
									/* translators: %1$d: number of non-selectable sites, %2$d: total sites */
									__(
										'%1$d of %2$d sites already have all selected patterns and are disabled.',
										'onedesign'
									),
									totalCount - selectableSiteCount,
									totalCount
							  )
							: __(
									'Sites that already have all selected patterns are disabled.',
									'onedesign'
							  ) }
					</p>
				</div>
			) }

			<div className="onedesign-sites-list onedesign-sites-grid">
				{ siteOptions.map( ( { id, name, url, logo } ) => {
					const isSelected = BrandSite?.includes( id );

					let hasAllPatterns = false;
					let isDisabled = false;

					if ( selectedPatterns.length > 0 && sitePatterns[ id ] ) {
						const sitePatternsArray = sitePatterns[ id ] || [];
						const presentPatterns = selectedPatterns.filter(
							( patternName ) =>
								sitePatternsArray.some(
									( pattern ) =>
										pattern.name ===
										patternName.replace( /\//g, '' )
								)
						);

						hasAllPatterns =
							presentPatterns.length ===
								selectedPatterns.length &&
							selectedPatterns.length > 0;
						isDisabled =
							( hasAllPatterns && ! isSelected ) ||
							! isSiteReachable( id );
					}

					return (
						<div
							key={ id }
							className={ `onedesign-site-item ${
								isSelected ? 'onedesign-site-selected' : ''
							} ${
								isDisabled ? 'onedesign-site-disabled' : ''
							}` }
							onClick={ () =>
								! isDisabled && onBrandSiteChange( id )
							}
							onKeyDown={ (
								e: ReactKeyboardEvent< HTMLDivElement >
							) => {
								if (
									! isDisabled &&
									( e.code === 'Enter' || e.code === 'Space' )
								) {
									onBrandSiteChange( id );
								}
							} }
							tabIndex={ isDisabled ? -1 : 0 }
							role="checkbox"
							aria-checked={ isSelected }
							aria-disabled={ isDisabled }
						>
							<div className="onedesign-site-inner">
								{ isSelected && (
									<div className="onedesign-site-selected-indicator">
										{ renderIcon( {
											sitesHealthCheckResult,
											id,
										} ) }
									</div>
								) }
								{ isDisabled && ! isSelected && (
									<div
										className="onedesign-site-disabled-indicator"
										title={ __(
											'This site already has all selected patterns',
											'onedesign'
										) }
									>
										{ renderIcon( {
											sitesHealthCheckResult,
											id,
										} ) }
									</div>
								) }
								<div className="onedesign-site-logo">
									{ logo ? (
										<img src={ logo } alt={ name } />
									) : (
										<div className="onedesign-site-initials">
											{ name ? getInitials( name ) : '?' }
										</div>
									) }
								</div>
								<span className="onedesign-site-name">
									{ name }
								</span>
								{ url && (
									<span className="onedesign-site-url">
										{ url }
									</span>
								) }

								{ /* Pattern sync status */ }
								{ selectedPatterns.length > 0 &&
									sitePatterns[ id ] && (
										<div className="onedesign-pattern-status">
											{ ( () => {
												const sitePatternsArray =
													sitePatterns[ id ] || [];
												const presentPatterns =
													selectedPatterns.filter(
														( patternName ) =>
															sitePatternsArray.some(
																( pattern ) =>
																	pattern.name ===
																	patternName.replace(
																		/\//g,
																		''
																	)
															)
													);
												const presentCount =
													presentPatterns.length;
												const allPatternsPresent =
													presentCount ===
														selectedPatterns.length &&
													selectedPatterns.length > 0;

												if ( presentCount === 0 ) {
													return (
														<span className="onedesign-onedesign-info">
															{ __(
																'All patterns will be synced',
																'onedesign'
															) }
														</span>
													);
												}

												if ( allPatternsPresent ) {
													return (
														<span className="onedesign-onedesign-info onedesign-all-patterns-present">
															{ __(
																'All selected patterns are already present',
																'onedesign'
															) }
														</span>
													);
												}

												const toSyncPatterns =
													selectedPatterns.filter(
														( patternName ) =>
															! sitePatternsArray.some(
																( pattern ) =>
																	pattern.name ===
																	patternName.replace(
																		/\//g,
																		''
																	)
															)
													);

												const toSyncPatternsTitles: Array<
													string | undefined
												> = toSyncPatterns.map(
													( patternName ) =>
														basePatterns.find(
															( pattern ) =>
																pattern.name ===
																patternName
														)?.title
												);

												if (
													toSyncPatternsTitles.length >
													5
												) {
													toSyncPatternsTitles.length = 5;
													toSyncPatternsTitles.push(
														'…'
													);
												}

												return (
													<>
														<span className="onedesign-onedesign-info">
															{ presentCount }{ ' ' }
															{ __(
																'of',
																'onedesign'
															) }{ ' ' }
															{
																selectedPatterns.length
															}{ ' ' }
															{ __(
																'selected patterns are already present',
																'onedesign'
															) }
														</span>
													</>
												);
											} )() }
										</div>
									) }
							</div>
						</div>
					);
				} ) }
			</div>
		</div>
	);
};

export default SiteSelection;
