import { LIBRARY_PAGES, navigateBack } from '@/js/navigation';
import { registerCoreBlocks } from '@wordpress/block-library';
import { Button, Modal } from '@wordpress/components';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import PatternLibrary from './page';

// The cards preview blocks, and outside the block editor nothing else registers them.
registerCoreBlocks();

const target = document.getElementById( 'onedesign-pattern-library' );
if ( target ) {
	const root = createRoot( target );
	root.render(
		<Modal
			title={ __( 'Pattern Library', 'onedesign' ) }
			onRequestClose={ () => {
				root.unmount();
				navigateBack( LIBRARY_PAGES );
			} }
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
