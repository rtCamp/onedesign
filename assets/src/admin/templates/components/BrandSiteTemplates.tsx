/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import MemoizedTemplatePreview from './MemoizedTemplatePreview';
import { API_NAMESPACE as REST_NAMESPACE, NONCE } from '../../../js/constants';

type TemplateId = number | string;

interface Template {
	id?: TemplateId;
	name?: string;
	original_id?: TemplateId;
	[ key: string ]: unknown;
}

interface NoticeState {
	type: 'error' | 'success';
	message: string;
}

interface BrandSiteTemplatesProps {
	filteredTemplates: Template[];
	currentPage: number;
	PER_PAGE: number;
	selectedTemplates: TemplateId[];
	handleTemplateSelection: ( id?: TemplateId ) => void;
	setCurrentPage: ( value: number | ( ( prev: number ) => number ) ) => void;
	currentSiteId: TemplateId;
	fetchConnectedSitesTemplates: () => void;
	setSelectedTemplates: ( templates: TemplateId[] ) => void;
	allTemplates: Template[];
	notice: NoticeState | null;
	setNotice: ( notice: NoticeState | null ) => void;
}

/**
 * BrandSiteTemplates component.
 *
 * @param props                              - Component props.
 * @param props.filteredTemplates            - Array of filtered templates to display.
 * @param props.currentPage                  - Current page number for pagination.
 * @param props.PER_PAGE                     - Number of templates to display per page.
 * @param props.selectedTemplates            - Array of selected template IDs.
 * @param props.handleTemplateSelection      - Function to handle template selection.
 * @param props.setCurrentPage               - Function to set the current page number.
 * @param props.currentSiteId                - Current brand site ID.
 * @param props.fetchConnectedSitesTemplates - Function to fetch templates for connected brand sites.
 * @param props.setSelectedTemplates         - Function to set selected templates.
 * @param props.allTemplates                 - Array of all available templates.
 * @param props.notice                       - Notice object containing type and message.
 * @param props.setNotice                    - Function to set the notice state.
 *
 * @return The rendered component.
 */
const BrandSiteTemplates = ( {
	filteredTemplates,
	currentPage,
	PER_PAGE,
	selectedTemplates,
	handleTemplateSelection,
	setCurrentPage,
	currentSiteId,
	fetchConnectedSitesTemplates,
	setSelectedTemplates,
	allTemplates,
	notice,
	setNotice,
}: BrandSiteTemplatesProps ): JSX.Element => {
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ isRemoveModalOpen, setIsRemoveModalOpen ] = useState( false );

	const handleRemoveTemplates = useCallback( async () => {
		setIsProcessing( true );
		try {
			const response = await fetch(
				`${ REST_NAMESPACE }/templates/remove`,
				{
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						template_ids: selectedTemplates,
						site: currentSiteId.toString(),
						is_remove_all: false,
					} ),
				}
			);
			const data = ( await response.json() ) as { success?: boolean };
			if ( data.success ) {
				fetchConnectedSitesTemplates();

				const count = selectedTemplates.length;

				setNotice( {
					type: 'success',
					message: sprintf(
						/* translators: %d: Number of templates removed. */
						_n(
							'%d template removed successfully.',
							'%d templates removed successfully.',
							count,
							'onedesign'
						),
						count
					),
				} );
				setSelectedTemplates( [] );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to remove templates.', 'onedesign' ),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error instanceof Error ? error.message : '' ) ||
					__(
						'An error occurred while removing templates.',
						'onedesign'
					),
			} );
		} finally {
			setIsProcessing( false );
		}
	}, [
		selectedTemplates,
		currentSiteId,
		fetchConnectedSitesTemplates,
		setSelectedTemplates,
		setNotice,
	] );

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
						isDestructive
						disabled={ selectedTemplates.length === 0 }
						onClick={ () => {
							setIsRemoveModalOpen( true );
						} }
					>
						{ __( 'Remove Template', 'onedesign' ) }
					</Button>
				</div>
			</div>
		);
	};

	const renderTemplates = () => {
		if ( filteredTemplates.length === 0 ) {
			return (
				<div className="onedesign-no-templates">
					<p>{ __( 'No templates found.', 'onedesign' ) }</p>
				</div>
			);
		}

		return (
			<div className="onedesign-templates-grid">
				{ filteredTemplates
					.slice( 0, currentPage * PER_PAGE )
					.map( ( template ) => {
						return (
							<MemoizedTemplatePreview
								key={ template?.name }
								template={
									allTemplates.find(
										( t ) => t.id === template.original_id
									) || template
								}
								isCheckBoxRequired
								onSelect={ () =>
									handleTemplateSelection( template?.id )
								}
								isSelected={ selectedTemplates.includes(
									template?.id ?? ''
								) }
							/>
						);
					} ) }
			</div>
		);
	};

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			{ renderTemplates() }
			{ renderPagination() }
			{ isRemoveModalOpen && (
				<Modal
					title={ __( 'Remove Template', 'onedesign' ) }
					onRequestClose={ () => {
						setIsRemoveModalOpen( false );
					} }
					size="medium"
				>
					<p>
						{ __(
							'Are you sure your want to remove selected templates?',
							'onedesign'
						) }
						<br />
						{ __(
							'Once you removed template it might break things on brand site so please check and confirm template is not in active use.',
							'onedesign'
						) }
					</p>

					<div
						style={ {
							display: 'flex',
							flexDirection: 'row',
							gap: '12px',
							justifyContent: 'flex-end',
							alignItems: 'center',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => {
								setIsRemoveModalOpen( false );
							} }
							label={ __( 'Cancel', 'onedesign' ) }
						>
							{ __( 'Cancel', 'onedesign' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								handleRemoveTemplates();
								setIsRemoveModalOpen( false );
							} }
							isDestructive
							isBusy={ isProcessing }
							disabled={ isProcessing }
							label={ __( 'Remove Templates', 'onedesign' ) }
						>
							{ __( 'Remove Templates', 'onedesign' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default BrandSiteTemplates;
