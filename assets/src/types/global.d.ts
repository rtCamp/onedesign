/**
 * Global type declarations for OneDesign.
 *
 * These types describe the window globals injected by WordPress PHP code.
 */

export type SiteType = 'governing-site' | 'brand-site' | '';

export interface OneDesignSharedSite {
	id: string;
	name: string;
	url: string;
	logo: string;
	logo_id: number;
}

export interface OneDesignSettings {
	restUrl: string;
	restNonce: string;
	apiKey: string;
	settingsLink: string;
	setupUrl: string;
	sharedSites?: OneDesignSharedSite[];
}

declare global {
	interface Window {
		OneDesignSettings: OneDesignSettings;
	}
}
