import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import Library, {
	type LibraryCopy,
	type LibraryItem,
} from '@/components/library';
import useLibraryData from '@/components/use-library-data';

const path = '/onedesign/v1/templates';

interface Template {
	id: string;
	// On a brand site, the id of the governing site's template it came from.
	original_id?: string;
	title: string;
	description?: string;
	content: string;
}

// A brand site's copy is listed, picked and removed by the id of this site's template it came from.
const toItem = ( template: Template ): LibraryItem => ( {
	key: template.original_id ?? template.id,
	title: template.title || template.id,
	description: template.description,
	content: template.content,
} );

const loadError = {
	items: __(
		"This site's templates couldn't be loaded. Reload the page to try again.",
		'onedesign'
	),
	brandItems: __(
		"This brand site's templates couldn't be loaded. Reload the page to try again.",
		'onedesign'
	),
};

const copy: LibraryCopy = {
	currentSiteTab: __( 'Current Site Templates', 'onedesign' ),
	search: __( 'Search templates…', 'onedesign' ),
	loading: __( 'Loading templates…', 'onedesign' ),
	empty: __( 'No templates found.', 'onedesign' ),
	noBrandSites: __(
		'There are no brand sites to share templates with. Add one on the settings page, then come back here to apply templates to it.',
		'onedesign'
	),
	selected: ( count ) =>
		sprintf(
			/* translators: %d: Number of selected templates. */
			_n(
				'%d template selected',
				'%d templates selected',
				count,
				'onedesign'
			),
			count
		),
	apply: __( 'Apply Templates', 'onedesign' ),
	applied: ( siteNames ) =>
		sprintf(
			/* translators: %s: Names of the brand sites. */
			__( 'Applied the templates to %s.', 'onedesign' ),
			siteNames
		),
	notApplied: __(
		"These brand sites didn't get the templates.",
		'onedesign'
	),
	allPresent: __( 'Already has all the selected templates', 'onedesign' ),
	somePresent: ( presentCount, selectedCount ) =>
		sprintf(
			/* translators: %1$d: Number of the selected templates the brand site has. %2$d: Number of selected templates. */
			__(
				'Already has %1$d of the %2$d selected templates',
				'onedesign'
			),
			presentCount,
			selectedCount
		),
	disabledHint: __(
		"Brand sites that already have every selected template, or that couldn't be reached, can't be picked.",
		'onedesign'
	),
	remove: __( 'Remove Templates', 'onedesign' ),
	removeMessage: __(
		"Pages on the brand site that use these templates will switch to its theme's templates, so check they aren't in use first.",
		'onedesign'
	),
};

/**
 * The Template Library, which applies this site's templates to brand sites, keeps their copies up to date and removes them.
 *
 * @return The library.
 */
const TemplateLibrary = () => {
	const { loadSite, ...library } = useLibraryData( {
		path,
		keyParam: 'ids',
		toItem,
		loadError,
	} );

	const resync = async ( siteId: string ) => {
		const { message } = await apiFetch< { message: string } >( {
			path,
			method: 'PUT',
			data: { site: siteId },
		} );
		await loadSite( siteId );
		return message;
	};

	return (
		<Library
			{ ...library }
			copy={ copy }
			resync={ {
				label: __( 'Sync Shared Templates', 'onedesign' ),
				run: resync,
			} }
		/>
	);
};

export default TemplateLibrary;
