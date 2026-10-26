import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import SiteTable from '@/components/SiteTable';
import SiteModal from '@/components/SiteModal';
import SiteSettings from '@/components/SiteSettings';
import SiteTypeSettings from '@/components/SiteTypeSettings';
import { API_NAMESPACE } from '@/js/utils';
import type { SiteType } from '@/types/global';

export interface NoticeType {
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
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

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

	const handleDelete = async ( index: number ) => {
		const updated = sites.filter( ( _, i ) => i !== index );
		const token = NONCE;

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to delete Brand site. Please try again.',
						'onedesign'
					),
				} );
				return;
			}
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'onedesign' ),
			} );
			setSites( updated );
			if ( updated.length === 0 ) {
				window.location.reload();
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error deleting Brand site. Please try again later.',
					'onedesign'
				),
			} );
		}
	};

	return (
		<>
			{ ( notice?.message?.length ?? 0 ) > 0 && (
				<Snackbar
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
