import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { LibraryItem, SiteError } from './library';

interface LibraryDataOptions< T > {
	toItem: ( item: T ) => LibraryItem;
	loadError: { items: string; brandItems: string };
}

const path = '/onedesign/v1/patterns';

const errorMessage = ( error: unknown, fallback: string ) =>
	( error as { message?: string } )?.message || fallback;

/**
 * Loads this site's items and each brand site's copies, and shares and removes them.
 *
 * @param options           - How the library shows an item, and its strings.
 * @param options.toItem    - Turns an item from the server into a library item.
 * @param options.loadError - What to say when the items couldn't be loaded.
 *
 * @return The props the library needs.
 */
const useLibraryData = < T >( {
	toItem,
	loadError: loadErrorCopy,
}: LibraryDataOptions< T > ) => {
	const sites = useMemo(
		() => window.OneDesignSettings.sharedSites ?? [],
		[]
	);
	const [ list, setList ] = useState< T[] | null >( null );
	const [ brandLists, setBrandLists ] = useState< Record< string, T[] > >(
		{}
	);
	const [ siteErrors, setSiteErrors ] = useState< Record< string, string > >(
		{}
	);
	const [ loadError, setLoadError ] = useState( '' );

	// A brand site that couldn't answer keeps its own message until it answers.
	const loadSite = useCallback(
		async ( siteId: string ) => {
			try {
				const copies = await apiFetch< T[] >( {
					path: `${ path }?site=${ encodeURIComponent( siteId ) }`,
				} );
				setBrandLists( ( current ) => ( {
					...current,
					[ siteId ]: copies,
				} ) );
				setSiteErrors( ( current ) => {
					const next = { ...current };
					delete next[ siteId ];
					return next;
				} );
			} catch ( error ) {
				setSiteErrors( ( current ) => ( {
					...current,
					[ siteId ]: errorMessage( error, loadErrorCopy.brandItems ),
				} ) );
			}
		},
		[ loadErrorCopy.brandItems ]
	);

	useEffect( () => {
		apiFetch< T[] >( { path } )
			.then( setList )
			.catch( ( error ) => {
				setList( [] );
				setLoadError( errorMessage( error, loadErrorCopy.items ) );
			} );
		sites.forEach( ( site ) => loadSite( site.id ) );
	}, [ sites, loadErrorCopy.items, loadSite ] );

	const items = useMemo( () => list && list.map( toItem ), [ list, toItem ] );

	const brandItems = useMemo(
		() =>
			Object.fromEntries(
				Object.entries( brandLists ).map( ( [ siteId, copies ] ) => [
					siteId,
					copies.map( toItem ),
				] )
			),
		[ brandLists, toItem ]
	);

	// Each site gets its own request, so one that fails doesn't stop the others.
	const onApply = async (
		keys: string[],
		siteIds: string[]
	): Promise< SiteError[] > => {
		const results = await Promise.allSettled(
			siteIds.map( ( site ) =>
				apiFetch( {
					path,
					method: 'POST',
					data: { site, names: keys },
				} )
			)
		);
		await Promise.all( siteIds.map( loadSite ) );

		return siteIds.flatMap( ( siteId, index ) => {
			const result = results[ index ];
			return result?.status === 'rejected'
				? [
						{
							site_id: siteId,
							message: errorMessage(
								result.reason,
								__(
									'Something went wrong. Try again.',
									'onedesign'
								)
							),
						},
					]
				: [];
		} );
	};

	const onRemove = async ( keys: string[], siteId: string ) => {
		const { message } = await apiFetch< { message: string } >( {
			path,
			method: 'DELETE',
			data: { site: siteId, names: keys },
		} );
		await loadSite( siteId );
		return message;
	};

	return {
		items,
		brandItems,
		siteErrors,
		sites,
		loadError,
		onApply,
		onRemove,
	};
};

export default useLibraryData;
