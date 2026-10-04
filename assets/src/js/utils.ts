/**
 * External dependencies
 */
import DOMPurify from 'dompurify';

/**
 * Helper function to extract initials from a name.
 *
 * @param name - The name to extract initials from.
 * @return The extracted initials (up to 2 characters).
 */
const getInitials = ( name: string ): string => {
	if ( ! name || typeof name !== 'string' ) {
		return '?';
	}

	const trimmedName = name.trim();
	if ( ! trimmedName ) {
		return '?';
	}

	const parts = trimmedName
		.split( /[\s-_,.]+/ )
		.filter( ( part ) => part.length > 0 );

	const [ first, second ] = parts;
	if ( ! first ) {
		return '?';
	}

	if ( parts.length === 1 ) {
		if ( first.length === 1 ) {
			return first.toUpperCase();
		}
		return first.substring( 0, 2 ).toUpperCase();
	}

	return (
		first.charAt( 0 ) + ( second ? second.charAt( 0 ) : '' )
	).toUpperCase();
};

/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param str - The string to validate as a URL.
 *
 * @return True if the string is a valid URL, false otherwise.
 */
const isURL = ( str: string ): boolean => {
	const pattern = new RegExp(
		'^https?:\\/\\/' +
			'(?:[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.)?' +
			'[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.' +
			'[a-z]{2,}' +
			'(?::\\d+)?' +
			'(?:\\/[^\\s]*)?' +
			'$',
		'i'
	);
	return pattern.test( str );
};

/**
 * Validates if a given string is a valid URL.
 *
 * @param url - The URL string to validate.
 *
 * @return True if the URL is valid, false otherwise.
 */
const isValidUrl = ( url: string ): boolean => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch {
		return false;
	}
};

/**
 * Sanitizes a given string by removing all HTML tags.
 *
 * @param item - The string to sanitize.
 *
 * @return The sanitized string with all HTML tags removed.
 */
const PurifyElement = ( item: string ): string => {
	return DOMPurify.sanitize( item, { ALLOWED_TAGS: [] } );
};

export { getInitials, isURL, isValidUrl, PurifyElement };
