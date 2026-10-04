/**
 * WordPress dependencies
 */
import { memo, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal, Spinner, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import MemoizedPatternPreview from './MemoizedPatternPreview';
import SiteSelection from './SiteSelection';

type SiteId = number | string;

interface Pattern {
	name?: string;
	providerSite?: string | false;
	title?: string;
	[ key: string ]: unknown;
}

interface SiteInfoEntry {
	id: SiteId;
	name?: string;
	[ key: string ]: unknown;
}

interface SitePatternEntry {
	name?: string;
	[ key: string ]: unknown;
}

interface ApplyResult {
	success?: boolean;
	message?: string;
}

interface ApplicationStatus {
	type: 'warning' | 'success' | 'error' | 'info';
	message: string;
	hasDetails?: boolean;
}

interface DetailedError {
	site: string;
	message: string;
}

interface BasePatternsTabProps {
	isLoading: boolean;
	basePatterns: Pattern[];
	visibleCount: number;
	selectedPatterns: string[];
	handlePatternSelection: ( name?: string ) => void;
	hasMorePatterns: boolean;
	loadMorePatterns: () => void;
	applySelectedPatterns: () => Promise< ApplyResult | null | undefined >;
	setSelectedPatterns: ( patterns: string[] ) => void;
	sitePatterns?: Record< string, SitePatternEntry[] >;
	siteOptions?: SiteInfoEntry[] | Record< string, SiteInfoEntry >;
	BrandSites?: SiteId[];
}

/**
 * BasePatternsTab component displays a list of base patterns with options to apply them to brand sites
 *
 * @param props                        - Component properties.
 * @param props.isLoading              - Indicates if base patterns are loading.
 * @param props.basePatterns           - List of base patterns.
 * @param props.visibleCount           - Number of base patterns currently visible.
 * @param props.selectedPatterns       - List of selected patterns.
 * @param props.handlePatternSelection - Function to handle pattern selection.
 * @param props.hasMorePatterns        - Indicates if there are more base patterns to load.
 * @param props.loadMorePatterns       - Function to load more base patterns.
 * @param props.applySelectedPatterns  - Function to apply selected patterns.
 * @param props.setSelectedPatterns    - Function to set the selected patterns.
 * @param props.sitePatterns           - Patterns from the brand site.
 * @param props.siteOptions            - Information about the brand sites.
 * @param props.BrandSites             - List of brand site IDs.
 *
 * @return Rendered component.
 */
const BasePatternsTab = memo(
	( {
		isLoading,
		basePatterns,
		visibleCount,
		selectedPatterns,
		handlePatternSelection,
		hasMorePatterns,
		loadMorePatterns,
		applySelectedPatterns,
		setSelectedPatterns,
		sitePatterns = {},
		siteOptions: siteInfo = {},
		BrandSites: selectedSites = [],
	}: BasePatternsTabProps ): JSX.Element => {
		const [ isModalOpen, setIsModalOpen ] = useState( false );
		const [ isApplying, setIsApplying ] = useState( false );
		const [ applicationStatus, setApplicationStatus ] =
			useState< ApplicationStatus | null >( null );
		const [ isSiteSelected, setIsSiteSelected ] = useState( false );
		const [ showCloseConfirmation, setShowCloseConfirmation ] =
			useState( false );
		const [ showDetailedErrors, setShowDetailedErrors ] = useState( false );
		const [ detailedErrors, setDetailedErrors ] = useState<
			DetailedError[]
		>( [] );

		useEffect( () => {
			setSelectedPatterns( [] );
		}, [ setSelectedPatterns ] );

		// Loading state for patterns
		if ( isLoading ) {
			return (
				<div className="onedesign-pattern-loading">
					<div className="onedesign-loading-content">
						<Spinner />
						<p className="onedesign-loading-text">
							{ __( 'Loading patterns…', 'onedesign' ) }
						</p>
					</div>
				</div>
			);
		}

		const OpenBrandSiteModal = () => {
			if ( selectedPatterns.length === 0 ) {
				setApplicationStatus( {
					type: 'warning',
					message: __(
						'Please select at least one pattern before proceeding.',
						'onedesign'
					),
				} );
				return;
			}

			setApplicationStatus( null );
			setIsModalOpen( true );
		};

		const CloseBrandSiteModal = () => {
			// If we're in the middle of applying patterns, show confirmation first
			if ( isApplying && ! showCloseConfirmation ) {
				setShowCloseConfirmation( true );
				setApplicationStatus( {
					type: 'warning',
					message: __(
						'Pattern application is in progress. Are you sure you want to cancel?',
						'onedesign'
					),
				} );
				return;
			}

			setIsModalOpen( false );
			setIsApplying( false );
			setApplicationStatus( null );
			setShowCloseConfirmation( false );
		};

		const handleApplyPatterns = async () => {
			setIsApplying( true );

			try {
				const result = await applySelectedPatterns();

				if ( result && result.success ) {
					// Show success message but don't close immediately
					setApplicationStatus( {
						type: 'success',
						message: sprintf(
							/* translators: %s site names. */
							__(
								'Patterns applied successfully to %s site.',
								'onedesign'
							),
							Object.values( siteInfo )
								.filter( ( site ) =>
									selectedSites.includes( site.id )
								)
								.map( ( site ) => site.name )
								.join( ', ' )
						),
					} );

					// Close modal after success with slightly longer delay for better user feedback
					setTimeout( () => {
						CloseBrandSiteModal();
						setSelectedPatterns( [] );
					}, 3000 );
				} else {
					setApplicationStatus( {
						type: 'error',
						message:
							result?.message ||
							__(
								'Failed to apply patterns. Please try again.',
								'onedesign'
							),
					} );
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Error applying patterns:', error );

				// Parse the error message to extract site-specific errors if possible
				let errorMessage =
					( error instanceof Error ? error.message : '' ) ||
					__(
						'Failed to apply patterns. Please try again.',
						'onedesign'
					);
				let parsedErrors: DetailedError[] = [];

				// Check if this is a multi-site error (contains bullet points)
				if ( errorMessage.includes( '•' ) ) {
					// This is a multi-site error, extract the summary line
					const summaryLine = errorMessage.split( '\n' )[ 0 ] ?? '';

					// Extract individual site errors
					parsedErrors = errorMessage
						.split( '\n' )
						.filter( ( line ) => line.includes( '•' ) )
						.map( ( line ) => {
							// Format: • Site Name: Error message
							const match = line.match( /•\s+(.*?):\s+(.*)/ );
							if ( match && match.length >= 3 ) {
								return {
									site: match[ 1 ]?.trim() ?? '',
									message: match[ 2 ]?.trim() ?? '',
								};
							}
							return { site: 'Unknown', message: line.trim() };
						} );

					// Set a summary message for the notice
					errorMessage = `${ summaryLine } (${
						parsedErrors.length
					} ${ parsedErrors.length === 1 ? 'site' : 'sites' })`;
					setDetailedErrors( parsedErrors );
					setShowDetailedErrors( false );
				}

				setApplicationStatus( {
					type: 'error',
					message: errorMessage,
					hasDetails: parsedErrors.length > 0,
				} );
			} finally {
				setIsApplying( false );
			}
		};

		const BrandSiteSelection = (): JSX.Element => {
			return (
				<div className="onedesign-brand-site-modal-content">
					{ applicationStatus && (
						<Notice
							status={ applicationStatus?.type ?? 'info' }
							isDismissible
							className="onedesign-application-notice onedesign-error-notice"
						>
							<div className="onedesign-error-notice-summary">
								<div className="onedesign-notice-message">
									{ applicationStatus?.message }
								</div>
							</div>

							{ showDetailedErrors &&
								applicationStatus?.hasDetails && (
									<div className="onedesign-error-details">
										{ detailedErrors.map(
											( error, index ) => (
												<div
													key={ index }
													className="onedesign-error-site"
												>
													<div className="onedesign-error-site-name">
														{ error?.site }
													</div>
													<div className="onedesign-error-site-message">
														{ error?.message }
													</div>
												</div>
											)
										) }
									</div>
								) }
						</Notice>
					) }

					<div className="onedesign-site-selection-wrapper">
						<SiteSelection
							setIsSiteSelected={ setIsSiteSelected }
							selectedPatterns={ selectedPatterns }
							basePatterns={ basePatterns }
							sitePatterns={ sitePatterns }
						/>
					</div>

					<div className="onedesign-modal-actions">
						<Button
							variant="secondary"
							onClick={ CloseBrandSiteModal }
							disabled={ isApplying && ! showCloseConfirmation }
						>
							{ showCloseConfirmation
								? __( 'Yes, Cancel', 'onedesign' )
								: __( 'Cancel', 'onedesign' ) }
						</Button>
						{ showCloseConfirmation ? (
							<Button
								variant="secondary"
								onClick={ () =>
									setShowCloseConfirmation( false )
								}
							>
								{ __( 'Continue Applying', 'onedesign' ) }
							</Button>
						) : (
							<Button
								variant="primary"
								onClick={ handleApplyPatterns }
								disabled={ isApplying || ! isSiteSelected }
								className="onedesign-apply-button"
								isBusy={ isApplying }
							>
								{ __( 'Apply Patterns', 'onedesign' ) }
							</Button>
						) }
					</div>
				</div>
			);
		};

		return (
			<div className="onedesign-patterns-container">
				<div className="onedesign-pattern-modal">
					{ basePatterns && basePatterns.length === 0 ? (
						<div className="onedesign-no-patterns">
							<p>{ __( 'No patterns found', 'onedesign' ) }</p>
							<p className="onedesign-no-patterns-subtitle">
								{ __(
									'Try adjusting your search criteria or check back later.',
									'onedesign'
								) }
							</p>
						</div>
					) : (
						basePatterns
							?.slice( 0, visibleCount )
							.map( ( pattern ) => (
								<MemoizedPatternPreview
									key={ pattern?.name }
									pattern={ pattern }
									isSelected={ selectedPatterns.includes(
										pattern?.name ?? ''
									) }
									onSelect={ () =>
										handlePatternSelection( pattern?.name )
									}
								/>
							) )
					) }
				</div>

				<div className="onedesign-pattern-footer">
					<div className="onedesign-selection-info">
						{ selectedPatterns.length > 0 && (
							<div className="onedesign-selected-count">
								<span className="onedesign-count-badge">
									{ selectedPatterns.length }
								</span>
								<span className="onedesign-count-text">
									{ selectedPatterns.length === 1
										? __( 'pattern selected', 'onedesign' )
										: __(
												'patterns selected',
												'onedesign'
										  ) }
								</span>
							</div>
						) }
					</div>

					<div className="onedesign-footer-actions">
						{ hasMorePatterns && (
							<Button
								variant="secondary"
								onClick={ loadMorePatterns }
								className="onedesign-load-more-button"
							>
								{ __( 'Show More', 'onedesign' ) }
								<span className="onedesign-pattern-count">
									({ visibleCount }/{ basePatterns.length })
								</span>
							</Button>
						) }

						<Button
							onClick={ OpenBrandSiteModal }
							variant="primary"
							className="onedesign-apply-to-sites-button"
							disabled={ selectedPatterns.length === 0 }
						>
							{ selectedPatterns.length === 0
								? __( 'Select Patterns First', 'onedesign' )
								: __( 'Apply to Sites', 'onedesign' ) }
						</Button>
					</div>
				</div>

				{ isModalOpen && (
					<Modal
						title=""
						onRequestClose={ CloseBrandSiteModal }
						className="onedesign-brand-site-modal"
						shouldCloseOnClickOutside={ ! isApplying }
						shouldCloseOnEsc={ ! isApplying }
						isFullScreen
					>
						{ BrandSiteSelection() }
					</Modal>
				) }
			</div>
		);
	}
);

export default BasePatternsTab;
