/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PatternModal from './components/PatternModal';

/**
 * Registers the Pattern Sync Library plugin.
 *
 * @return {void}
 */
registerPlugin( 'onedesign-library', {
	render: () => {
		if ( typeof createRoot !== 'function' ) {
			return null;
		}

		const className = 'onedesign-library';
		const modalID = 'onedesign-library-modal';

		if ( document.getElementById( modalID ) ) {
			return null;
		}

		const modalWrap = document.createElement( 'div' );
		const modal = Object.assign( modalWrap, { id: modalID, className } );
		document.body?.appendChild( modal );
		createRoot( modal ).render( <PatternModal /> );

		// make document.title to Pattern Library.
		document.title = __( 'Pattern Library', 'onedesign' );

		return null;
	},
} );
