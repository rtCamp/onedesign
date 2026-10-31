import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type {
	BrandSite,
	EditingIndex,
	NoticeType,
} from '@/admin/settings/page';
import { CURRENT_SITE_ID, MULTISITES, getInitials } from '@/js/utils';
import MultiSites from './MultiSites';

const SiteTable = ( {
	sites,
	onEdit,
	onDelete,
	setFormData,
	setShowModal,
	setSites,
	setNotice,
}: {
	sites: BrandSite[];
	onEdit: ( index: number ) => void;
	onDelete: ( index: number | null ) => void;
	setFormData: ( data: BrandSite ) => void;
	setShowModal: ( show: boolean ) => void;
	setSites: ( sites: BrandSite[] ) => void;
	setNotice: ( notice: NoticeType ) => void;
} ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState< EditingIndex >( null );

	// Only network admins get the network's sites to add. They're listed when the page loads, so leave out the ones added since.
	const networkSites = MULTISITES.filter(
		( site ) => ! sites.some( ( brandSite ) => brandSite.id === site.id )
	);

	const handleDeleteClick = ( index: number ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		onDelete( deleteIndex );
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	const handleDeleteCancel = () => {
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onedesign' ) }</h3>
				<div style={ { display: 'flex', gap: '16px' } }>
					{ networkSites.length > 0 && (
						<MultiSites
							governingSiteId={ CURRENT_SITE_ID }
							sites={ networkSites }
							onAdd={ setSites }
							setNotice={ setNotice }
						/>
					) }
					<Button
						style={ { width: 'fit-content' } }
						variant="primary"
						onClick={ () => setShowModal( true ) }
					>
						{ __( 'Add Brand Site', 'onedesign' ) }
					</Button>
				</div>
			</CardHeader>
			<CardBody>
				<table
					className="wp-list-table widefat fixed striped "
					style={ { marginTop: '16px' } }
				>
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'onedesign' ) }</th>
							<th>{ __( 'Site URL', 'onedesign' ) }</th>
							<th>{ __( 'Logo', 'onedesign' ) }</th>
							<th>{ __( 'API Key', 'onedesign' ) }</th>
							<th>{ __( 'Actions', 'onedesign' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td
									colSpan={ 5 }
									style={ { textAlign: 'center' } }
								>
									{ __(
										'No Brand Sites found.',
										'onedesign'
									) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.name }</td>
								<td>{ site?.url }</td>
								<td>
									{ site.logo ? (
										<img
											src={ site.logo }
											alt=""
											style={ {
												maxWidth: '100px',
												maxHeight: '50px',
											} }
											loading="lazy"
											decoding="async"
										/>
									) : (
										<span className="onedesign-site-initials">
											{ getInitials( site.name ) }
										</span>
									) }
								</td>
								<td>
									{ /* The stored key never reaches the browser. */ }
									<code>••••••••</code>
								</td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											// The key field starts empty, which keeps the stored key.
											setFormData( {
												...site,
												api_key: '',
											} );
											onEdit( index );
											setShowModal( true );
										} }
										style={ { marginRight: '8px' } }
									>
										{ __( 'Edit', 'onedesign' ) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () =>
											handleDeleteClick( index )
										}
									>
										{ __( 'Delete', 'onedesign' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ handleDeleteConfirm }
					onCancel={ handleDeleteCancel }
				/>
			) }
		</Card>
	);
};

const DeleteConfirmationModal = ( {
	onConfirm,
	onCancel,
}: {
	onConfirm: () => void;
	onCancel: () => void;
} ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'onedesign' ) }
		onRequestClose={ onCancel }
		isDismissible
		shouldCloseOnClickOutside
	>
		<p>
			{ __(
				'Are you sure you want to delete this Brand Site? This action cannot be undone.',
				'onedesign'
			) }
		</p>
		<div
			style={ {
				display: 'flex',
				justifyContent: 'flex-end',
				marginTop: '20px',
				gap: '16px',
			} }
		>
			<Button variant="secondary" onClick={ onCancel }>
				{ __( 'Cancel', 'onedesign' ) }
			</Button>
			<Button variant="primary" isDestructive onClick={ onConfirm }>
				{ __( 'Delete', 'onedesign' ) }
			</Button>
		</div>
	</Modal>
);

export default SiteTable;
