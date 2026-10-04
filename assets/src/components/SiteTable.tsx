/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getInitials } from '../js/utils';
import MultiSites from './MultiSites';
import { IS_MULTISITE, IS_GOVERNING_SITE_SELECTED } from '../js/constants';

type SiteId = number | string;

interface Site {
	id?: SiteId;
	name?: string;
	url?: string;
	logo?: string;
	api_key?: string;
	logo_id?: number | null;
	is_editable?: boolean;
}

interface Notice {
	type: 'error' | 'success';
	message: string;
}

interface SiteTableProps {
	sites: Site[];
	onEdit: ( index: number ) => void;
	onDelete: ( index: number ) => void;
	setFormData: ( site: Site ) => void;
	setShowModal: ( show: boolean ) => void;
	setSites: ( sites: Site[] ) => void;
	setNotice: ( notice: Notice ) => void;
}

/**
 * SiteTable component to display and manage brand sites.
 *
 * @param props              - Component properties.
 * @param props.sites        - List of brand sites.
 * @param props.onEdit       - Function to handle editing a site.
 * @param props.onDelete     - Function to handle deleting a site.
 * @param props.setFormData  - Function to set form data for editing.
 * @param props.setShowModal - Function to show/hide the modal for adding/editing a site.
 * @param props.setSites     - Function to update the list of sites.
 * @param props.setNotice    - Function to set notice messages.
 *
 * @return Rendered component.
 */
const SiteTable = ( {
	sites,
	onEdit,
	onDelete,
	setFormData,
	setShowModal,
	setSites,
	setNotice,
}: SiteTableProps ): JSX.Element => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState< number | null >( null );

	const handleDeleteClick = ( index: number ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		if ( deleteIndex !== null ) {
			onDelete( deleteIndex );
		}
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
					{ IS_MULTISITE && IS_GOVERNING_SITE_SELECTED && (
						<MultiSites
							setBrandSites={ setSites }
							brandSites={ sites }
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
				<table className="wp-list-table widefat fixed striped">
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
									{ site?.logo ? (
										<img
											src={ site.logo }
											alt={ __(
												'Site Logo',
												'onedesign'
											) }
											style={ {
												maxWidth: '100px',
												maxHeight: '50px',
											} }
											loading="lazy"
											decoding="async"
										/>
									) : (
										<span className="onedesign-site-initials">
											{ getInitials( site?.name ?? '' ) }
										</span>
									) }
								</td>
								<td>
									<code>
										{ site?.api_key?.substring( 0, 10 ) }...
									</code>
								</td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
										disabled={ site?.is_editable === false }
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

interface DeleteConfirmationModalProps {
	onConfirm: () => void;
	onCancel: () => void;
}

/**
 * DeleteConfirmationModal component for confirming site deletion.
 *
 * @param props           - Component properties.
 * @param props.onConfirm - Function to call on confirmation.
 * @param props.onCancel  - Function to call on cancellation.
 * @return Rendered component.
 */
const DeleteConfirmationModal = ( {
	onConfirm,
	onCancel,
}: DeleteConfirmationModalProps ): JSX.Element => (
	<Modal
		title={ __( 'Delete Brand Site', 'onedesign' ) }
		onRequestClose={ onCancel }
		isDismissible
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
