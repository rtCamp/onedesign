<?php
/**
 * Registers the Admin menu and settings screen.
 *
 * @package OneDesign\Modules\Settings
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Settings;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Multisite\Utils as MU_Utils;

/**
 * Class - Admin
 */
final class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo replace with a cross-plugin menu.
	 */
	public const MENU_SLUG = 'onedesign';

	/**
	 * The screen ID for the settings page.
	 */
	public const SCREEN_ID = self::MENU_SLUG . '-settings';

	/**
	 * Path to the SVG logo for the menu.
	 *
	 * @var string
	 */
	private const SVG_LOGO_PATH = 'build/images/logo.svg';

	/**
	 * The admin page hook of the settings page.
	 */
	public const SETTINGS_HOOK = self::MENU_SLUG . '_page_' . self::SCREEN_ID;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );

		add_filter( 'plugin_action_links_' . ONEDESIGN_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneDesign', 'onedesign' ),
			__( 'OneDesign', 'onedesign' ),
			'manage_options',
			self::MENU_SLUG,
			'__return_null',
			ONEDESIGN_URL . self::SVG_LOGO_PATH,
			2
		);
	}

	/**
	 * Register the settings page.
	 */
	public function add_submenu(): void {
		// Add the settings submenu page.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'onedesign' ),
			__( 'Settings', 'onedesign' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'screen_callback' ],
			999
		);
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Admin page content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onedesign' ); ?></h1>
			<div id="onedesign-settings-page"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( self::SETTINGS_HOOK !== $hook ) {
			return;
		}

		$localized_data = Assets::get_localized_data();

		// Network admins add the network's sites to a governing site from here. Listing them switches into each site, so no other site screen does.
		if ( MU_Utils::is_network_activated() && current_user_can( 'manage_network_options' ) && Settings::is_governing_site() ) {
			$localized_data['multisites']    = array_values(
				array_filter(
					MU_Utils::get_all_multisites_info(),
					static fn ( array $site ): bool => $site['canAddAsBrandSite']
				)
			);
			$localized_data['currentSiteId'] = (string) get_current_blog_id();
		}

		wp_localize_script( Assets::SETTINGS_SCRIPT_HANDLE, 'OneDesignSettings', $localized_data );
		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ADMIN_STYLES_HANDLE );

		// The brand site logo field picks images from the media library.
		if ( Settings::is_governing_site() ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Renders the setup admin notice.
	 */
	public function render_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The settings page shows the site type picker itself.
		if ( $this->needs_setup() && in_array( get_current_screen()?->base, [ 'plugins', 'dashboard' ], true ) ) {
			wp_admin_notice(
				sprintf(
					/* translators: %s: URL of the OneDesign settings page. */
					__( 'OneDesign needs to be set up. <a href="%s">Choose whether this is a governing or a brand site</a> to get started.', 'onedesign' ),
					esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) )
				),
				[
					'type'        => 'info',
					'dismissible' => true,
				]
			);
		}
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		// Defense against other plugins.
		if ( ! is_array( $links ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'onedesign' ), '1.0.0' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'onedesign' )
		);

		return $links;
	}

	/**
	 * Whether the site still has to choose its type.
	 */
	private function needs_setup(): bool {
		return empty( Settings::get_site_type() );
	}
}
