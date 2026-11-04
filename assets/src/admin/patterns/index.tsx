import { registerCoreBlocks } from '@wordpress/block-library';
import { Button, Modal } from '@wordpress/components';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import PatternLibrary from './page';

// The cards preview blocks, and outside the block editor nothing else registers them.
registerCoreBlocks();

// Closing the library goes back to where the admin came from, or to the dashboard when it was opened on its own.
const close = () => {
	if ( window.history.length > 1 ) {
		window.history.back();
	} else {
		window.location.assign( 'index.php' );
	}
};

const target = document.getElementById( 'onedesign-pattern-library' );
if ( target ) {
	createRoot( target ).render(
		<Modal
			title={ __( 'Pattern Library', 'onedesign' ) }
			onRequestClose={ close }
			headerActions={
				<Button
					icon={ cog }
					variant="secondary"
					href={ window.OneDesignSettings.setupUrl }
					label={ __( 'Go to OneDesign Settings', 'onedesign' ) }
				/>
			}
			isFullScreen
		>
			<PatternLibrary />
		</Modal>
	);
}
