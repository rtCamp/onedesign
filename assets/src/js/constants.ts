/**
 * PHP consts for JS usage.
 *
 * @package
 */

interface OneDesignSettingsData {
	restUrl?: string;
	restNonce?: string;
	apiKey?: string;
	settingsLink?: string;
	multisites?: unknown[];
	isMultisite?: boolean;
	isGoverningSiteSelected?: boolean;
	currentSiteId?: number | string;
}

/**
 * The settings payload is injected onto `window` via `wp_localize_script` under one of several
 * keys depending on the screen. Read it through a local view of `window` so this module owns its
 * own typing — the `OneDesignSettings` key is also declared, differently, by the onboarding entry.
 */
const globalSettings = window as unknown as {
	OneDesignSettings?: OneDesignSettingsData;
	patternSyncData?: OneDesignSettingsData;
	TemplateLibraryData?: OneDesignSettingsData;
	OneDesignMultiSiteSettings?: OneDesignSettingsData;
};

let settings: OneDesignSettingsData = {};

if ( typeof globalSettings.OneDesignSettings !== 'undefined' ) {
	settings = globalSettings.OneDesignSettings;
} else if ( typeof globalSettings.patternSyncData !== 'undefined' ) {
	settings = globalSettings.patternSyncData;
} else if ( typeof globalSettings.TemplateLibraryData !== 'undefined' ) {
	settings = globalSettings.TemplateLibraryData;
} else if ( typeof globalSettings.OneDesignMultiSiteSettings !== 'undefined' ) {
	settings = globalSettings.OneDesignMultiSiteSettings;
}

const ONEDESIGN_REST_NAME = 'onedesign';
const ONEDESIGN_REST_VERSION = 'v1';

const API_NAMESPACE = settings.restUrl
	? settings.restUrl + `/${ ONEDESIGN_REST_NAME }/${ ONEDESIGN_REST_VERSION }`
	: '';
const NONCE = settings.restNonce ? settings.restNonce : '';
const API_KEY = settings.apiKey ? settings.apiKey : '';
const SETTINGS_LINK = settings.settingsLink ? settings.settingsLink : '';
const PER_PAGE = 9;
const MULTISITES = settings.multisites || [];
const IS_MULTISITE = settings.isMultisite || false;
const IS_GOVERNING_SITE_SELECTED = settings.isGoverningSiteSelected || false;
const CURRENT_SITE_ID =
	settings.currentSiteId !== undefined && settings.currentSiteId !== null
		? String(settings.currentSiteId)
		: '';

export {
	API_KEY,
	API_NAMESPACE,
	CURRENT_SITE_ID,
	IS_GOVERNING_SITE_SELECTED,
	IS_MULTISITE,
	MULTISITES,
	NONCE,
	PER_PAGE,
	SETTINGS_LINK,
};
