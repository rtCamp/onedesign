import {
	BaseControl,
	Button,
	Modal,
	Notice,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import type { defaultBrandSite } from '@/admin/settings/page';
import { isValidUrl, withTrailingSlash } from '@/js/utils';

interface ErrorsType {
	name: string;
	url: string;
	api_key: string;
	message: string;
}

// The parts of wp.media the logo field uses.
interface MediaFrame {
	on: ( event: 'select' | 'open', callback: () => void ) => void;
	state: () => {
		get: ( key: 'selection' ) => {
			first: () => { toJSON: () => { id: number; url: string } };
			add: ( attachments: unknown[] ) => void;
		};
	};
	open: () => void;
}

interface WpMedia {
	( options: {
		title: string;
		button: { text: string };
		multiple: boolean;
		library: { type: string[] };
	} ): MediaFrame;
	attachment: ( id: number ) => { fetch: () => void };
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
}: {
	formData: typeof defaultBrandSite;
	setFormData: ( data: typeof defaultBrandSite ) => void;
	// Resolves true on success, or false or the server's error message on failure.
	onSubmit: () => Promise< boolean | string >;
	onClose: () => void;
	editing: boolean;
	sites: ( typeof defaultBrandSite )[];
	originalData: typeof defaultBrandSite | undefined;
} ) => {
	const [ errors, setErrors ] = useState< ErrorsType >( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const logoControlId = useInstanceId( SiteModal, 'onedesign-site-logo' );

	// A blank API key means the user hasn't typed one yet.
	const typedApiKey = ( formData.api_key ?? '' ).trim();
	const needsApiKey =
		! editing ||
		withTrailingSlash( formData.url.trim() ) !==
			withTrailingSlash( originalData?.url ?? '' );

	// Check if form data has changed from original data (only for editing mode)
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
		const wpMedia = ( window as unknown as { wp: { media: WpMedia } } ).wp
			.media;

		// Create a media frame for single image selection
		const frame = wpMedia( {
			title: __( 'Select Site Logo', 'onedesign' ),
			button: { text: __( 'Select Image', 'onedesign' ) },
			multiple: false,
			library: { type: [ 'image' ] }, // Only allow images
		} );

		// When an image is selected, update the formData with the image data
		frame.on( 'select', () => {
			const attachment = frame
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

		// Open the library with the current logo already selected.
		if ( formData.logo_id ) {
			const current = wpMedia.attachment( formData.logo_id );
			frame.on( 'open', () => {
				current.fetch();
				frame.state().get( 'selection' ).add( [ current ] );
			} );
		}

		frame.open();
	};

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
		<Modal
			title={
				editing
					? __( 'Edit Brand Site', 'onedesign' )
					: __( 'Add Brand Site', 'onedesign' )
			}
			onRequestClose={ onClose }
			size="medium"
			shouldCloseOnClickOutside
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
				value={ formData.name }
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
				value={ formData.url }
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
			<BaseControl
				id={ logoControlId }
				label={ __( 'Site Logo', 'onedesign' ) }
				help={ __(
					"Shown in the brand site list and on this site's card in the pattern and template libraries. Without a logo, the site's initials are shown instead.",
					'onedesign'
				) }
				__nextHasNoMarginBottom
			>
				{ formData.logo && (
					<img
						src={ formData.logo }
						alt={ __( 'Site Logo', 'onedesign' ) }
						style={ {
							display: 'block',
							maxWidth: '150px',
							maxHeight: '100px',
							margin: '8px 0',
						} }
					/>
				) }
				<div style={ { display: 'flex', gap: '8px' } }>
					<Button variant="secondary" onClick={ handleLogoSelect }>
						{ formData.logo
							? __( 'Replace Logo', 'onedesign' )
							: __( 'Select Logo', 'onedesign' ) }
					</Button>
					{ formData.logo && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () =>
								setFormData( {
									...formData,
									logo: '',
									logo_id: null,
								} )
							}
						>
							{ __( 'Remove Logo', 'onedesign' ) }
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
	);
};

export default SiteModal;
