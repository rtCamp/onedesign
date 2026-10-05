/**
 * WordPress dependencies
 */
import { Button, Modal, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE, CURRENT_SITE_ID } from '../js/constants';

type SiteId = number | string;

interface Site {
	id: SiteId;
	name?: string;
	url?: string;
}

interface Notice {
	type: 'error' | 'success';
	message: string;
}

// Brand-site references may be unsaved rows (no id yet), so keep it loose.
interface BrandSiteRef {
	id?: SiteId;
	name?: string;
	url?: string;
}

interface MultiSitesProps {
	setBrandSites: ( sites: BrandSiteRef[] ) => void;
	brandSites: BrandSiteRef[];
	setNotice: ( notice: Notice ) => void;
}

/**
 * MultiSites component to manage brand sites from multisite network.
 *
 * @param props               - Component properties.
 * @param props.setBrandSites - Function to set brand sites in parent component.
 * @param props.brandSites    - Current list of brand sites.
 * @param props.setNotice     - Function to set notice messages.
 *
 * @return Rendered component.
 */
const MultiSites = ( {
	setBrandSites,
	brandSites,
	setNotice,
}: MultiSitesProps ): JSX.Element => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ selectedSites, setSelectedSites ] = useState< SiteId[] >( [] );
	const [ isApplying, setIsApplying ] = useState( false );
	const [ sites, setSites ] = useState< Site[] | undefined >();

	const openModal = () => setIsOpen( true );
	const closeModal = () => setIsOpen( false );

	const toggleSiteSelection = ( siteId: SiteId ) => {
		setSelectedSites( ( prevSelected ) => {
			if ( prevSelected.includes( siteId ) ) {
				return prevSelected.filter( ( id ) => id !== siteId );
			}
			return [ ...prevSelected, siteId ];
		} );
	};

	const fetchSelectedBrandSites = useCallback( async () => {
		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': NONCE,
				},
			} );

			if ( response.ok ) {
				const data = ( await response.json() ) as {
					shared_sites?: Site[];
				};
				setBrandSites( data.shared_sites || [] );

				// if shared_sites length is 1 meaning
				if (
					( data?.shared_sites?.length ?? 0 ) > 0 &&
					brandSites?.length === 0
				) {
					window.location.reload();
				}
			} else {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to fetch selected brand sites. Please try again.',
						'onedesign'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'An error occurred while fetching selected brand sites. Please try again.',
					'onedesign'
				),
			} );
		}
	}, [ brandSites, setBrandSites, setNotice ] );

	const fetchBrandSites = useCallback( async () => {
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/multisite/sites`,
				{
					headers: {
						'Content-Type': 'application/json',
						'X-WP-NONCE': NONCE,
					},
				}
			);

			if ( response.ok ) {
				const data = ( await response.json() ) as { sites?: Site[] };
				setSites( data.sites || [] );
			} else {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to fetch brand sites. Please try again.',
						'onedesign'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'An error occurred while fetching brand sites. Please try again.',
					'onedesign'
				),
			} );
		}
	}, [ setNotice ] );

	const handleMultiSiteAdd = useCallback(
		async ( selectedMUSites: SiteId[] ) => {
			setIsApplying( true );
			try {
				const response = await fetch(
					`${ API_NAMESPACE }/multisite/add-sites`,
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': NONCE,
						},
						body: JSON.stringify( { site_ids: selectedMUSites } ),
					}
				);

				if ( ! response.ok ) {
					setNotice( {
						type: 'error',
						message: __(
							'Failed to add selected sites. Please try again.',
							'onedesign'
						),
					} );
					setIsApplying( false );
					return;
				}

				const data = ( await response.json() ) as {
					success?: boolean;
					message?: string;
				};

				if ( data.success ) {
					setNotice( {
						type: 'success',
						message: __(
							'Selected sites added successfully.',
							'onedesign'
						),
					} );
					fetchSelectedBrandSites();
					setSelectedSites( [] );
				} else {
					setNotice( {
						type: 'error',
						message:
							data.message ||
							__(
								'Failed to add selected sites. Please try again.',
								'onedesign'
							),
					} );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __(
						'An error occurred while adding selected sites. Please try again.',
						'onedesign'
					),
				} );
			} finally {
				setIsApplying( false );
			}
		},
		[ setNotice, fetchSelectedBrandSites ]
	);

	useEffect( () => {
		fetchBrandSites();
	}, [ fetchBrandSites ] );

	const availableSites =
		sites?.filter(
			( site ) =>
				String( site.id ) !== CURRENT_SITE_ID &&
				! brandSites?.some(
					( brandSite ) =>
						String( brandSite.id ) === String( site.id )
				)
		) ?? [];

	return (
		<>
			<Button variant="secondary" onClick={ openModal }>
				{ __( 'Add Brand Sites From Current MU', 'onedesign' ) }
			</Button>

			{ isOpen && (
				<Modal
					title={ __( 'Multi-Sites Information', 'onedesign' ) }
					onRequestClose={ closeModal }
					size="medium"
				>
					{ /* create multi select checkbox list of sites excluding current site */ }
					{ ( sites?.length ?? 0 ) > 0 ? (
						<div
							style={ {
								maxHeight: '400px',
								overflowY: 'auto',
								padding: '4px 4px',
							} }
						>
							{ availableSites.map( ( site ) => (
								<CheckboxControl
									key={ site.id }
									label={ `${ site.name } ( ${ site?.url } )` }
									checked={ selectedSites.includes(
										site.id
									) }
									onChange={ () =>
										toggleSiteSelection( site.id )
									}
									__nextHasNoMarginBottom
								/>
							) ) }
						</div>
					) : (
						<p>
							{ __(
								'No other sites available in this multisite network.',
								'onedesign'
							) }
						</p>
					) }

					{ ( sites?.length ?? 0 ) > 0 &&
						availableSites.length === 0 && (
							<p>
								{ __(
									'All sites in this multisite network have already been added as brand sites.',
									'onedesign'
								) }
							</p>
						) }

					<div
						style={ {
							marginTop: '20px',
							display: 'flex',
							justifyContent: 'flex-end',
							gap: '10px',
						} }
					>
						<Button variant="secondary" onClick={ closeModal }>
							{ __( 'Cancel', 'onedesign' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								handleMultiSiteAdd( selectedSites );
								closeModal();
							} }
							disabled={ selectedSites.length === 0 }
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
