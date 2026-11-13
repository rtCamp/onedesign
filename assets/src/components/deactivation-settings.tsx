/**
 * External dependencies
 */
import { useState } from 'react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { NoticeType } from '@/admin/settings/page';
import type { SiteType } from '@/types/global';

const OPTION = 'onedesign_delete_data_on_deactivation';
const SITE_TYPE = ( window.OneDesignSettings.siteType as SiteType ) || '';

const HELP: Record< SiteType, string > = {
	'governing-site': __(
		"Use this on hosts that never uninstall plugins. Deactivating OneDesign then deletes its settings and disconnects the brand sites. This can't be undone, so turn it off before deactivating OneDesign to troubleshoot.",
		'onedesign'
	),
	'brand-site': __(
		"Use this on hosts that never uninstall plugins. Deactivating OneDesign then deletes its settings and the patterns and templates this site received, and disconnects it from the governing site. The synced patterns and template parts it created are kept. This can't be undone, so turn it off before deactivating OneDesign to troubleshoot.",
		'onedesign'
	),
	'': __(
		"Use this on hosts that never uninstall plugins. Deactivating OneDesign then deletes its settings. This can't be undone.",
		'onedesign'
	),
};

const DeactivationSettings = ( {
	setNotice,
}: {
	setNotice: ( notice: NoticeType ) => void;
} ) => {
	const [ isEnabled, setIsEnabled ] = useState(
		!! window.OneDesignSettings.deleteDataOnDeactivation
	);
	const [ isSaving, setIsSaving ] = useState( false );

	const onChange = async ( value: boolean ) => {
		setIsSaving( true );

		try {
			const settings = await apiFetch< Record< string, unknown > >( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { [ OPTION ]: value },
			} );

			setIsEnabled( !! settings?.[ OPTION ] );
			setNotice( {
				type: 'success',
				message: __( 'Settings saved.', 'onedesign' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to save the setting. Please try again.',
					'onedesign'
				),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h2>{ __( 'Deactivation', 'onedesign' ) }</h2>
			</CardHeader>
			<CardBody>
				<ToggleControl
					label={ __(
						'Delete plugin data on deactivation',
						'onedesign'
					) }
					help={ HELP[ SITE_TYPE ] }
					checked={ isEnabled }
					disabled={ isSaving }
					onChange={ onChange }
					__nextHasNoMarginBottom
				/>
			</CardBody>
		</Card>
	);
};

export default DeactivationSettings;
