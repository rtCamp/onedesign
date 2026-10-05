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
export const getInitials = ( name: string ): string => {
	// Handle empty or invalid names
	if ( ! name || typeof name !== 'string' ) {
		return '?';
	}

	// Trim the name and convert to proper case
	const trimmedName = name.trim();
	if ( ! trimmedName ) {
		return '?';
	}

	// Split the name by spaces and other separators
	const parts = trimmedName
		.split( /[\s-_,.]+/ )
		.filter( ( part ) => part.length > 0 );

	const [ first, second ] = parts;
	if ( ! first ) {
		return '?';
	}

	// For single word names
	if ( parts.length === 1 ) {
		// If name is a single character, return that character
		if ( first.length === 1 ) {
			return first.toUpperCase();
		}
		// Otherwise return first two characters
		return first.substring( 0, 2 ).toUpperCase();
	}

	// For multi-word names, take first letter of first two parts
	return (
		first.charAt( 0 ) + ( second ? second.charAt( 0 ) : '' )
	).toUpperCase();
};
/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url: string ): boolean => {
	try {
		new URL( url );
		return true;
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
export const PurifyElement = ( item: string ): string => {
	return DOMPurify.sanitize( item, { ALLOWED_TAGS: [] } );
};
