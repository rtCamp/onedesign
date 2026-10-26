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

export interface OneDesignMultisite {
	id: string;
	name: string;
	url: string;
}

export interface OneDesignSettings {
	restUrl: string;
	nonce: string;
	api_key: string;
	setupUrl: string;
	sharedSites?: OneDesignSharedSite[];
	restNamespace: string;
	multisites: OneDesignMultisite[];
	isGoverningSiteSelected: boolean;
	currentSiteId: number;
}

declare global {
	interface Window {
		OneDesignSettings: OneDesignSettings;
	}
}
