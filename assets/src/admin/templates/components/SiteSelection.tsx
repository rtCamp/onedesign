/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';

/**
 * External dependencies
 */
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';

/**
 * Internal dependencies
 */
import { getInitials } from '../../../js/utils';
import { renderIcon } from '../../../components/Dashicons';

type SiteId = number | string;
type TemplateId = number | string;

interface Site {
	id: SiteId;
	name?: string;
	url?: string;
	logo?: string;
}

interface BrandTemplate {
	original_id?: TemplateId;
	[ key: string ]: unknown;
}

interface NoticeState {
	type: 'error' | 'success' | 'warning';
	message: string;
}

interface SiteSelectionProps {
	siteInfo: Site[];
	isApplying: boolean;
	setIsApplying: ( value: boolean ) => void;
	onApply: ( siteIds: SiteId[] ) => void;
	setIsApplyModalOpen: ( value: boolean ) => void;
	setSelectedSites: (
		value: SiteId[] | ( ( prev: SiteId[] ) => SiteId[] )
	) => void;
	selectedSites: SiteId[];
	notice: NoticeState | null;
	brandSiteTemplates: Record< string, BrandTemplate[] >;
	selectedTemplates: TemplateId[];
	sitesHealthCheckResult?: Record<
		string,
		{ success?: boolean } | undefined
	>;
}

/**
 * SiteSelection component.
 *
 * @param props                        - Component props.
 * @param props.siteInfo               - Array of connected site information.
 * @param props.isApplying             - Boolean indicating if templates are being applied.
 * @param props.setIsApplying          - Function to set the isApplying state.
 * @param props.onApply                - Function to handle applying templates to selected sites.
 * @param props.setIsApplyModalOpen    - Function to control the visibility of the apply modal.
 * @param props.setSelectedSites       - Function to set the selected site IDs.
 * @param props.selectedSites          - Array of selected site IDs.
 * @param props.notice                 - Notice object containing type and message.
 * @param props.brandSiteTemplates     - Array of templates available for brand sites.
 * @param props.selectedTemplates      - Array of selected template IDs.
 * @param props.sitesHealthCheckResult - Object containing health check results for sites.
 * @return The rendered component.
 */
const SiteSelection = ( {
	siteInfo,
	isApplying,
	setIsApplying,
	onApply,
	setIsApplyModalOpen,
	setSelectedSites,
	selectedSites,
	notice,
	brandSiteTemplates,
	selectedTemplates,
	sitesHealthCheckResult,
}: SiteSelectionProps ): JSX.Element => {
	// Helper function to check if all templates are already present
	const areAllTemplatesPresent = ( siteId: SiteId ) => {
		const siteTemplates = brandSiteTemplates[ siteId ];
		if ( selectedTemplates.length === 0 || siteTemplates === undefined ) {
			return false;
		}

		const availableTemplateIds = Object.values( siteTemplates ).map(
			( template ) => template.original_id
		);

		return selectedTemplates.every( ( templateId ) =>
			availableTemplateIds.includes( templateId )
		);
	};

	// Helper function to check if a site is unreachable
	const isSiteUnreachable = ( siteId: SiteId ): boolean => {
		return Boolean(
			sitesHealthCheckResult?.[ siteId ] &&
				! sitesHealthCheckResult[ siteId ]?.success
		);
	};

	// Helper function to check if a site should be disabled
	const isSiteDisabled = ( siteId: SiteId ) => {
		return areAllTemplatesPresent( siteId ) || isSiteUnreachable( siteId );
	};

	const handleSiteSelection = ( siteId: SiteId ) => {
		// Prevent selection/deselection of disabled sites
		if ( isSiteDisabled( siteId ) ) {
			return;
		}

		setSelectedSites( ( prevSelected ) => {
			if ( prevSelected.includes( siteId ) ) {
				return prevSelected.filter( ( id ) => id !== siteId );
			}
			return [ ...prevSelected, siteId ];
		} );
	};

	const selectAllSites = () => {
		// Get IDs of sites that are selectable (not disabled)
		const selectableSiteIds = siteInfo
			.filter( ( site ) => ! isSiteDisabled( site.id ) )
			.map( ( site ) => site.id );

		setSelectedSites( selectableSiteIds );
	};

	const deselectAllSites = () => {
		setSelectedSites( [] );
	};

	const totalCount = siteInfo.length;

	// Calculate the number of selectable sites
	const selectableSites = siteInfo.filter(
		( site ) => ! isSiteDisabled( site.id )
	);
	const selectableSiteCount = selectableSites.length;

	// Count only selected sites that are still selectable
	const selectedSelectableSiteCount = selectedSites.filter( ( siteId ) =>
		selectableSites.some( ( site ) => site.id === siteId )
	).length;

	const selectedCount = selectedSites.length;

	if ( siteInfo.length === 0 ) {
		return (
			<div className="onedesign-no-sites">
				<Notice status="warning" isDismissible={ false }>
					<p>{ __( 'No connected sites found.', 'onedesign' ) }</p>
					<p>
						{ __(
							'Please configure brand sites first to apply templates.',
							'onedesign'
						) }
					</p>
				</Notice>
			</div>
		);
	}

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => {} }
				>
					{ notice.message }
				</Notice>
			) }

			<div className="onedesign-brand-site-modal-content">
				<div className="onedesign-site-selection-wrapper">
					<div className="onedesign-brand-site-selection">
						<div className="onedesign-selection-header">
							<div className="onedesign-selection-summary">
								<h4>
									{ __( 'Select Brand Sites', 'onedesign' ) }
								</h4>
								<span className="onedesign-selection-count">
									{ selectedCount > 0
										? sprintf(
												/* translators: %1$d: Number of selected sites, %2$d: Total number of sites. */
												__(
													'%1$d of %2$d selected',
													'onedesign'
												),
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
									<span className="onedesign-bulk-separator">
										|
									</span>
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
						{ selectedTemplates.length > 0 &&
							totalCount !== selectableSiteCount && (
								<div className="onedesign-selection-hint">
									<p>
										<span className="dashicons dashicons-info"></span>
										{ sprintf(
											/* translators: %1$d: Number of sites that already have all selected templates or are unreachable. %2$d: Total number of sites. */
											__(
												'%1$d of %2$d sites are disabled (already have all templates or unreachable).',
												'onedesign'
											),
											totalCount - selectableSiteCount,
											totalCount
										) }
									</p>
								</div>
							) }

						<div className="onedesign-sites-list onedesign-sites-grid">
							{ siteInfo.map( ( { id, name, url, logo } ) => {
								const isSelected = selectedSites.includes( id );
								const isDisabled = isSiteDisabled( id );

								return (
									<div
										key={ id }
										className={ `onedesign-site-item ${
											isSelected
												? 'onedesign-site-selected'
												: ''
										} ${
											isDisabled
												? 'onedesign-site-disabled'
												: ''
										}` }
										onClick={ () =>
											! isDisabled &&
											handleSiteSelection( id )
										}
										onKeyDown={ (
											e: ReactKeyboardEvent< HTMLDivElement >
										) => {
											if (
												! isDisabled &&
												( e.key === 'Enter' ||
													e.key === ' ' )
											) {
												// Prevent Space from scrolling the page when activating this custom control.
												if ( e.key === ' ' ) {
													e.preventDefault();
												}
												handleSiteSelection( id );
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
														sitesHealthCheckResult:
															sitesHealthCheckResult ??
															{},
														id,
													} ) }
												</div>
											) }
											{ isDisabled && ! isSelected && (
												<div
													className="onedesign-site-disabled-indicator"
													title={
														isSiteUnreachable( id )
															? __(
																	'This site is unreachable',
																	'onedesign'
															  )
															: __(
																	'This site already has all selected templates',
																	'onedesign'
															  )
													}
												>
													{ renderIcon( {
														sitesHealthCheckResult:
															sitesHealthCheckResult ??
															{},
														id,
													} ) }
												</div>
											) }
											<div className="onedesign-site-logo">
												{ logo ? (
													<img
														src={ logo }
														alt={ name }
														loading="lazy"
													/>
												) : (
													<div className="onedesign-site-initials">
														{ getInitials(
															name ?? ''
														) }
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

											{ /* Template sync status */ }
											{ selectedTemplates.length > 0 &&
												brandSiteTemplates[ id ] !==
													undefined && (
													<div className="onedesign-template-status">
														{ ( () => {
															const availableTemplateIds =
																Object.values(
																	brandSiteTemplates[
																		id
																	] ?? []
																).map(
																	(
																		template
																	) =>
																		template.original_id
																);

															const alreadyPresentCount =
																selectedTemplates.filter(
																	(
																		templateId
																	) =>
																		availableTemplateIds.includes(
																			templateId
																		)
																).length;

															const totalSelected =
																selectedTemplates.length;

															if (
																alreadyPresentCount ===
																0
															) {
																return (
																	<span className="onedesign-onedesign-info">
																		{ __(
																			'All templates will be synced',
																			'onedesign'
																		) }
																	</span>
																);
															}

															if (
																alreadyPresentCount ===
																totalSelected
															) {
																return (
																	<span className="onedesign-onedesign-info onedesign-all-templates-present">
																		{ __(
																			'All selected templates are already present',
																			'onedesign'
																		) }
																	</span>
																);
															}

															return (
																<span className="onedesign-onedesign-info">
																	{ sprintf(
																		/* translators: %1$d: Number of selected templates already present. %2$d: Total number of selected templates. */
																		__(
																			'%1$d of %2$d selected templates are already present',
																			'onedesign'
																		),
																		alreadyPresentCount,
																		totalSelected
																	) }
																</span>
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
				</div>

				<div className="onedesign-modal-actions">
					<Button
						variant="secondary"
						onClick={ () => {
							setIsApplyModalOpen( false );
							setSelectedSites( [] );
						} }
					>
						{ __( 'Cancel', 'onedesign' ) }
					</Button>
					<Button
						variant="primary"
						disabled={ selectedSites.length === 0 || isApplying }
						isBusy={ isApplying }
						onClick={ () => {
							setIsApplying( true );
							onApply( selectedSites );
						} }
					>
						{ __( 'Apply Templates', 'onedesign' ) }
					</Button>
				</div>
			</div>
		</>
	);
};

export default SiteSelection;
