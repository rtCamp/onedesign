/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
} from '@wordpress/components';
import {
	createRoot,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, MULTISITES, NONCE } from '../../js/constants';

type SiteId = number | string;

interface NoticeState {
	type: 'error' | 'success';
	message: string;
}

interface SiteTypeSelectorProps {
	value: string;
	setGoverningSite: ( value: string ) => void;
}

const GOVERNING_SITE_OPTIONS: Array< { label: string; value: string } > = [
	{ label: __( 'Select…', 'onedesign' ), value: '' },
	...( MULTISITES as Array< { name?: string; id?: SiteId } > ).map(
		( site ) => ( {
			label: site.name ?? '',
			value: String( site.id ?? '' ),
		} )
	),
];

/**
 * SiteTypeSelector component for selecting site type.
 *
 * @param props                  - Component properties.
 * @param props.value            - Current selected value.
 * @param props.setGoverningSite - Function to set governing site.
 *
 * @return Rendered component.
 */
const SiteTypeSelector = ( {
	value,
	setGoverningSite,
}: SiteTypeSelectorProps ): JSX.Element => (
	<SelectControl
		label={ __( 'Select Governing Site', 'onedesign' ) }
		value={ value }
		help={ __(
			'Choose governing site from current multisite network. Other sites will be set as brand sites. This setting cannot be changed later and affects available features and configurations.',
			'onedesign'
		) }
		onChange={ ( v ) => {
			setGoverningSite( v );
		} }
		options={ GOVERNING_SITE_OPTIONS }
	/>
);

/**
 * Site type selection component for OneDesign Multisite setup.
 *
 * @return Rendered component.
 */
const OneDesignMultisiteGoverningSiteSelection = (): JSX.Element => {
	const [ governingSite, setGoverningSite ] = useState( '' );
	const currentGoverningSiteID = useRef( '' );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isSaving, setIsSaving ] = useState( false );

	const fetchCurrentGoverningSite = useCallback( async () => {
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/multisite/governing-site`,
				{
					headers: {
						'Content-Type': 'application/json',
						'X-WP-NONCE': NONCE,
					},
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Error fetching current governing site.',
						'onedesign'
					),
				} );
				return;
			}

			// The REST controller returns `governing_site` as an integer.
			const data = (await response.json()) as {
				governing_site?: SiteId;
			};
			if ( data?.governing_site ) {
				const siteId = String(data.governing_site);
				setGoverningSite(siteId);
				currentGoverningSiteID.current = siteId;
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error fetching current governing site.',
					'onedesign'
				),
			} );
		}
	}, [] );

	useEffect( () => {
		fetchCurrentGoverningSite();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleGoverningSiteChange = useCallback( async ( value: string ) => {
		setGoverningSite(value);
		setIsSaving( true );

		try {
			const response = await fetch(
				`${ API_NAMESPACE }/multisite/governing-site`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-NONCE': NONCE,
					},
					body: JSON.stringify( { governing_site_id: value } ),
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Error setting governing site.', 'onedesign' ),
				} );
				setIsSaving( false );
				return;
			}

			currentGoverningSiteID.current = value;

			setNotice( {
				type: 'success',
				message: __(
					'Governing site updated successfully.',
					'onedesign'
				),
			} );

			setTimeout( () => {
				setIsSaving( false );
				window.location.reload();
			}, 1000 );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting governing site.', 'onedesign' ),
			} );
		} finally {
			setIsSaving( false );
		}
	}, [] );

	return (
		<>
			<Card>
				<>
					{ ( notice?.message?.length ?? 0 ) > 0 && (
						<Notice
							status={ notice?.type ?? 'success' }
							isDismissible
							onRemove={ () => setNotice( null ) }
						>
							{ notice?.message }
						</Notice>
					) }
				</>
				<CardHeader>
					<h2>{ __( 'OneDesign', 'onedesign' ) }</h2>
				</CardHeader>
				<CardBody>
					<SiteTypeSelector
						value={ governingSite }
						setGoverningSite={ setGoverningSite }
					/>
					<Button
						variant="primary"
						onClick={ () =>
							handleGoverningSiteChange( governingSite )
						}
						disabled={
							isSaving ||
							governingSite.trim().length === 0 ||
							governingSite === currentGoverningSiteID.current
						}
						style={ { marginTop: '1.5rem' } }
						isBusy={ isSaving }
					>
						{ __( 'Select Governing Site', 'onedesign' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
};

// Render to Gutenberg admin page with ID: onedesign-multisite-selection-modal
const target = document.getElementById( 'onedesign-multisite-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneDesignMultisiteGoverningSiteSelection /> );
}
