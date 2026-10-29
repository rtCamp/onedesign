import {
	Button,
	Modal,
	Notice,
	SearchControl,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import BrandSitePicker, {
	type BrandSite,
	type BrandSitePickerCopy,
	type SiteError,
} from './brand-site-picker';
import PreviewCard, { type LibraryItem } from './preview-card';

export type { LibraryItem, SiteError };

export interface LibraryCopy extends BrandSitePickerCopy {
	currentSiteTab: string;
	search: string;
	loading: string;
	empty: string;
	noBrandSites: string;
	selected: ( count: number ) => string;
	applied: ( siteNames: string ) => string;
	remove: string;
	removeMessage: string;
	categories: string;
}

interface LibraryProps {
	// This site's items, or null while they load.
	items: LibraryItem[] | null;
	// Each brand site's items keyed by site id. A site that's still loading, or couldn't answer, has none.
	brandItems: Record< string, LibraryItem[] >;
	siteErrors: Record< string, string >;
	sites: BrandSite[];
	loadError: string;
	copy: LibraryCopy;
	// Resolves with the brand sites that didn't get the items.
	onApply: ( keys: string[], siteIds: string[] ) => Promise< SiteError[] >;
	// Resolves with the server's message.
	onRemove: ( keys: string[], siteId: string ) => Promise< string >;
}

interface NoticeState {
	status: 'success' | 'error';
	message: string;
}

const PER_PAGE = 9;
const CURRENT_SITE = 'current-site';

/**
 * A library of this site's patterns and each brand site's copies, which shares them with brand sites and removes them.
 *
 * @param props            - Component properties.
 * @param props.items      - This site's items.
 * @param props.brandItems - Each brand site's items.
 * @param props.siteErrors - Why each brand site that couldn't answer didn't, keyed by site id.
 * @param props.sites      - The brand sites.
 * @param props.loadError  - Why the items couldn't be loaded.
 * @param props.copy       - The library's own copy.
 * @param props.onApply    - Applies items to brand sites.
 * @param props.onRemove   - Removes items from a brand site.
 *
 * @return The library.
 */
const Library = ( {
	items,
	brandItems,
	siteErrors,
	sites,
	loadError,
	copy,
	onApply,
	onRemove,
}: LibraryProps ) => {
	const [ tab, setTab ] = useState( CURRENT_SITE );
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ selected, setSelected ] = useState< string[] >( [] );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isPicking, setIsPicking ] = useState( false );
	const [ isConfirming, setIsConfirming ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );

	const toggle = useCallback(
		( key: string ) =>
			setSelected( ( current ) =>
				current.includes( key )
					? current.filter( ( selectedKey ) => selectedKey !== key )
					: [ ...current, key ]
			),
		[]
	);

	if ( sites.length === 0 ) {
		return (
			<div className="onedesign-library-empty">
				<p>{ copy.noBrandSites }</p>
				<Button
					variant="primary"
					href={ window.OneDesignSettings.setupUrl }
				>
					{ __( 'Add a brand site', 'onedesign' ) }
				</Button>
			</div>
		);
	}

	const isCurrentSite = tab === CURRENT_SITE;
	const tabItems = isCurrentSite ? items : ( brandItems[ tab ] ?? null );
	// The picker needs every brand site's items to tell which ones have the selected items already.
	const isBrandLoading = sites.some(
		( site ) => ! brandItems[ site.id ] && ! siteErrors[ site.id ]
	);

	const categories: Record< string, string > = {};
	( tabItems ?? [] ).forEach( ( item ) =>
		Object.assign( categories, item.categoryLabels )
	);

	const term = search.trim().toLowerCase();
	const shownItems = ( tabItems ?? [] ).filter(
		( item ) =>
			( ! category || category in ( item.categoryLabels ?? {} ) ) &&
			( ! term ||
				item.title.toLowerCase().includes( term ) ||
				Boolean( item.description?.toLowerCase().includes( term ) ) )
	);

	const selectTab = ( name: string ) => {
		setTab( name );
		setCategory( '' );
		setPage( 1 );
		setSelected( [] );
		setNotice( null );
	};

	// Removing resolves with the server's message.
	const run = async ( action: () => Promise< string > ) => {
		setIsBusy( true );
		try {
			setNotice( { status: 'success', message: await action() } );
			setSelected( [] );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					( error as { message?: string } )?.message ||
					__( 'Something went wrong. Try again.', 'onedesign' ),
			} );
		} finally {
			setIsBusy( false );
		}
	};

	const apply = async ( siteIds: string[] ) => {
		const errors = await onApply( selected, siteIds );
		if ( errors.length === 0 ) {
			setNotice( {
				status: 'success',
				message: copy.applied(
					sites
						.filter( ( site ) => siteIds.includes( site.id ) )
						.map( ( site ) => site.name )
						.join( ', ' )
				),
			} );
			setSelected( [] );
			setIsPicking( false );
		}
		return errors;
	};

	const renderItems = () => {
		if ( tabItems === null ) {
			return (
				<div className="onedesign-library-loading">
					<Spinner />
					<p>{ copy.loading }</p>
				</div>
			);
		}

		if ( shownItems.length === 0 ) {
			return (
				<div className="onedesign-library-empty">
					<p>{ copy.empty }</p>
				</div>
			);
		}

		return (
			<div className="onedesign-library-grid">
				{ shownItems.slice( 0, page * PER_PAGE ).map( ( item ) => (
					<PreviewCard
						key={ item.key }
						item={ item }
						isSelected={ selected.includes( item.key ) }
						onToggle={ toggle }
					/>
				) ) }
			</div>
		);
	};

	const renderTab = () => {
		if ( ! isCurrentSite && siteErrors[ tab ] ) {
			return (
				<Notice status="error" isDismissible={ false }>
					{ siteErrors[ tab ] }
				</Notice>
			);
		}

		const shownCount = Math.min( page * PER_PAGE, shownItems.length );

		return (
			<>
				{ renderItems() }
				<div className="onedesign-library-footer">
					<div className="onedesign-selection-info">
						{ selected.length > 0 && (
							<span className="onedesign-selected-count">
								{ copy.selected( selected.length ) }
							</span>
						) }
					</div>
					<div className="onedesign-footer-actions">
						{ shownCount < shownItems.length && (
							<Button
								variant="secondary"
								onClick={ () =>
									setPage( ( current ) => current + 1 )
								}
							>
								{ sprintf(
									/* translators: %1$d: Number of items shown. %2$d: Number of items. */
									__( 'Show More (%1$d/%2$d)', 'onedesign' ),
									shownCount,
									shownItems.length
								) }
							</Button>
						) }
						{ isCurrentSite ? (
							<Button
								variant="primary"
								disabled={
									selected.length === 0 || isBrandLoading
								}
								onClick={ () => setIsPicking( true ) }
							>
								{ __( 'Apply to Sites', 'onedesign' ) }
							</Button>
						) : (
							<Button
								variant="primary"
								isDestructive
								disabled={ isBusy || selected.length === 0 }
								isBusy={ isBusy }
								onClick={ () => setIsConfirming( true ) }
							>
								{ copy.remove }
							</Button>
						) }
					</div>
				</div>
			</>
		);
	};

	return (
		<div className="onedesign-library">
			{ loadError && (
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
			) }
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<SearchControl
				className="onedesign-library-search"
				value={ search }
				onChange={ ( value: string ) => {
					setSearch( value );
					setPage( 1 );
				} }
				placeholder={ copy.search }
				__nextHasNoMarginBottom
			/>

			<TabPanel
				className="onedesign-library-tabs"
				activeClass="active-tab"
				onSelect={ selectTab }
				tabs={ [
					{ name: CURRENT_SITE, title: copy.currentSiteTab },
					...sites.map( ( site ) => ( {
						name: site.id,
						title: site.name,
					} ) ),
				] }
			>
				{ () => (
					<div className="onedesign-library-content">
						<div className="onedesign-library-categories">
							<p className="onedesign-library-categories-heading">
								{ copy.categories }
							</p>
							{ (
								[
									[ '', __( 'All', 'onedesign' ) ],
									...Object.entries( categories ),
								] as Array< [ string, string ] >
							 ).map( ( [ slug, label ] ) => (
								<Button
									key={ slug }
									aria-pressed={ category === slug }
									onClick={ () => {
										setCategory( slug );
										setPage( 1 );
									} }
								>
									{ label }
								</Button>
							) ) }
						</div>
						<div className="onedesign-library-items">
							{ renderTab() }
						</div>
					</div>
				) }
			</TabPanel>

			{ isPicking && (
				<BrandSitePicker
					sites={ sites }
					siteErrors={ siteErrors }
					selectedCount={ selected.length }
					countPresent={ ( siteId ) =>
						( brandItems[ siteId ] ?? [] ).filter( ( item ) =>
							selected.includes( item.key )
						).length
					}
					copy={ copy }
					onApply={ apply }
					onClose={ () => setIsPicking( false ) }
				/>
			) }

			{ isConfirming && (
				<Modal
					title={ copy.remove }
					onRequestClose={ () => setIsConfirming( false ) }
					size="medium"
				>
					<p>{ copy.removeMessage }</p>
					<div className="onedesign-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setIsConfirming( false ) }
						>
							{ __( 'Cancel', 'onedesign' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => {
								setIsConfirming( false );
								run( () => onRemove( selected, tab ) );
							} }
						>
							{ copy.remove }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
};

export default Library;
