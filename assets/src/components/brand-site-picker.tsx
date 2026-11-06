import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';
import { getInitials } from '@/js/utils';

export interface BrandSite {
	id: string;
	name: string;
	url: string;
	logo?: string;
}

export interface SiteError {
	site_id: string;
	message: string;
}

export interface BrandSitePickerCopy {
	apply: string;
	notApplied: string;
	allPresent: string;
	somePresent: ( presentCount: number, selectedCount: number ) => string;
	disabledHint: string;
}

interface BrandSitePickerProps {
	sites: BrandSite[];
	siteErrors: Record< string, string >;
	selectedCount: number;
	// How many of the selected items a brand site already has.
	countPresent: ( siteId: string ) => number;
	copy: BrandSitePickerCopy;
	// Resolves with the brand sites that didn't get the items.
	onApply: ( siteIds: string[] ) => Promise< SiteError[] >;
	onClose: () => void;
}

/**
 * Picks the brand sites to apply the selected patterns or templates to, and applies them.
 *
 * @param props               - Component properties.
 * @param props.sites         - The brand sites.
 * @param props.siteErrors    - Why each brand site that couldn't answer didn't, keyed by site id.
 * @param props.selectedCount - How many items are selected.
 * @param props.countPresent  - How many of the selected items a brand site already has.
 * @param props.copy          - The library's own copy.
 * @param props.onApply       - Applies the items to the picked sites.
 * @param props.onClose       - Closes the picker.
 *
 * @return The picker.
 */
const BrandSitePicker = ( {
	sites,
	siteErrors,
	selectedCount,
	countPresent,
	copy,
	onApply,
	onClose,
}: BrandSitePickerProps ) => {
	const [ picked, setPicked ] = useState< string[] >( [] );
	const [ isApplying, setIsApplying ] = useState( false );
	const [ failures, setFailures ] = useState< SiteError[] >( [] );

	const hasAll = ( siteId: string ) =>
		countPresent( siteId ) === selectedCount;
	const selectableSites = sites.filter(
		( site ) => ! siteErrors[ site.id ] && ! hasAll( site.id )
	);
	const pickedSelectableCount = picked.filter( ( id ) =>
		selectableSites.some( ( site ) => site.id === id )
	).length;

	const toggle = ( siteId: string ) =>
		setPicked( ( current ) =>
			current.includes( siteId )
				? current.filter( ( id ) => id !== siteId )
				: [ ...current, siteId ]
		);

	const apply = async () => {
		setIsApplying( true );
		setFailures( await onApply( picked ) );
		setIsApplying( false );
	};

	return (
		<Modal
			title={ __( 'Select Brand Sites', 'onedesign' ) }
			onRequestClose={ onClose }
			className="onedesign-brand-site-modal"
			shouldCloseOnClickOutside={ ! isApplying }
			shouldCloseOnEsc={ ! isApplying }
			onKeyDown={ ( event: React.KeyboardEvent< HTMLDivElement > ) => {
				if ( isApplying && event.key === 'Escape' ) {
					event.preventDefault();
				}
			} }
			isDismissible={ ! isApplying }
			isFullScreen
		>
			<div className="onedesign-brand-site-modal-content">
				{ failures.length > 0 && (
					<Notice
						status="error"
						isDismissible={ false }
						className="onedesign-error-notice"
					>
						<div className="onedesign-notice-message">
							{ copy.notApplied }
						</div>
						<div className="onedesign-error-details">
							{ failures.map( ( failure ) => (
								<div
									key={ failure.site_id }
									className="onedesign-error-site"
								>
									<div className="onedesign-error-site-name">
										{ sites.find(
											( site ) =>
												site.id === failure.site_id
										)?.name ?? failure.site_id }
									</div>
									<div className="onedesign-error-site-message">
										{ failure.message }
									</div>
								</div>
							) ) }
						</div>
					</Notice>
				) }

				<div className="onedesign-site-selection-wrapper">
					<div className="onedesign-brand-site-selection">
						<div className="onedesign-selection-header">
							<span className="onedesign-selection-count">
								{ sprintf(
									/* translators: %1$d: Number of picked sites. %2$d: Number of sites. */
									__( '%1$d of %2$d selected', 'onedesign' ),
									picked.length,
									sites.length
								) }
							</span>

							{ sites.length > 1 && (
								<div className="onedesign-bulk-actions">
									<Button
										variant="link"
										onClick={ () =>
											setPicked(
												selectableSites.map(
													( site ) => site.id
												)
											)
										}
										disabled={
											pickedSelectableCount ===
											selectableSites.length
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
										onClick={ () => setPicked( [] ) }
										disabled={ picked.length === 0 }
										className="onedesign-bulk-action"
									>
										{ __( 'Deselect All', 'onedesign' ) }
									</Button>
								</div>
							) }
						</div>

						{ selectableSites.length < sites.length && (
							<div className="onedesign-selection-hint">
								<p>
									<span className="dashicons dashicons-info"></span>
									{ copy.disabledHint }
								</p>
							</div>
						) }

						<div className="onedesign-sites-list onedesign-sites-grid">
							{ sites.map( ( { id, name, url, logo } ) => {
								const isPicked = picked.includes( id );
								const siteError = siteErrors[ id ];
								const presentCount = countPresent( id );
								// A picked site stays enabled so it can be unpicked.
								const isDisabled =
									Boolean( siteError ) ||
									( hasAll( id ) && ! isPicked );

								return (
									<div
										key={ id }
										className={ `onedesign-site-item${
											isPicked
												? ' onedesign-site-selected'
												: ''
										}${
											isDisabled
												? ' onedesign-site-disabled'
												: ''
										}` }
										onClick={ () =>
											! isDisabled && toggle( id )
										}
										onKeyDown={ (
											event: ReactKeyboardEvent< HTMLDivElement >
										) => {
											if (
												! isDisabled &&
												( event.key === 'Enter' ||
													event.key === ' ' )
											) {
												event.preventDefault();
												toggle( id );
											}
										} }
										tabIndex={ isDisabled ? -1 : 0 }
										role="checkbox"
										aria-checked={ isPicked }
										aria-disabled={ isDisabled }
									>
										<div className="onedesign-site-inner">
											{ isPicked && (
												<div className="onedesign-site-selected-indicator">
													<span className="dashicons dashicons-yes-alt"></span>
												</div>
											) }
											{ isDisabled && ! isPicked && (
												<div className="onedesign-site-disabled-indicator">
													<span
														className={ `dashicons ${
															siteError
																? 'dashicons-warning'
																: 'dashicons-yes-alt'
														}` }
													></span>
												</div>
											) }
											<div className="onedesign-site-logo">
												{ logo ? (
													<img src={ logo } alt="" />
												) : (
													<div className="onedesign-site-initials">
														{ getInitials( name ) }
													</div>
												) }
											</div>
											<span className="onedesign-site-name">
												{ name }
											</span>
											<span className="onedesign-site-url">
												{ url }
											</span>
											{ siteError && (
												<span className="onedesign-site-error-message">
													{ siteError }
												</span>
											) }
											{ ! siteError &&
												presentCount > 0 && (
													<div className="onedesign-site-status">
														{ hasAll( id )
															? copy.allPresent
															: copy.somePresent(
																	presentCount,
																	selectedCount
																) }
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
						onClick={ onClose }
						disabled={ isApplying }
					>
						{ __( 'Cancel', 'onedesign' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ apply }
						disabled={ isApplying || picked.length === 0 }
						isBusy={ isApplying }
					>
						{ copy.apply }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default BrandSitePicker;
