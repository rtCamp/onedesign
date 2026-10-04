<?php

declare(strict_types = 1);

/**
 * This file is to handle OneDesign Multisite related functionality.
 *
 * @package OneDesign
 */

namespace OneDesign\Modules\Multisite;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Multisite\Settings as MU_Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// check if current site setup is multisite or not.
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );

		// add governing site selection modal on network admin plugins page.
		add_action( 'admin_footer', [ $this, 'render_governing_site_modal' ] );

		// add admin_body_class class of onedesign-multisite-selection-modal on network admin plugins page.
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || 'plugins-network' !== $current_screen->id || MU_Settings::is_governing_site_selected() ) {
			return;
		}

		wp_localize_script(
			Assets::MULTISITE_SETUP_SCRIPT_HANDLE,
			'OneDesignMultiSiteSettings',
			array_merge(
				Assets::get_localized_data(),
				[
					'multisites' => MU_Settings::get_all_multisites_info(),
				]
			)
		);

		wp_enqueue_script( Assets::MULTISITE_SETUP_SCRIPT_HANDLE );

		// @todo Move other scripts from Assets to here.
	}

	/**
	 * Render governing site selection modal.
	 */
	public function render_governing_site_modal(): void {

		if ( ! is_network_admin() ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || 'plugins-network' !== $current_screen->id ) {
			return;
		}

		if ( MU_Settings::is_governing_site_selected() ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="onedesign-multisite-selection-modal" class="onedesign-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add admin body class for governing site selection modal.
	 *
	 * @param string $classes Existing admin body classes.
	 * @return string Modified admin body classes.
	 */
	public function add_admin_body_class( string $classes ): string {

		if ( MU_Settings::is_governing_site_selected() ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( is_network_admin() && $current_screen instanceof \WP_Screen && 'plugins-network' === $current_screen->id ) {
			$classes .= ' onedesign-multisite-selection-modal ';
		}
		return $classes;
	}
}
