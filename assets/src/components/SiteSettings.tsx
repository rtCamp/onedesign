import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	TextareaControl,
	Button,
	Card,
	Notice,
	Spinner,
	CardHeader,
	CardBody,
	TextControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import type { NoticeType } from '@/admin/settings/page';

const API_KEY = window.OneDesignSettings.api_key;

/**
 * SiteSettings component for managing API key and governing site connection.
 *
 * @return Rendered component.
 */
const SiteSettings = (): JSX.Element => {
	const [ apiKey, setApiKey ] = useState( API_KEY );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );
	const [ governingSite, setGoverningSite ] = useState( '' );
	const [ showDisconnectionModal, setShowDisconnectionModal ] =
		useState( false );

	// The admin nonce authenticates these same-site requests.
	const regenerateApiKey = useCallback( async () => {
		try {
			const data = await apiFetch< { secret_key?: string } >( {
				path: '/onedesign/v1/secret-key',
				method: 'POST',
			} );
			if ( data?.secret_key ) {
				setApiKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __(
						'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.',
						'onedesign'
					),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to regenerate API key. Please try again later.',
						'onedesign'
					),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error as { message?: string } )?.message ||
					__(
						'Error regenerating API key. Please try again later.',
						'onedesign'
					),
			} );
		}
	}, [] );

	const fetchCurrentGoverningSite = useCallback( async () => {
		try {
			const data = await apiFetch< {
				onedesign_parent_site_url?: string;
			} >( { path: '/wp/v2/settings' } );
			setGoverningSite( data?.onedesign_parent_site_url || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error as { message?: string } )?.message ||
					__(
						'Failed to fetch governing site. Please try again later.',
						'onedesign'
					),
			} );
		}
	}, [] );

	const deleteGoverningSiteConnection = useCallback( async () => {
		try {
			const data = await apiFetch< {
				governing_site_notified?: boolean;
				message: string;
			} >( {
				path: '/onedesign/v1/governing-site',
				method: 'DELETE',
			} );
			setGoverningSite( '' );

			// Disconnected locally, but the governing site may still list this site.
			if ( data?.governing_site_notified === false ) {
				setNotice( { type: 'warning', message: data.message } );
				return;
			}

			setNotice( {
				type: 'success',
				message: __(
					'Governing site disconnected successfully.',
					'onedesign'
				),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error as { message?: string } )?.message ||
					__(
						'Failed to disconnect governing site. Please try again later.',
						'onedesign'
					),
			} );
		} finally {
			setShowDisconnectionModal( false );
		}
	}, [] );

	const handleDisconnectGoverningSite = useCallback( async () => {
		setShowDisconnectionModal( true );
	}, [] );

	// Load once on mount.
	useEffect( () => {
		fetchCurrentGoverningSite().finally( () => setIsLoading( false ) );
	}, [ fetchCurrentGoverningSite ] );

	if ( isLoading ) {
		return <Spinner />;
	}

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

			<Card style={ { marginTop: '30px' } }>
				<CardHeader>
					<h2>{ __( 'API Key', 'onedesign' ) }</h2>
					<div>
						{ /* Copy to clipboard button */ }
						<Button
							variant="primary"
							onClick={ () => {
								(
									navigator.clipboard?.writeText( apiKey ) ??
									Promise.reject( '' )
								)
									.then( () => {
										setNotice( {
											type: 'success',
											message: __(
												'API key copied to clipboard.',
												'onedesign'
											),
										} );
									} )
									.catch( ( error ) => {
										setNotice( {
											type: 'error',
											message:
												__(
													'Failed to copy api key. Please try again.',
													'onedesign'
												) +
												' ' +
												error,
										} );
									} );
							} }
						>
							{ __( 'Copy API Key', 'onedesign' ) }
						</Button>
						{ /* Regenerate key button */ }
						<Button
							variant="secondary"
							onClick={ regenerateApiKey }
							style={ { marginLeft: '10px' } }
						>
							{ __( 'Regenerate API Key', 'onedesign' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div>
						<TextareaControl
							label={ __( 'API Key', 'onedesign' ) }
							hideLabelFromVision
							value={ apiKey }
							readOnly
							help={ __(
								'This key is used for secure communication with the Governing site.',
								'onedesign'
							) }
							__nextHasNoMarginBottom
							onChange={ () => {} } // to avoid ts warning
						/>
					</div>
				</CardBody>
			</Card>
			<Card
				className="governing-site-connection"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'Governing Site Connection', 'onedesign' ) }</h2>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDisconnectGoverningSite }
						disabled={
							governingSite.trim().length === 0 || isLoading
						}
					>
						{ __( 'Disconnect Governing Site', 'onedesign' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'onedesign' ) }
						value={ governingSite }
						disabled
						help={ __(
							'This is the URL of the Governing site this Brand site is connected to.',
							'onedesign'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ () => {} } // to avoid ts warning
					/>
				</CardBody>
			</Card>

			{ showDisconnectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'onedesign' ) }
					onRequestClose={ () => setShowDisconnectionModal( false ) }
					shouldCloseOnClickOutside
				>
					<p>
						{ __(
							'Are you sure you want to disconnect from the governing site? This action cannot be undone.',
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
						<Button
							variant="secondary"
							onClick={ () => setShowDisconnectionModal( false ) }
						>
							{ __( 'Cancel', 'onedesign' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ deleteGoverningSiteConnection }
						>
							{ __( 'Disconnect', 'onedesign' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default SiteSettings;
