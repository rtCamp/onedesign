<?php
/**
 * Registers the network admin screen where network admins set up the network's sites.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Multisite;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Multisite\Utils as MU_Utils;
use OneDesign\Modules\Settings\Admin as Settings_Admin;

/**
 * Class Admin
 */
final class Admin implements Registrable {
	/**
	 * The screen ID of the network screen.
	 */
	public const SCREEN_ID = Settings_Admin::MENU_SLUG . '-network';

	/**
	 * The admin page hook of the network screen, under Network Admin > Settings.
	 */
	public const SCREEN_HOOK = 'settings_page_' . self::SCREEN_ID;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// A plugin active only on the main site still loads in the network admin, where the routes the screen uses don't exist.
		if ( ! MU_Utils::is_network_activated() ) {
			return;
		}

		add_action( 'network_admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'network_admin_notices', [ $this, 'render_notices' ] );
	}

	/**
	 * Registers the network screen.
	 */
	public function add_submenu(): void {
		add_submenu_page(
			'settings.php',
			__( 'OneDesign', 'onedesign' ),
			__( 'OneDesign', 'onedesign' ),
			'manage_network_options',
			self::SCREEN_ID,
			[ $this, 'screen_callback' ]
		);
	}

	/**
	 * Network screen content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OneDesign', 'onedesign' ); ?></h1>
			<div id="onedesign-network-settings"></div>
		</div>
		<?php
	}

	/**
	 * Enqueues the network screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( self::SCREEN_HOOK !== $hook ) {
			return;
		}

		$localized_data = Assets::get_localized_data();

		// Listing the network's sites switches into each one, so only this screen does it.
		$localized_data['multisites'] = MU_Utils::get_all_multisites_info();

		wp_localize_script( Assets::NETWORK_SETTINGS_SCRIPT_HANDLE, 'OneDesignSettings', $localized_data );
		wp_enqueue_script( Assets::NETWORK_SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ADMIN_STYLES_HANDLE );
	}

	/**
	 * Renders the network setup notice.
	 */
	public function render_notices(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// The network screen shows the setup itself.
		if ( in_array( get_current_screen()?->base, [ 'plugins-network', 'dashboard-network' ], true ) && ! MU_Utils::has_site_with_type() ) {
			wp_admin_notice(
				sprintf(
					/* translators: %s: URL of the OneDesign network screen. */
					__( 'OneDesign needs to be set up. <a href="%s">Make a site on this network a governing site</a> to get started.', 'onedesign' ),
					esc_url( network_admin_url( 'settings.php?page=' . self::SCREEN_ID ) )
				),
				[
					'type'        => 'info',
					'dismissible' => true,
				]
			);
		}
	}
}
