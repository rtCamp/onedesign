/**
 * Admin pages of the pattern and template libraries.
 */
export const LIBRARY_PAGES = [
	'onedesign-pattern-library',
	'onedesign-template-library',
];

/**
 * Returns to the admin page that linked here, or the dashboard.
 *
 * @param skipPages - Admin `page` slugs never to return to, so screens that link to each other don't loop.
 */
export const navigateBack = ( skipPages: string[] = [] ): void => {
	let destination = 'index.php';

	try {
		const referrer = new URL( document.referrer );
		const page = referrer.searchParams.get( 'page' ) ?? '';
		if (
			referrer.origin === window.location.origin &&
			referrer.pathname.includes( '/wp-admin/' ) &&
			! skipPages.includes( page )
		) {
			destination = referrer.href;
		}
	} catch {
		// No referrer, e.g. opened from a bookmark or a new tab.
	}

	window.location.assign( destination );
};
