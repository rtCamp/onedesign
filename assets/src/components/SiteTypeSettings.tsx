import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';
import type { SiteType } from '@/types/global';

const BRAND_SITE = 'brand-site' as const;
const GOVERNING_SITE = 'governing-site' as const;

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: {
	value: SiteType;
	setSiteType: ( v: SiteType ) => void;
} ) => (
	<SelectControl
		label={ __( 'Site Type', 'onedesign' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This determines which settings are available on this site.",
			'onedesign'
		) }
		onChange={ ( v: SiteType ) => {
			setSiteType( v );
		} }
		__nextHasNoMarginBottom
		__next40pxDefaultSize
		options={ [
			{ label: __( 'Select…', 'onedesign' ), value: '' },
			{ label: __( 'Brand Site', 'onedesign' ), value: BRAND_SITE },
			{
				label: __( 'Governing site', 'onedesign' ),
				value: GOVERNING_SITE,
			},
		] }
	/>
);

const SiteTypeSettings = () => {
	const [ siteType, setSiteType ] = useState< SiteType >( '' );
	const [ error, setError ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );

	const handleSiteTypeChange = async ( value: SiteType ) => {
		setIsSaving( true );

		try {
			await apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onedesign_site_type: value },
			} );

			// Reload so the settings screen and menu match the new site type.
			window.location.reload();
		} catch ( err ) {
			setError(
				( err as { message?: string } )?.message ||
					__( 'Error setting site type.', 'onedesign' )
			);
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			{ !! error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( '' ) }
				>
					{ error }
				</Notice>
			) }

			<CardHeader>
				<h2>{ __( 'OneDesign', 'onedesign' ) }</h2>
			</CardHeader>

			<CardBody>
				<SiteTypeSelector
					value={ siteType }
					setSiteType={ setSiteType }
				/>
				<p>
					<strong>{ __( 'Governing site', 'onedesign' ) }</strong>:{ ' ' }
					{ __(
						'shares its patterns and templates with the brand sites it connects.',
						'onedesign'
					) }
					<br />
					<strong>{ __( 'Brand site', 'onedesign' ) }</strong>:{ ' ' }
					{ __(
						'connects to a governing site and uses the patterns and templates it shares.',
						'onedesign'
					) }
				</p>
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || ! siteType }
					isBusy={ isSaving }
					style={ { marginTop: '1.5rem' } }
				>
					{ __( 'Select Current Site Type', 'onedesign' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default SiteTypeSettings;
