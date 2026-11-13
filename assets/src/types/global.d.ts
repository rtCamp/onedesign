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
	siteType: SiteType;
	canAddAsBrandSite: boolean;
	manageUrl: string;
}

export interface OneDesignSettings {
	api_key: string;
	setupUrl: string;
	siteType: SiteType | null;
	sharedSites?: OneDesignSharedSite[];
	// Only the network screen and a governing site's settings screen for network admins list the network's sites.
	multisites?: OneDesignMultisite[];
	currentSiteId?: string;
	deleteDataOnDeactivation?: boolean;
}

declare global {
	interface Window {
		OneDesignSettings: OneDesignSettings;
	}
}
