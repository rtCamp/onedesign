/**
 * WordPress dependencies
 */
import { useState, useMemo } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
	BaseControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useInstanceId } from '@wordpress/compose';

/**
 * External dependencies
 */
import type { SyntheticEvent } from 'react';

/**
 * Internal dependencies
 */
import { isValidUrl } from '../js/utils';

interface SiteFormData {
	name?: string;
	url?: string;
	api_key?: string;
	logo?: string;
	logo_id?: number | null;
}

interface FormErrors {
	name: string;
	url: string;
	api_key: string;
	message: string;
}

interface SubmitResponse {
	ok: boolean;
	json: () => Promise< { message?: string } >;
	data?: { status?: number };
	message?: string;
}

// Minimal typing for the `wp.media` global used for logo selection.
interface WpAttachmentJSON {
	url: string;
	id: number;
}
interface WpMediaSelection {
	first: () => { toJSON: () => WpAttachmentJSON };
	add: ( items: unknown[] ) => void;
}
interface WpMediaState {
	get: ( key: string ) => WpMediaSelection;
}
interface WpMediaFrame {
	on: ( event: string, callback: () => void ) => void;
	state: () => WpMediaState;
	open: () => void;
}
interface WpAttachmentModel {
	fetch: () => void;
}
interface WpMediaConfig {
	title: string;
	button: { text: string };
	multiple: boolean;
	library: { type: string[] };
}
interface WpMediaFactory {
	( config: WpMediaConfig ): WpMediaFrame;
	attachment: ( id: number | string ) => WpAttachmentModel;
}

interface DeleteConfirmationModalProps {
	// Used as both Button onClick and Modal onRequestClose, so the event is a
	// broad, optional SyntheticEvent.
	onConfirm: ( event?: SyntheticEvent ) => void;
	onCancel: ( event?: SyntheticEvent ) => void;
}

/**
 * Delete Confirmation Modal component.
 *
 * @param props           - Component properties.
 * @param props.onConfirm - Function to call on confirm.
 * @param props.onCancel  - Function to call on cancel.
 * @return Rendered component.
 */
const DeleteConfirmationModal = ( {
	onConfirm,
	onCancel,
}: DeleteConfirmationModalProps ): JSX.Element => (
	<Modal
		title={ __( 'Remove Site Logo', 'onedesign' ) }
		onRequestClose={ onCancel }
		isDismissible={ false }
		className="onedesign-delete-confirmation-modal"
	>
		<p>
			{ __(
				'Are you sure you want to remove this logo? This action cannot be undone.',
				'onedesign'
			) }
		</p>
		<div
			style={ {
				display: 'flex',
				justifyContent: 'flex-end',
				marginTop: '20px',
				gap: '16px',
			} }
		>
			<Button variant="secondary" onClick={ onCancel }>
				{ __( 'Cancel', 'onedesign' ) }
			</Button>
			<Button variant="primary" isDestructive onClick={ onConfirm }>
				{ __( 'Remove', 'onedesign' ) }
			</Button>
		</div>
	</Modal>
);

interface SiteModalProps {
	formData: SiteFormData;
	setFormData: ( data: SiteFormData ) => void;
	onSubmit: () => Promise< SubmitResponse | void >;
	onClose: () => void;
	editing: boolean;
	originalData?: Partial< SiteFormData >;
}

/**
 * Site Modal component for adding/editing a site.
 *
 * @param props              - Component properties.
 * @param props.formData     - Current form data.
 * @param props.setFormData  - Function to update form data.
 * @param props.onSubmit     - Function to call on form submission.
 * @param props.onClose      - Function to call on modal close.
 * @param props.editing      - Whether the modal is in editing mode.
 * @param props.originalData - Original data for comparison when editing.
 * @return Rendered component.
 */
const SiteModal = ( {
	formData,
	setFormData,
	onSubmit,
	onClose,
	editing,
	originalData = {},
}: SiteModalProps ): JSX.Element => {
	const [ errors, setErrors ] = useState< FormErrors >( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ showDeleteConfirm, setShowDeleteConfirm ] = useState( false );
	const logoControlId = useInstanceId( SiteModal, 'onedesign-site-logo' );

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url?.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onedesign' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __(
				'Enter a valid URL (must start with http or https).',
				'onedesign'
			);
		}

		const newErrors: FormErrors = {
			name: ! formData.name?.trim()
				? __( 'Site Name is required.', 'onedesign' )
				: '',
			url: siteUrlError,
			api_key: ! formData.api_key?.trim()
				? __( 'API Key is required.', 'onedesign' )
				: '',
			message: '',
		};

		// Make sure site name is under 20 characters
		if ( ( formData.name?.length ?? 0 ) > 20 ) {
			newErrors.name = __(
				'Site Name must be under 20 characters.',
				'onedesign'
			);
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${
					formData.url
				}/wp-json/onedesign/v1/health-check?timestamp=${ Date.now() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneDesign-Token': formData.api_key ?? '',
						'X-OneDesign-Source': 'Settings',
					},
				}
			);

			const healthCheckData = ( await healthCheck.json() ) as {
				success?: boolean;
			};
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __(
						'Health check failed. Please ensure the site is accessible and the api key is correct.',
						'onedesign'
					),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( submitResponse && ! submitResponse.ok ) {
				const errorData = await submitResponse.json();
				setErrors( {
					...newErrors,
					message:
						errorData.message ||
						__(
							'An error occurred while saving the site. Please try again.',
							'onedesign'
						),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message:
						submitResponse?.message ||
						__(
							'An error occurred while saving the site. Please try again.',
							'onedesign'
						),
				} );
				setShowNotice( true );
			}
		} catch {
			setErrors( {
				...newErrors,
				message: __(
					'An unexpected error occurred. Please try again.',
					'onedesign'
				),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	const handleLogoSelect = () => {
		const wpMedia = (
			window as unknown as { wp: { media: WpMediaFactory } }
		 ).wp.media;

		// Create a media frame for single image selection
		const mediaFrame = wpMedia( {
			title: __( 'Select Site Logo', 'onedesign' ),
			button: {
				text: __( 'Select Image', 'onedesign' ),
			},
			multiple: false, // Restrict to single image selection
			library: {
				type: [ 'image' ], // Only allow images
			},
		} );

		// When an image is selected, update the formData with the image data
		mediaFrame.on( 'select', () => {
			const attachment = mediaFrame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			setFormData( {
				...formData,
				logo: attachment.url,
				logo_id: attachment.id, // Store the attachment ID for future reference
			} );
		} );

		// If logo_id is already set, pre-select that image in the media library
		if ( formData.logo_id ) {
			mediaFrame.on( 'open', () => {
				const selection = mediaFrame.state().get( 'selection' );
				const attachment = wpMedia.attachment(
					formData.logo_id as number
				);

				// Fetch attachment details
				attachment.fetch();

				// Add to selection
				if ( selection && attachment ) {
					selection.add( [ attachment ] );
				}
			} );
		}

		// Open the media modal
		mediaFrame.open();
	};

	const handleLogoRemove = ( e?: SyntheticEvent ) => {
		e?.preventDefault();
		e?.stopPropagation();
		setShowDeleteConfirm( true );
	};

	const confirmLogoRemove = ( e?: SyntheticEvent ) => {
		e?.preventDefault();
		e?.stopPropagation();
		setFormData( {
			...formData,
			logo: '',
			logo_id: null,
		} );
		setShowDeleteConfirm( false );
	};

	const cancelLogoRemove = ( e?: SyntheticEvent ) => {
		e?.preventDefault();
		e?.stopPropagation();
		setShowDeleteConfirm( false );
	};

	const handleMainModalClose = () => {
		if ( ! showDeleteConfirm ) {
			onClose();
		}
	};

	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData?.name !== originalData?.name ||
			formData?.url !== originalData?.url ||
			formData?.api_key !== originalData?.api_key ||
			formData?.logo !== originalData?.logo
		);
	}, [ editing, formData, originalData ] );

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled =
		isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.api_key ||
		( editing && ! hasChanges );

	return (
		<>
			{ ! showDeleteConfirm && (
				<Modal
					title={
						editing
							? __( 'Edit Brand Site', 'onedesign' )
							: __( 'Add Brand Site', 'onedesign' )
					}
					onRequestClose={ handleMainModalClose }
					size="medium"
				>
					{ showNotice && (
						<Notice
							status="error"
							isDismissible
							onRemove={ () => setShowNotice( false ) }
						>
							{ errors.message ||
								errors.name ||
								errors.url ||
								errors.api_key }
						</Notice>
					) }

					<TextControl
						label={ __( 'Site Name*', 'onedesign' ) }
						value={ formData.name ?? '' }
						onChange={ ( value ) =>
							setFormData( { ...formData, name: value } )
						}
						help={ __(
							'This is the name of the site that will be registered.',
							'onedesign'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Site URL*', 'onedesign' ) }
						value={ formData.url ?? '' }
						onChange={ ( value ) =>
							setFormData( { ...formData, url: value } )
						}
						help={ __(
							'It must start with http or https and end with /, like: https://rtcamp.com/',
							'onedesign'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{ /* Logo Media Selection */ }
					<BaseControl
						id={ logoControlId }
						label={ __( 'Site Logo', 'onedesign' ) }
						help={ __(
							'Select a logo for this brand site.',
							'onedesign'
						) }
						__nextHasNoMarginBottom
					>
						<div style={ { marginTop: '8px' } }>
							{ formData.logo && (
								<div
									style={ {
										marginBottom: '12px',
										padding: '12px',
										border: '1px solid #ddd',
										borderRadius: '4px',
										backgroundColor: '#f9f9f9',
									} }
								>
									<img
										src={ formData.logo }
										alt={ __( 'Site Logo', 'onedesign' ) }
										style={ {
											maxWidth: '150px',
											maxHeight: '100px',
											display: 'block',
											marginBottom: '8px',
										} }
									/>
									<div
										style={ {
											display: 'flex',
											gap: '8px',
										} }
									>
										<Button
											variant="secondary"
											onClick={ handleLogoSelect }
											size="small"
										>
											{ __(
												'Replace Logo',
												'onedesign'
											) }
										</Button>
										<Button
											variant="secondary"
											onClick={ handleLogoRemove }
											size="small"
											isDestructive
										>
											{ __( 'Remove Logo', 'onedesign' ) }
										</Button>
									</div>
								</div>
							) }

							{ ! formData.logo && (
								<Button
									variant="secondary"
									onClick={ handleLogoSelect }
								>
									{ __( 'Select Logo', 'onedesign' ) }
								</Button>
							) }
						</div>
					</BaseControl>

					<TextareaControl
						label={ __( 'API Key*', 'onedesign' ) }
						value={ formData.api_key ?? '' }
						onChange={ ( value ) =>
							setFormData( { ...formData, api_key: value } )
						}
						help={ __(
							'This is the api key that will be used to authenticate the site for onedesign.',
							'onedesign'
						) }
						__nextHasNoMarginBottom
					/>

					<Button
						variant="primary"
						onClick={ handleSubmit }
						className={ isProcessing ? 'is-busy' : '' }
						disabled={ isButtonDisabled }
						style={ { marginTop: '12px' } }
					>
						{ editing
							? __( 'Update Site', 'onedesign' )
							: __( 'Add Site', 'onedesign' ) }
					</Button>
				</Modal>
			) }

			{ showDeleteConfirm && (
				<DeleteConfirmationModal
					onConfirm={ confirmLogoRemove }
					onCancel={ cancelLogoRemove }
				/>
			) }
		</>
	);
};

export default SiteModal;
