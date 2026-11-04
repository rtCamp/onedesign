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
import type { SyntheticEvent } from 'react';
import type { defaultBrandSite } from '@/admin/settings/page';
import { isValidUrl, withTrailingSlash } from '@/js/utils';

interface FormErrors {
	name: string;
	url: string;
	api_key: string;
	message: string;
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
	formData: typeof defaultBrandSite;
	setFormData: ( data: typeof defaultBrandSite ) => void;
	// Resolves true on success, or false or the server's error message on failure.
	onSubmit: () => Promise< boolean | string >;
	onClose: () => void;
	editing: boolean;
	sites: ( typeof defaultBrandSite )[];
	originalData: typeof defaultBrandSite | undefined;
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
 * @param props.sites        - The brand sites already added, to catch a duplicate URL.
 * @param props.originalData - Original data for comparison when editing.
 * @return Rendered component.
 */
const SiteModal = ( {
	formData,
	setFormData,
	onSubmit,
	onClose,
	editing,
	sites,
	originalData,
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

	// A blank API key means the user hasn't typed one yet.
	const typedApiKey = ( formData.api_key ?? '' ).trim();
	const needsApiKey =
		! editing ||
		withTrailingSlash( formData.url.trim() ) !==
			withTrailingSlash( originalData?.url ?? '' );

	const handleSubmit = async (): Promise< void > => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onedesign' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __(
				'Enter a valid URL (must start with http or https).',
				'onedesign'
			);
		}

		const newErrors = {
			name: ! formData.name.trim()
				? __( 'Site Name is required.', 'onedesign' )
				: '',
			url: siteUrlError,
			api_key:
				needsApiKey && ! typedApiKey
					? __( 'API Key is required.', 'onedesign' )
					: '',
			message: '',
		};

		// make sure site name is under 20 characters
		if ( formData.name.length > 20 ) {
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

		const formUrl = withTrailingSlash( formData.url );
		const isAlreadyExists = sites.some(
			( site ) =>
				withTrailingSlash( site.url ) === formUrl &&
				// Allow keeping the original url in editing mode.
				! (
					editing &&
					withTrailingSlash( originalData?.url ?? '' ) === formUrl
				)
		);

		if ( isAlreadyExists ) {
			setErrors( {
				...newErrors,
				message: __(
					'Site URL already exists. Please use a different URL.',
					'onedesign'
				),
			} );
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			const submitResponse = await onSubmit();

			if ( submitResponse !== true ) {
				setErrors( {
					...newErrors,
					message:
						typeof submitResponse === 'string' && submitResponse
							? submitResponse
							: __(
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
		} finally {
			setIsProcessing( false );
		}
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
			formData.name !== originalData?.name ||
			formData.url !== originalData?.url ||
			typedApiKey !== '' ||
			formData.logo !== originalData?.logo
		);
	}, [ editing, formData, originalData, typedApiKey ] );

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled =
		isProcessing ||
		! formData.name ||
		! formData.url ||
		( needsApiKey && ! typedApiKey ) ||
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
							'Starts with http:// or https://, like https://rtcamp.com.',
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
						label={
							needsApiKey
								? __( 'API Key*', 'onedesign' )
								: __( 'API Key', 'onedesign' )
						}
						value={ formData.api_key ?? '' }
						onChange={ ( value ) =>
							setFormData( { ...formData, api_key: value } )
						}
						help={
							needsApiKey
								? __(
										'This is the API key that will be used to authenticate the site for OneDesign.',
										'onedesign'
									)
								: __(
										'Leave blank to keep the current key.',
										'onedesign'
									)
						}
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
