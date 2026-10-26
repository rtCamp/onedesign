<?php
/**
 * Registers the Pattern Library screen.
 *
 * @package OneDesign\Modules\Library
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Library;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Core\Assets;
use OneDesign\Modules\Settings\Admin as Settings_Admin;
use OneDesign\Modules\Settings\Settings;

/**
 * Class - Admin
 */
final class Admin implements Registrable {
	/**
	 * The screen ID of the Pattern Library.
	 */
	public const PATTERN_LIBRARY_SCREEN_ID = Settings_Admin::MENU_SLUG . '-pattern-library';

	/**
	 * The admin page hook of the Pattern Library.
	 */
	public const PATTERN_LIBRARY_HOOK = Settings_Admin::MENU_SLUG . '_page_' . self::PATTERN_LIBRARY_SCREEN_ID;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'load-' . self::PATTERN_LIBRARY_HOOK, [ $this, 'load_block_editor' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Registers the Pattern Library screen on a governing site.
	 */
	public function add_submenu(): void {
		if ( ! Settings::is_governing_site() || ! Settings::get_shared_sites() ) {
			return;
		}

		add_submenu_page(
			Settings_Admin::MENU_SLUG,
			__( 'Pattern Library', 'onedesign' ),
			__( 'Pattern Library', 'onedesign' ),
			'manage_options',
			self::PATTERN_LIBRARY_SCREEN_ID,
			[ $this, 'render_pattern_library' ],
			1
		);
	}

	/**
	 * Pattern Library content callback.
	 */
	public function render_pattern_library(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pattern Library', 'onedesign' ); ?></h1>
			<div id="onedesign-pattern-library"></div>
		</div>
		<?php
	}

	/**
	 * Marks the Pattern Library screen as a block editor.
	 * Like core's block screens, it happens before the admin header, so the block assets core enqueues there load too.
	 */
	public function load_block_editor(): void {
		get_current_screen()?->is_block_editor( true );
	}

	/**
	 * Enqueues the Pattern Library on its screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( self::PATTERN_LIBRARY_HOOK !== $hook ) {
			return;
		}

		$handle = Assets::PATTERN_LIBRARY_SCRIPT_HANDLE;

		/**
		 * The cards are block previews.
		 * Like core Widgets, the screen loads the blocks plugins and themes register, with their server definitions, so the previews render them too.
		 */
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ');'
		);
		// Like core Widgets, it has no inserter, so it doesn't load the Block Directory.
		remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
		do_action( 'enqueue_block_editor_assets' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's hook.

		// They render with this site's theme styles like the block editor does.
		$editor_settings = get_block_editor_settings(
			[ 'styles' => get_block_editor_theme_styles() ],
			new \WP_Block_Editor_Context( [ 'name' => 'onedesign/library' ] )
		);

		wp_localize_script( $handle, 'OneDesignSettings', Assets::get_localized_data() );
		wp_add_inline_script( $handle, sprintf( 'wp.data.dispatch( "core/block-editor" ).updateSettings( %s );', wp_json_encode( $editor_settings ) ), 'before' );
		// Some core blocks, like the query title, read the post being edited from the editor's store, so it loads first.
		wp_enqueue_script( 'wp-editor' );
		wp_enqueue_script( $handle );
		wp_enqueue_style( Assets::LIBRARY_STYLES_HANDLE );
	}
}
