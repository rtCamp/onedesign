import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import MultiSites from '@/components/MultiSites';
import type { NoticeType } from '@/admin/settings/page';
import type { OneDesignMultisite } from '@/types/global';

/**
 * Network Admin > Settings > OneDesign, where network admins make the network's sites governing sites and add brand sites to them.
 *
 * @return Rendered component.
 */
const NetworkSettingsPage = (): JSX.Element => {
	const [ sites, setSites ] = useState< OneDesignMultisite[] >(
		window.OneDesignSettings.multisites ?? []
	);
	const [ newGoverningSite, setNewGoverningSite ] = useState( '' );
	const [ governingSite, setGoverningSite ] = useState( '' );
	const [ isApplying, setIsApplying ] = useState( false );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	const governingSites = sites.filter(
		( site ) => site.siteType === 'governing-site'
	);
	const typelessSites = sites.filter( ( site ) => ! site.siteType );
	const availableSites = sites.filter(
		( site ) => site.siteType !== 'governing-site'
	);

	const handleMakeGoverning = async () => {
		setIsApplying( true );
		setNotice( null );
		try {
			await apiFetch( {
				path: '/onedesign/v1/multisite/governing-sites',
				method: 'POST',
				data: { site_id: Number( newGoverningSite ) },
			} );

			const name =
				sites.find( ( site ) => site.id === newGoverningSite )?.name ??
				'';

			setSites( ( current ) =>
				current.map( ( site ) =>
					site.id === newGoverningSite
						? { ...site, siteType: 'governing-site' }
						: site
				)
			);
			// Picked so brand sites can be added to it right away.
			setGoverningSite( newGoverningSite );
			setNewGoverningSite( '' );
			setNotice( {
				type: 'success',
				message: sprintf(
					/* translators: %s: Name of the site. */
					__( '%s is now a governing site.', 'onedesign' ),
					name
				),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message:
					( error as { message?: string } )?.message ||
					__(
						"Couldn't make the site a governing site. Try again.",
						'onedesign'
					),
			} );
		} finally {
			setIsApplying( false );
		}
	};

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ availableSites.length === 0 && (
				<p>
					{ __(
						'Every site on this network is already set up.',
						'onedesign'
					) }
				</p>
			) }

			{ governingSites.length > 0 && availableSites.length > 0 && (
				<Card style={ { marginTop: '16px' } }>
					<CardHeader>
						<h2>{ __( 'Add Brand Sites', 'onedesign' ) }</h2>
					</CardHeader>
					<CardBody>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Governing Site', 'onedesign' ) }
							value={ governingSite }
							onChange={ setGoverningSite }
							options={ [
								{
									label: __( 'Select…', 'onedesign' ),
									value: '',
								},
								...governingSites.map( ( site ) => ( {
									label: site.name,
									value: site.id,
								} ) ),
							] }
						/>

						{ governingSite && (
							<div style={ { marginTop: '16px' } }>
								<MultiSites
									governingSiteId={ governingSite }
									sites={ availableSites }
									onAdd={ ( _sharedSites, addedIds ) => {
										// Brand sites with a governing site can't be added again.
										setSites( ( current ) =>
											current.filter(
												( site ) =>
													! addedIds.includes(
														site.id
													)
											)
										);
										setNewGoverningSite( ( current ) =>
											addedIds.includes( current )
												? ''
												: current
										);
									} }
									setNotice={ setNotice }
								/>
							</div>
						) }
					</CardBody>
				</Card>
			) }

			{ typelessSites.length > 0 && (
				<Card style={ { marginTop: '16px' } }>
					<CardHeader>
						<h2>{ __( 'Add a Governing Site', 'onedesign' ) }</h2>
					</CardHeader>
					<CardBody>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Site', 'onedesign' ) }
							value={ newGoverningSite }
							help={ __(
								"A governing site shares its patterns and templates with its brand sites. A site's type can't be changed once it's set.",
								'onedesign'
							) }
							onChange={ setNewGoverningSite }
							options={ [
								{
									label: __( 'Select…', 'onedesign' ),
									value: '',
								},
								...typelessSites.map( ( site ) => ( {
									label: site.name,
									value: site.id,
								} ) ),
							] }
						/>
						<Button
							variant="primary"
							onClick={ handleMakeGoverning }
							disabled={ ! newGoverningSite || isApplying }
							isBusy={ isApplying }
							style={ { marginTop: '16px' } }
						>
							{ __( 'Make Governing Site', 'onedesign' ) }
						</Button>
					</CardBody>
				</Card>
			) }
		</>
	);
};

export default NetworkSettingsPage;
