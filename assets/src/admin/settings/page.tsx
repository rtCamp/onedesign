import { useState, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import SiteTable from '@/components/SiteTable';
import SiteModal from '@/components/SiteModal';
import SiteSettings from '@/components/SiteSettings';
import SiteTypeSettings from '@/components/SiteTypeSettings';
import type { SiteType } from '@/types/global';

export interface NoticeType {
	// Set by the settings page, so each notice gets its own Snackbar.
	id?: number;
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	// Only set while the admin types a key.
	api_key?: string;
	logo?: string;
	logo_id?: number | null;
}

export const defaultBrandSite: BrandSite = {
	name: '',
	url: '',
	api_key: '',
};

export type EditingIndex = number | null;

const NONCE = window.OneDesignSettings.nonce;
const SITE_TYPE = ( window.OneDesignSettings.siteType as SiteType ) || '';
const SHARED_SITES_ENDPOINT = '/onedesign/v1/shared-sites';

/**
 * Create NONCE middleware for apiFetch
 */
apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

interface DeleteSiteResponse {
	governing_site_notified?: boolean;
	message?: string;
}

/**
 * Settings page component for OneDesign plugin.
 *
 * @return Rendered component.
 */
const OneDesignSettingsPage = (): JSX.Element => {
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState< EditingIndex >( null );
	const [ sites, setSites ] = useState< BrandSite[] >(
		window.OneDesignSettings.sharedSites ?? []
	);
	const [ formData, setFormData ] = useState< BrandSite >( defaultBrandSite );
	const [ notice, setNoticeState ] = useState< NoticeType | null >( null );
	const noticeId = useRef( 0 );

	// A new id remounts the Snackbar, which restarts its timer for the new notice.
	const setNotice = useCallback( ( next: NoticeType | null ) => {
		noticeId.current += 1;
		setNoticeState( next ? { ...next, id: noticeId.current } : null );
	}, [] );

	// Kept until dismissed, since each one asks the admin to finish disconnecting a brand site.
	const [ disconnectNotices, setDisconnectNotices ] = useState<
		NoticeType[]
	>( [] );

	const handleFormSubmit = async (): Promise< boolean | string > => {
		const updated: BrandSite[] =
			editingIndex !== null
				? sites.map( ( item, i ) =>
						i === editingIndex ? formData : item
					)
				: [ ...sites, formData ];

		try {
			const data = await apiFetch< { shared_sites?: BrandSite[] } >( {
				path: SHARED_SITES_ENDPOINT,
				method: 'POST',
				data: { sites_data: updated },
			} );

			if ( ! data?.shared_sites ) {
				return false;
			}

			setSites( data.shared_sites );
			setFormData( defaultBrandSite );
			setShowModal( false );
			setEditingIndex( null );

			if ( data.shared_sites.length === 0 ) {
				// Reloading causes the menus etc to reflect the missing sites.
				window.location.reload();
			}

			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onedesign' ),
			} );
			return true;
		} catch ( error ) {
			// The modal stays open with the server's message, so the admin can fix the input and retry.
			return ( error as { message?: string } )?.message || false;
		}
	};

	const handleDelete = async ( index: number ): Promise< void > => {
		const removedSite = sites[ index ];

		if ( ! removedSite ) {
			return;
		}

		let response: DeleteSiteResponse;

		try {
			response = await apiFetch< DeleteSiteResponse >( {
				path: `${ SHARED_SITES_ENDPOINT }/${ encodeURIComponent(
					removedSite.id ?? ''
				) }`,
				method: 'DELETE',
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error as { message?: string } )?.message ||
					__( 'Failed to update shared sites', 'onedesign' ),
			} );
			return;
		}

		setSites( ( current ) =>
			current.filter( ( site ) => site.id !== removedSite.id )
		);

		if ( response?.governing_site_notified === false && response.message ) {
			const disconnectNotice: NoticeType = {
				type: 'warning',
				message: response.message,
			};

			setDisconnectNotices( ( notices ) => [
				...notices.filter(
					( item ) => item.message !== disconnectNotice.message
				),
				disconnectNotice,
			] );
		}

		if ( response?.governing_site_notified && response.message ) {
			setNotice( { type: 'success', message: response.message } );
		}
	};

	return (
		<>
			{ !! notice && notice?.message?.length > 0 && (
				<Snackbar
					key={ notice.id }
					onRemove={ () => setNotice( null ) }
					className={
						notice?.type === 'error'
							? 'onedesign-error-notice'
							: 'onedesign-success-notice'
					}
				>
					{ notice?.message }
				</Snackbar>
			) }

			{ disconnectNotices.map( ( disconnectNotice ) => (
				<Notice
					key={ disconnectNotice.message }
					status={ disconnectNotice.type }
					isDismissible
					onRemove={ () =>
						setDisconnectNotices( ( notices ) =>
							notices.filter(
								( item ) => item !== disconnectNotice
							)
						)
					}
				>
					{ disconnectNotice.message }
				</Notice>
			) ) }

			{ ! SITE_TYPE && <SiteTypeSettings /> }

			{ SITE_TYPE === 'brand-site' && <SiteSettings /> }

			{ SITE_TYPE === 'governing-site' && (
				<SiteTable
					sites={ sites }
					onEdit={ setEditingIndex }
					onDelete={ handleDelete }
					setFormData={ setFormData }
					setShowModal={ setShowModal }
					setSites={ setSites }
					setNotice={ setNotice }
				/>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( defaultBrandSite );
					} }
					editing={ editingIndex !== null }
					sites={ sites }
					originalData={
						editingIndex !== null
							? sites[ editingIndex ]
							: undefined
					}
				/>
			) }
		</>
	);
};

export default OneDesignSettingsPage;
