/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import NetworkSettingsPage from './page';

// Render to the network admin page with ID: onedesign-network-settings
const target = document.getElementById( 'onedesign-network-settings' );
if ( target ) {
	const root = createRoot( target );
	root.render( <NetworkSettingsPage /> );
}
