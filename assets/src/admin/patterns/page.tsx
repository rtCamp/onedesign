import { __, _n, sprintf } from '@wordpress/i18n';
import Library, {
	type LibraryCopy,
	type LibraryItem,
} from '@/components/library';
import useLibraryData from '@/components/use-library-data';

interface Pattern {
	name: string;
	title?: string;
	description?: string;
	content: string;
	category_labels?: Record< string, string >;
}

const toItem = ( pattern: Pattern ): LibraryItem => ( {
	key: pattern.name,
	title: pattern.title || pattern.name,
	description: pattern.description,
	content: pattern.content,
	categoryLabels: pattern.category_labels,
} );

const loadError = {
	items: __(
		"This site's patterns couldn't be loaded. Reload the page to try again.",
		'onedesign'
	),
	brandItems: __(
		"This brand site's patterns couldn't be loaded. Reload the page to try again.",
		'onedesign'
	),
};

const copy: LibraryCopy = {
	currentSiteTab: __( 'Current Site Patterns', 'onedesign' ),
	search: __( 'Search patterns…', 'onedesign' ),
	loading: __( 'Loading patterns…', 'onedesign' ),
	empty: __( 'No patterns found.', 'onedesign' ),
	selected: ( count ) =>
		sprintf(
			/* translators: %d: Number of selected patterns. */
			_n(
				'%d pattern selected',
				'%d patterns selected',
				count,
				'onedesign'
			),
			count
		),
	apply: __( 'Apply Patterns', 'onedesign' ),
	applied: ( siteNames ) =>
		sprintf(
			/* translators: %s: Names of the brand sites. */
			__( 'Shared the patterns with %s.', 'onedesign' ),
			siteNames
		),
	notApplied: __( "These brand sites didn't get the patterns.", 'onedesign' ),
	allPresent: __( 'Already has all the selected patterns', 'onedesign' ),
	somePresent: ( presentCount, selectedCount ) =>
		sprintf(
			/* translators: %1$d: Number of the selected patterns the brand site has. %2$d: Number of selected patterns. */
			__( 'Already has %1$d of the %2$d selected patterns', 'onedesign' ),
			presentCount,
			selectedCount
		),
	disabledHint: __(
		"Brand sites that already have every selected pattern, or that couldn't be reached, can't be picked.",
		'onedesign'
	),
	remove: __( 'Remove Patterns', 'onedesign' ),
	removeMessage: __(
		"Editors on the brand site won't be able to insert these patterns any more, so check it doesn't need them first.",
		'onedesign'
	),
	categories: __( 'Pattern Categories', 'onedesign' ),
};

/**
 * The Pattern Library, which shares this site's patterns with brand sites and removes them.
 *
 * @return The library.
 */
const PatternLibrary = () => {
	const library = useLibraryData( {
		toItem,
		loadError,
	} );

	return <Library { ...library } copy={ copy } />;
};

export default PatternLibrary;
