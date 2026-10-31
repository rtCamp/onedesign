import { Button, Modal, CheckboxControl, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import type { BrandSite, NoticeType } from '@/admin/settings/page';
import type { OneDesignMultisite } from '@/types/global';

interface MultiSitesProps {
	governingSiteId: string;
	sites: OneDesignMultisite[];
	onAdd: ( sharedSites: BrandSite[], addedIds: string[] ) => void;
	setNotice: ( notice: NoticeType ) => void;
	label?: string;
	disabled?: boolean;
	children?: ReactNode;
}

/**
 * MultiSites component to add the network's sites to a governing site's brand sites.
 *
 * @param props                 - Component properties.
 * @param props.governingSiteId - The governing site to add them to.
 * @param props.sites           - The network sites that can be added.
 * @param props.onAdd           - Gets the governing site's brand sites and the ids of the added sites.
 * @param props.setNotice       - Function to set notice messages.
 * @param props.label           - The label of the button that opens the picker.
 * @param props.disabled        - Whether the button that opens the picker is disabled.
 * @param props.children        - Shown in the picker above the sites.
 *
 * @return Rendered component.
 */
const MultiSites = ( {
	governingSiteId,
	sites,
	onAdd,
	setNotice,
	label = __( 'Add Brand Sites From This Network', 'onedesign' ),
	disabled = false,
	children,
}: MultiSitesProps ): JSX.Element => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ selectedSites, setSelectedSites ] = useState< string[] >( [] );
	const [ isApplying, setIsApplying ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const openModal = () => setIsOpen( true );
	const closeModal = () => {
		setIsOpen( false );
		setSelectedSites( [] );
		setError( null );
	};

	const toggleSiteSelection = ( siteId: string ) => {
		setSelectedSites( ( prevSelected ) => {
			if ( prevSelected.includes( siteId ) ) {
				return prevSelected.filter( ( id ) => id !== siteId );
			}
			return [ ...prevSelected, siteId ];
		} );
	};

	const handleMultiSiteAdd = async () => {
		setIsApplying( true );
		setError( null );
		try {
			const data = await apiFetch< { shared_sites: BrandSite[] } >( {
				path: '/onedesign/v1/multisite/brand-sites',
				method: 'POST',
				data: {
					governing_site_id: Number( governingSiteId ),
					site_ids: selectedSites.map( Number ),
				},
			} );

			const addedNames = sites
				.filter( ( site ) => selectedSites.includes( site.id ) )
				.map( ( site ) => site.name );

			onAdd( data.shared_sites, selectedSites );
			closeModal();
			setNotice( {
				type: 'success',
				message: sprintf(
					/* translators: %s: Names of the sites that were added. */
					_n(
						'%s is now a brand site.',
						'%s are now brand sites.',
						addedNames.length,
						'onedesign'
					),
					addedNames.join( ', ' )
				),
			} );
		} catch ( addError ) {
			setError(
				( addError as { message?: string } )?.message ||
					__(
						"Couldn't add the selected sites. Try again.",
						'onedesign'
					)
			);
		} finally {
			setIsApplying( false );
		}
	};

	return (
		<>
			<Button
				variant="secondary"
				onClick={ openModal }
				disabled={ disabled }
			>
				{ label }
			</Button>

			{ isOpen && (
				<Modal
					title={ __(
						'Select Sites From This Network',
						'onedesign'
					) }
					onRequestClose={ closeModal }
					shouldCloseOnClickOutside={ ! isApplying }
					shouldCloseOnEsc={ ! isApplying }
					size="medium"
				>
					{ error && (
						<Notice
							status="error"
							isDismissible={ false }
							className="onedesign-form-error"
						>
							{ error }
						</Notice>
					) }
					{ children }
					<div
						style={ {
							maxHeight: '400px',
							overflowY: 'auto',
							padding: '4px 4px',
						} }
					>
						{ sites.map( ( site ) => (
							<CheckboxControl
								key={ site.id }
								label={ `${ site.name } ( ${ site.url } )` }
								checked={ selectedSites.includes( site.id ) }
								onChange={ () =>
									toggleSiteSelection( site.id )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>

					<div
						style={ {
							marginTop: '20px',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: '10px',
						} }
					>
						<Button
							variant="secondary"
							onClick={ closeModal }
							disabled={ isApplying }
						>
							{ __( 'Cancel', 'onedesign' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleMultiSiteAdd }
							disabled={
								! governingSiteId ||
								selectedSites.length === 0 ||
								isApplying
							}
							isBusy={ isApplying }
						>
							{ __( 'Add Selected Sites', 'onedesign' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default MultiSites;
