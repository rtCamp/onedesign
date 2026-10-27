import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Modal,
	Notice,
	SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import MultiSites from '@/components/MultiSites';
import type { NoticeType } from '@/admin/settings/page';
import type { OneDesignMultisite, SiteType } from '@/types/global';

/**
 * Network Admin > Settings > OneDesign, where network admins see the network's sites, make them governing sites and add brand sites to them.
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
	const [ governingSiteError, setGoverningSiteError ] = useState<
		string | null
	>( null );
	const [ isGoverningSiteModalOpen, setIsGoverningSiteModalOpen ] =
		useState( false );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	const governingSites = sites.filter(
		( site ) => site.siteType === 'governing-site'
	);
	const typelessSites = sites.filter( ( site ) => ! site.siteType );
	const hasSiteWithType = typelessSites.length < sites.length;
	const availableSites = sites.filter( ( site ) => site.canAddAsBrandSite );

	const siteTypeLabels: Record< SiteType, string > = {
		'governing-site': __( 'Governing Site', 'onedesign' ),
		'brand-site': __( 'Brand Site', 'onedesign' ),
		'': __( 'Not set', 'onedesign' ),
	};

	const closeGoverningSiteModal = () => {
		setIsGoverningSiteModalOpen( false );
		setNewGoverningSite( '' );
		setGoverningSiteError( null );
	};

	const handleMakeGoverning = async () => {
		setIsApplying( true );
		setGoverningSiteError( null );
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
						? {
								...site,
								siteType: 'governing-site',
								canAddAsBrandSite: false,
							}
						: site
				)
			);
			// Picked so brand sites can be added to it right away.
			setGoverningSite( newGoverningSite );
			closeGoverningSiteModal();
			setNotice( {
				type: 'success',
				message: sprintf(
					/* translators: %s: Name of the site. */
					__( '%s is now a governing site.', 'onedesign' ),
					name
				),
			} );
		} catch ( error ) {
			setGoverningSiteError(
				( error as { message?: string } )?.message ||
					__(
						"Couldn't make the site a governing site. Try again.",
						'onedesign'
					)
			);
		} finally {
			setIsApplying( false );
		}
	};

	const governingSiteForm = (
		<>
			{ governingSiteError && (
				<Notice
					status="error"
					isDismissible={ false }
					className="onedesign-form-error"
				>
					{ governingSiteError }
				</Notice>
			) }
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
		</>
	);

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

			{ ! hasSiteWithType && (
				<Card style={ { marginTop: '16px' } }>
					<CardHeader>
						<h2>{ __( 'Add a Governing Site', 'onedesign' ) }</h2>
					</CardHeader>
					<CardBody>{ governingSiteForm }</CardBody>
				</Card>
			) }

			{ hasSiteWithType && (
				<Card style={ { marginTop: '16px' } }>
					<CardHeader>
						<h2>{ __( 'Sites', 'onedesign' ) }</h2>
						<div style={ { display: 'flex', gap: '16px' } }>
							<Button
								variant="secondary"
								onClick={ () =>
									setIsGoverningSiteModalOpen( true )
								}
								disabled={ typelessSites.length === 0 }
							>
								{ __( 'Add a Governing Site', 'onedesign' ) }
							</Button>
							<MultiSites
								label={ __( 'Add Brand Sites', 'onedesign' ) }
								disabled={
									governingSites.length === 0 ||
									availableSites.length === 0
								}
								governingSiteId={ governingSite }
								sites={ availableSites }
								onAdd={ ( _sharedSites, addedIds ) =>
									setSites( ( current ) =>
										current.map( ( site ) =>
											addedIds.includes( site.id )
												? {
														...site,
														siteType: 'brand-site',
														canAddAsBrandSite: false,
													}
												: site
										)
									)
								}
								setNotice={ setNotice }
							>
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Governing Site',
										'onedesign'
									) }
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
							</MultiSites>
						</div>
					</CardHeader>
					<CardBody>
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Site Name', 'onedesign' ) }</th>
									<th>{ __( 'Site URL', 'onedesign' ) }</th>
									<th>{ __( 'Site Type', 'onedesign' ) }</th>
									<th>{ __( 'Actions', 'onedesign' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ sites.map( ( site ) => (
									<tr key={ site.id }>
										<td>{ site.name }</td>
										<td>{ site.url }</td>
										<td>
											{ siteTypeLabels[ site.siteType ] }
										</td>
										<td>
											<Button
												variant="secondary"
												href={ site.manageUrl }
											>
												{ __(
													'Manage Site',
													'onedesign'
												) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</CardBody>
				</Card>
			) }

			{ isGoverningSiteModalOpen && (
				<Modal
					title={ __( 'Add a Governing Site', 'onedesign' ) }
					onRequestClose={ closeGoverningSiteModal }
					shouldCloseOnClickOutside={ ! isApplying }
					shouldCloseOnEsc={ ! isApplying }
					size="medium"
				>
					{ governingSiteForm }
				</Modal>
			) }
		</>
	);
};

export default NetworkSettingsPage;
