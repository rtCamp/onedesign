import { registerCoreBlocks } from '@wordpress/block-library';
import { createRoot } from '@wordpress/element';
import PatternLibrary from './page';

// The cards preview blocks, and outside the block editor nothing else registers them.
registerCoreBlocks();

const target = document.getElementById( 'onedesign-pattern-library' );
if ( target ) {
	createRoot( target ).render( <PatternLibrary /> );
}
