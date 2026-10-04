/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';
/**
 * Internal dependencies
 */
import OnboardingScreen, { type SiteType } from './page';

interface OneDesignSettings {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneDesignSettings: OneDesignSettings;
	}
}

// Render to the target element.
const target = document.getElementById( 'onedesign-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
