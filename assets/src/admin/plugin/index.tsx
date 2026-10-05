/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	API_NAMESPACE,
	NONCE,
	API_KEY,
	SETTINGS_LINK,
} from '../../js/constants';

interface NoticeState {
	type: 'error' | 'success';
	message: string;
}

interface SiteTypeSelectorProps {
	value: string;
	setSiteType: ( value: string ) => void;
}

/**
 * SiteTypeSelector component for selecting site type.
 *
 * @param props             - Component properties.
 * @param props.value       - Current selected site type.
 * @param props.setSiteType - Function to update the selected site type.
 * @return Rendered component.
 */
const SITE_TYPE_OPTIONS: Array< { label: string; value: string } > = [
	{ label: __( 'Select…', 'onedesign' ), value: '' },
	{ label: __( 'Brand Site', 'onedesign' ), value: 'brand-site' },
	{
		label: __( 'Governing Site', 'onedesign' ),
		value: 'governing-site',
	},
];

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: SiteTypeSelectorProps ): JSX.Element => (
	<SelectControl
		label={ __( 'Site Type', 'onedesign' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This setting cannot be changed later and affects available features and configurations.",
			'onedesign'
		) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ SITE_TYPE_OPTIONS }
	/>
);

/**
 * Site type selection component for OneDesign setup.
 *
 * @return Rendered component.
 */
const OneDesignSiteTypeSelection = (): JSX.Element => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		const token = NONCE;

		const fetchData = async () => {
			try {
				const [ siteTypeRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneDesign-Token': API_KEY,
						},
					} ),
				] );

				const siteTypeData = ( await siteTypeRes.json() ) as {
					site_type?: string;
				};

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __(
						'Error fetching site type or Brand sites.',
						'onedesign'
					),
				} );
			}
		};

		fetchData();
	}, [] );

	const handleSiteTypeChange = async ( value: string ) => {
		setSiteType( value );
		const token = NONCE;
		setIsSaving( true );

		try {
			const response = await fetch( `${ API_NAMESPACE }/site-type`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneDesign-Token': API_KEY,
				},
				body: JSON.stringify( { site_type: value } ),
			} );

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Error setting site type.', 'onedesign' ),
				} );
				return;
			}

			const data = ( await response.json() ) as { site_type?: string };
			if ( data?.site_type ) {
				setSiteType( data.site_type );

				// redirect user to setup page.
				window.location.href = SETTINGS_LINK;
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'onedesign' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

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
						value={ siteType }
						setSiteType={ setSiteType }
					/>
					<Button
						variant="primary"
						onClick={ () => handleSiteTypeChange( siteType ) }
						disabled={ isSaving || siteType.trim().length === 0 }
						style={ { marginTop: '1.5rem' } }
						className={ isSaving ? 'is-busy' : '' }
					>
						{ __( 'Select Current Site Type', 'onedesign' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
};

// Render to Gutenberg admin page with ID: onedesign-site-selection-modal
const target = document.getElementById( 'onedesign-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneDesignSiteTypeSelection /> );
}
