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
 * Validates if a given string is a valid http or https URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url: string ): boolean => {
	try {
		return [ 'http:', 'https:' ].includes( new URL( url ).protocol );
	} catch {
		return false;
	}
};

/**
 * WordPress REST API base URL for OneDesign plugin requests.
 *
 * @constant
 * @type {string}
 */
export const API_NAMESPACE =
	window.OneDesignSettings.restUrl + window.OneDesignSettings.restNamespace;

/**
 * The API key used for authenticating requests to the OneDesign REST API.
 */
export const API_KEY = window.OneDesignSettings.api_key;

/**
 * WordPress REST API nonce for authenticated requests.
 *
 * @constant
 * @type {string}
 */
export const NONCE = window.OneDesignSettings.nonce as string;

/**
 * Sites on this multisite network, for picking the governing site and adding brand sites.
 */
export const MULTISITES = window.OneDesignSettings.multisites;

/**
 * Whether the network has picked its governing site.
 */
export const IS_GOVERNING_SITE_SELECTED =
	window.OneDesignSettings.isGoverningSiteSelected;

/**
 * Current site's blog ID on the network.
 */
export const CURRENT_SITE_ID = window.OneDesignSettings.currentSiteId;
