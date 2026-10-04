/**
 * External dependencies
 */
import { useState, useEffect } from 'react';
/**
 * WordPress dependencies
 */
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

const BRAND_SITE = 'brand-site';
const GOVERNING_SITE = 'governing-site';

export type SiteType = typeof BRAND_SITE | typeof GOVERNING_SITE;

interface NoticeState {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: {
	value: SiteType | '';
	setSiteType: ( v: SiteType | '' ) => void;
} ) => (
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

const OnboardingScreen = () => {
	// WordPress provides snake_case keys here; alias them to camelCase locals.
	const {
		nonce,
		setup_url: setupUrl,
		site_type: initialSiteType,
	} = window.OneDesignSettings;

	const [ siteType, setSiteType ] = useState< SiteType | '' >(
		initialSiteType || ''
	);
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isSaving, setIsSaving ] = useState< boolean >( false );

	useEffect( () => {
		apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
		apiFetch< { onedesign_site_type?: SiteType } >( {
			path: '/wp/v2/settings',
		} )
			.then( ( settings ) => {
				if ( settings?.onedesign_site_type ) {
					setSiteType( settings.onedesign_site_type );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type.', 'onedesign' ),
				} );
			} );
	} );

	const handleSiteTypeChange = async ( value: SiteType | '' ) => {
		// Optimistically set site type.
		setSiteType( value );
		setIsSaving( true );

		try {
			await apiFetch< { onedesign_site_type?: SiteType } >( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onedesign_site_type: value },
			} ).then( ( settings ) => {
				if ( ! settings?.onedesign_site_type ) {
					throw new Error(
						__( 'No site type in response', 'onedesign' )
					);
				}

				setSiteType( settings.onedesign_site_type );

				if ( setupUrl ) {
					window.location.href = setupUrl;
				}
			} );
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
		<Card>
			{ !! notice?.message && (
				<Notice
					status={ notice?.type ?? 'success' }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice?.message }
				</Notice>
			) }

			<CardHeader>
				<h2>{ __( 'OneDesign', 'onedesign' ) }</h2>
			</CardHeader>

			<CardBody className="onedesign-onboarding-page">
				<SiteTypeSelector
					value={ siteType }
					setSiteType={ setSiteType }
				/>
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || ! siteType }
					style={ { marginTop: '1.5rem' } }
					className={ isSaving ? 'is-busy' : '' }
				>
					{ __( 'Select Current Site Type', 'onedesign' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default OnboardingScreen;
