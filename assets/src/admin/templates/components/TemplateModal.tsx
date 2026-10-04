/**
 * WordPress dependencies
 */
import {
	Button,
	Modal,
	SearchControl,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { cog } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import BaseSiteTemplates from './BaseSiteTemplates';
import SiteSelection from './SiteSelection';
import BrandSiteTemplates from './BrandSiteTemplates';
import useSitesManagement from '../../../hooks/useSitesManagement';
import {
	API_NAMESPACE as REST_NAMESPACE,
	NONCE,
	SETTINGS_LINK as SettingLink,
	PER_PAGE,
} from '../../../js/constants';

type SiteId = number | string;
type TemplateId = number | string;

interface Template {
	id?: TemplateId;
	original_id?: TemplateId;
	name?: string;
	title?: string;
	description?: string;
	[ key: string ]: unknown;
}

type ConnectedTemplatesMap = Record< string, Template[] >;

interface BrandSite {
	id: SiteId;
	name?: string;
	url?: string;
	logo?: string;
}

interface Tab {
	name: string;
	title: string;
	className: string;
	value?: SiteId;
}

interface NoticeState {
	type: 'error' | 'success';
	message: string;
}

/**
 * TemplateModal component.
 *
 * @return The rendered component.
 */
const TemplateModal = (): JSX.Element => {
	const [ templates, setTemplates ] = useState< Template[] >( [] );
	const [ isOpen, setIsOpen ] = useState( true );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const [ selectedTemplates, setSelectedTemplates ] = useState<
		TemplateId[]
	>( [] );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ activeTab, setActiveTab ] = useState< SiteId >( 'baseTemplate' );
	const [ selectedSites, setSelectedSites ] = useState< SiteId[] >( [] );
	const [ connectedSitesTemplates, setConnectedSitesTemplates ] =
		useState< ConnectedTemplatesMap >( {} );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isReSyncing, setIsReSyncing ] = useState( false );

	const {
		siteInfo,
		sitesHealthCheckResult,
		isLoading: isSiteInfoLoading,
	} = useSitesManagement( { NONCE, API_NAMESPACE: REST_NAMESPACE } );

	// The hook types siteInfo as a keyed record; consumers treat it as a list.
	const siteList = Object.values( siteInfo ) as BrandSite[];

	const [ tabs, setTabs ] = useState< Tab[] >( [
		{
			name: 'baseTemplate',
			title: __( 'Current Site Templates', 'onedesign' ),
			className: 'onedesign-base-templates-tab',
			value: 'baseTemplate',
		},
	] );
	const [ isApplyModalOpen, setIsApplyModalOpen ] = useState( false );
	const [ isApplying, setIsApplying ] = useState( false );

	const fetchConnectedSitesTemplates = useCallback( async () => {
		try {
			const response = await fetch(
				`${ REST_NAMESPACE }/templates/connected-sites?timestamp=${ Date.now() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);
			const data = ( await response.json() ) as {
				success?: boolean;
				templates?: ConnectedTemplatesMap;
			};
			if ( data.success ) {
				setConnectedSitesTemplates( data.templates || {} );
			}
		} catch {}
	}, [] );

	const fetchTemplates = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch(
				`${ REST_NAMESPACE }/templates/all?timestamp=${ Date.now() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);
			const data = ( await response.json() ) as {
				success?: boolean;
				templates?: Template[];
			};
			if ( data.success ) {
				setTemplates( data.templates || [] );
			}
		} catch {
		} finally {
			setIsLoading( false );
		}
	}, [] );

	const handleTemplateReSync = useCallback( async () => {
		setIsReSyncing( true );
		try {
			const idArray = Object.values( connectedSitesTemplates )
				.flat()
				.map( ( template ) => template.id );
			const originalIdArray = Object.values( connectedSitesTemplates )
				.flat()
				.map( ( template ) => template.original_id );
			const response = await fetch(
				`${ REST_NAMESPACE }/templates/resync`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						sites: Array.of( activeTab ),
						templates: [ ...idArray, ...originalIdArray ],
					} ),
				}
			);
			const data = ( await response.json() ) as { success?: boolean };
			if ( data.success ) {
				fetchConnectedSitesTemplates();
				setNotice( {
					type: 'success',
					message: __(
						'Templates re-synced successfully.',
						'onedesign'
					),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to re-sync templates.', 'onedesign' ),
				} );
			}
		} catch {
		} finally {
			setIsReSyncing( false );
		}
	}, [ fetchConnectedSitesTemplates, activeTab, connectedSitesTemplates ] );

	const handleApplyTemplates = useCallback( async () => {
		setIsApplying( true );
		try {
			const response = await fetch(
				`${ REST_NAMESPACE }/templates/apply`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						templates: selectedTemplates,
						sites: selectedSites,
					} ),
				}
			);
			const data = ( await response.json() ) as { success?: boolean };
			if ( data.success ) {
				setSelectedTemplates( [] );
				setSelectedSites( [] );
				fetchConnectedSitesTemplates();
				setNotice( {
					type: 'success',
					message: sprintf(
						/* translators: %s site names. */
						__(
							'Templates applied successfully to %s site.',
							'onedesign'
						),
						siteList
							.filter( ( site ) =>
								selectedSites.includes( site.id )
							)
							.map( ( site ) => site.name )
							.join( ', ' )
					),
				} );
				setTimeout( () => {
					setNotice( null );
					setIsApplyModalOpen( false );
				}, 3000 );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to apply templates.', 'onedesign' ),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'An error occurred while applying templates.',
					'onedesign'
				),
			} );
		} finally {
			setIsApplying( false );
			setTimeout( () => {
				setNotice( null );
			}, 3000 );
		}
	}, [
		selectedTemplates,
		selectedSites,
		fetchConnectedSitesTemplates,
		siteList,
	] );

	useEffect( () => {
		fetchTemplates();
		fetchConnectedSitesTemplates();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		setNotice( null );
	}, [ activeTab ] );

	useEffect( () => {
		const newTabs: Tab[] = [
			{
				name: 'baseTemplate',
				title: __( 'Current Site Templates', 'onedesign' ),
				className: 'onedesign-base-templates-tab',
				value: 'baseTemplate',
			},
		];
		siteList.forEach( ( site ) => {
			if ( site?.id && site?.name ) {
				newTabs.push( {
					name: site.name,
					title: site.name,
					className: 'onedesign-templates-tab-brand-site',
					value: site.id,
				} );
			}
		} );
		setTabs( newTabs );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ siteInfo ] );

	const handleTemplateSelection = ( tId?: TemplateId ) => {
		if ( tId === undefined ) {
			return;
		}
		setSelectedTemplates( ( prevSelected ) => {
			const newSelected = prevSelected.includes( tId )
				? prevSelected.filter( ( id ) => id !== tId )
				: [ ...prevSelected, tId ];
			return newSelected;
		} );
	};

	const filteredTemplates = useMemo( () => {
		if ( searchQuery.trim() === '' ) {
			return templates;
		}
		return templates.filter(
			( template ) =>
				( template.title || '' )
					.toLowerCase()
					.includes( searchQuery.toLowerCase() ) ||
				( template.description &&
					template.description
						.toLowerCase()
						.includes( searchQuery.toLowerCase() ) )
		);
	}, [ templates, searchQuery ] );

	const renderPagination = () => {
		// default will show 9 templates then will show load more button.
		return (
			<div className="onedesign-pagination">
				<div className="onedesign-selected-templates-info">
					{ selectedTemplates.length > 0 && (
						<div className="onedesign-selected-templates-count-info">
							<span className="onedesign-selected-templates-count">
								{ selectedTemplates.length }
							</span>
							<span className="onedesign-selected-templates-text">
								{ selectedTemplates.length === 1
									? __( 'Template selected', 'onedesign' )
									: __( 'Templates selected', 'onedesign' ) }
							</span>
						</div>
					) }
				</div>
				<div
					style={ {
						display: 'flex',
						gap: '12px',
						flexDirection: 'row',
					} }
				>
					<Button
						variant="secondary"
						disabled={
							currentPage * PER_PAGE >= filteredTemplates.length
						}
						onClick={ () =>
							setCurrentPage( ( prevPage ) => prevPage + 1 )
						}
					>
						{ __( 'Show More', 'onedesign' ) }{ ' ' }
						{ Math.min(
							currentPage * PER_PAGE,
							filteredTemplates.length
						) }
						/{ filteredTemplates.length }
					</Button>
					<Button
						variant="primary"
						disabled={ selectedTemplates.length === 0 }
						onClick={ () => {
							setIsApplyModalOpen( true );
						} }
					>
						{ selectedTemplates.length === 0
							? __( 'Select Template First', 'onedesign' )
							: __( 'Apply To Sites', 'onedesign' ) }
					</Button>
				</div>
			</div>
		);
	};

	const handleTabSelection = ( tab: string ) => {
		setActiveTab(
			tabs.find( ( t ) => t.name === tab )?.value || 'baseTemplate'
		);
		setSearchQuery( '' );
		setCurrentPage( 1 );
		setSelectedTemplates( [] );
	};

	return (
		<>
			<div className="onedesign-loader">
				<Spinner />
				{ __( 'Loading…', 'onedesign' ) }
			</div>
			{ isOpen && (
				<Modal
					title={ __( 'Template Library', 'onedesign' ) }
					onRequestClose={ () => {
						setIsOpen( false );
						window.history.back();
					} }
					className="onedesign-template-modal"
					headerActions={
						<div
							style={ {
								display: 'flex',
								gap: '8px',
								flexDirection: 'row',
								alignItems: 'center',
							} }
						>
							{ activeTab !== 'baseTemplate' && (
								<Button
									variant="primary"
									onClick={ () => {
										handleTemplateReSync();
									} }
									isBusy={ isReSyncing }
									disabled={
										isReSyncing ||
										Object.keys( connectedSitesTemplates )
											?.length === 0 ||
										(
											connectedSitesTemplates?.[
												activeTab
											] || []
										)?.length === 0
									}
									label={ __(
										'Sync Shared Templates',
										'onedesign'
									) }
								>
									{ __(
										'Sync Shared Templates',
										'onedesign'
									) }
								</Button>
							) }

							{ SettingLink && (
								<Button
									icon={ cog }
									variant="secondary"
									onClick={ () => {
										window.location.href = SettingLink;
									} }
									label={ __(
										'Go to OneDesign Settings',
										'onedesign'
									) }
								/>
							) }
						</div>
					}
				>
					{ ( isLoading || isSiteInfoLoading ) && (
						<div style={ { textAlign: 'center', padding: '20px' } }>
							<Spinner />
							<p>{ __( 'Loading templates…', 'onedesign' ) }</p>
						</div>
					) }
					{ ! ( isLoading || isSiteInfoLoading ) && (
						<>
							<SearchControl
								value={ searchQuery }
								onChange={ ( value ) =>
									setSearchQuery( value )
								}
								placeholder={ __(
									'Search Templates',
									'onedesign'
								) }
								className="onedesign-template-search"
								__nextHasNoMarginBottom
							/>
							<TabPanel
								className="onedesign-template-tabs"
								activeClass="active-tab"
								onSelect={ handleTabSelection }
								tabs={ tabs }
							>
								{ ( tab ) => {
									const currentTab = tab as Tab;
									if ( currentTab.name === 'baseTemplate' ) {
										return (
											<>
												<BaseSiteTemplates
													filteredTemplates={
														filteredTemplates
													}
													currentPage={ currentPage }
													PER_PAGE={ PER_PAGE }
													selectedTemplates={
														selectedTemplates
													}
													handleTemplateSelection={
														handleTemplateSelection
													}
												/>
												{ renderPagination() }
											</>
										);
									}
									return (
										<BrandSiteTemplates
											filteredTemplates={ (
												connectedSitesTemplates[
													currentTab.value ?? ''
												] || []
											).filter(
												( template ) =>
													( template.title || '' )
														.toLowerCase()
														.includes(
															searchQuery.toLowerCase()
														) ||
													( template.description &&
														template.description
															.toLowerCase()
															.includes(
																searchQuery.toLowerCase()
															) )
											) }
											currentPage={ currentPage }
											PER_PAGE={ PER_PAGE }
											selectedTemplates={
												selectedTemplates
											}
											handleTemplateSelection={
												handleTemplateSelection
											}
											setCurrentPage={ setCurrentPage }
											currentSiteId={
												currentTab.value ?? ''
											}
											fetchConnectedSitesTemplates={
												fetchConnectedSitesTemplates
											}
											setSelectedTemplates={
												setSelectedTemplates
											}
											allTemplates={ templates }
											notice={ notice }
											setNotice={ setNotice }
										/>
									);
								} }
							</TabPanel>
							{ isApplyModalOpen && (
								<Modal
									onRequestClose={ () =>
										setIsApplyModalOpen( false )
									}
									className="onedesign-apply-templates-modal"
									isFullScreen
								>
									<SiteSelection
										siteInfo={ siteList }
										isApplying={ isApplying }
										setIsApplying={ setIsApplying }
										onApply={ () => {
											handleApplyTemplates();
										} }
										setIsApplyModalOpen={
											setIsApplyModalOpen
										}
										setSelectedSites={ setSelectedSites }
										selectedSites={ selectedSites }
										notice={ notice }
										brandSiteTemplates={
											connectedSitesTemplates
										}
										selectedTemplates={ selectedTemplates }
										sitesHealthCheckResult={
											sitesHealthCheckResult ?? {}
										}
									/>
								</Modal>
							) }
						</>
					) }
				</Modal>
			) }
		</>
	);
};

export default TemplateModal;
