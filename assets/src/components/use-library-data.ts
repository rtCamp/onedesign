import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { LibraryItem, SiteError } from './library';

interface LibraryDataOptions< T > {
	path: string;
	keyParam: string;
	toItem: ( item: T ) => LibraryItem;
	loadError: { items: string; brandItems: string };
}

const errorMessage = ( error: unknown, fallback: string ) =>
	( error as { message?: string } )?.message || fallback;

/**
 * Loads this site's items and each brand site's copies, and shares and removes them.
 *
 * @param options           - The library's route, how it shows an item, and its strings.
 * @param options.path      - The REST route.
 * @param options.keyParam  - What the request bodies call the item keys.
 * @param options.toItem    - Turns an item from the server into a library item.
 * @param options.loadError - What to say when the items couldn't be loaded.
 *
 * @return The props the library needs, and a function to reload a brand site's copies.
 */
const useLibraryData = < T >( {
	path,
	keyParam,
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
		[ path, loadErrorCopy.brandItems ]
	);

	useEffect( () => {
		apiFetch< T[] >( { path } )
			.then( setList )
			.catch( ( error ) => {
				setList( [] );
				setLoadError( errorMessage( error, loadErrorCopy.items ) );
			} );
		sites.forEach( ( site ) => loadSite( site.id ) );
	}, [ path, sites, loadErrorCopy.items, loadSite ] );

	const items = useMemo( () => list && list.map( toItem ), [ list, toItem ] );

	// A brand site's copy points at its own synced patterns, which don't render here, so it previews as this site's item it came from, when this site still has it.
	const brandItems = useMemo(
		() =>
			Object.fromEntries(
				Object.entries( brandLists ).map( ( [ siteId, copies ] ) => [
					siteId,
					copies.map( ( copy ) => {
						const item = toItem( copy );
						const source = items?.find(
							( { key } ) => key === item.key
						);
						return {
							...item,
							content: source?.content ?? item.content,
						};
					} ),
				] )
			),
		[ brandLists, items, toItem ]
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
					data: { site, [ keyParam ]: keys },
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
			data: { site: siteId, [ keyParam ]: keys },
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
		loadSite,
	};
};

export default useLibraryData;
