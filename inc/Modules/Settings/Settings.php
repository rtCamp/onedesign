<?php
/**
 * Registers the plugin's settings and options
 *
 * @package OneDesign\Modules\Settings
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Settings;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Encryptor;

/**
 * Class - Settings
 */
final class Settings implements Registrable {
	/**
	 * The setting prefix.
	 */
	private const SETTING_PREFIX = 'onedesign_';

	/**
	 * The setting group.
	 */
	public const SETTING_GROUP = self::SETTING_PREFIX . 'settings';

	/**
	 * Setting keys
	 */
	// Shared settings.
	public const OPTION_SITE_TYPE = self::SETTING_PREFIX . 'site_type';

	// Consumer settings.
	public const OPTION_CONSUMER_API_KEY         = self::SETTING_PREFIX . 'consumer_api_key';
	public const OPTION_CONSUMER_PARENT_SITE_URL = self::SETTING_PREFIX . 'parent_site_url';

	// Governing settings.
	public const OPTION_GOVERNING_SHARED_SITES = self::SETTING_PREFIX . 'shared_sites';

	/**
	 * Site type keys.
	 */
	public const SITE_TYPE_CONSUMER  = 'brand-site';
	public const SITE_TYPE_GOVERNING = 'governing-site';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );

		// Listen to updates.
		add_action( 'update_option_' . self::OPTION_SITE_TYPE, [ $this, 'on_site_type_change' ], 10, 2 );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		$shared_settings = [
			self::OPTION_SITE_TYPE => [
				'type'              => 'string',
				'label'             => __( 'Site Type', 'onedesign' ),
				'description'       => __( 'Defines whether this site is a governing or a brand site.', 'onedesign' ),
				'sanitize_callback' => static function ( $value ): string {
					$valid_values = [
						self::SITE_TYPE_CONSUMER  => true,
						self::SITE_TYPE_GOVERNING => true,
					];

					return is_string( $value ) && isset( $valid_values[ $value ] ) ? $value : '';
				},
				'show_in_rest'      => [
					'schema' => [
						'enum' => [ self::SITE_TYPE_CONSUMER, self::SITE_TYPE_GOVERNING ],
					],
				],
			],
		];

		$consumer_settings = [
			self::OPTION_CONSUMER_API_KEY         => [
				'type'              => 'string',
				'label'             => __( 'Consumer API Key', 'onedesign' ),
				'description'       => __( 'API key used by governing site to authenticate requests from this consumer site.', 'onedesign' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
					],
				],
			],
			self::OPTION_CONSUMER_PARENT_SITE_URL => [
				'type'              => 'string',
				'label'             => __( 'Parent Site URL', 'onedesign' ),
				'description'       => __( 'The URL of the governing site that manages this consumer site.', 'onedesign' ),
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) ? untrailingslashit( esc_url_raw( $value ) ) : null;
				},
				'show_in_rest'      => [
					'schema' => [
						'type'   => 'string',
						'format' => 'uri',
					],
				],
			],
		];

		$governing_settings = [
			self::OPTION_GOVERNING_SHARED_SITES => [
				'type'              => 'array',
				'label'             => __( 'Brand Sites', 'onedesign' ),
				'description'       => __( 'An array of brand sites connected to this governing site.', 'onedesign' ),
				'sanitize_callback' => [ self::class, 'sanitize_shared_sites' ],
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'      => [
									'type' => 'string',
								],
								'name'    => [
									'type' => 'string',
								],
								'url'     => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'logo'    => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'logo_id' => [
									'type' => 'integer',
								],
								'api_key' => [
									'type' => 'string',
								],
							],
						],
					],
				],
			],
		];

		$all_settings = array_merge(
			$shared_settings,
			self::is_consumer_site() ? $consumer_settings : $governing_settings
		);

		foreach ( $all_settings as $key => $args ) {
			register_setting(
				self::SETTING_GROUP,
				$key,
				$args
			);
		}
	}

	/**
	 * Ensures the API key is generated when the site type changes to 'consumer'.
	 *
	 * @internal Hook callback
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value.
	 */
	public function on_site_type_change( $old_value, $new_value ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		if ( self::SITE_TYPE_CONSUMER !== $new_value ) {
			return;
		}

		// By getting the API key, it will be generated if it doesn't exist.
		self::get_api_key();
	}

	/**
	 * Sanitize the `shared_sites` option.
	 *
	 * @param mixed $input The input value.
	 *
	 * @return array{
	 * id: string,
	 * name: string,
	 * url: string,
	 * logo: string,
	 * logo_id: int,
	 * api_key: string
	 * }[]
	 */
	public static function sanitize_shared_sites( $input ): array {
		if ( ! is_array( $input ) || empty( $input ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $input as $site_data ) {
			if ( ! is_array( $site_data ) ) {
				continue;
			}

			$site_id      = isset( $site_data['id'] ) ? sanitize_text_field( $site_data['id'] ) : '';
			$site_name    = isset( $site_data['name'] ) ? sanitize_text_field( $site_data['name'] ) : '';
			$site_url     = isset( $site_data['url'] ) ? esc_url_raw( $site_data['url'] ) : '';
			$site_logo    = isset( $site_data['logo'] ) ? esc_url_raw( $site_data['logo'] ) : '';
			$site_logo_id = isset( $site_data['logo_id'] ) ? absint( $site_data['logo_id'] ) : 0;
			$site_api_key = isset( $site_data['api_key'] ) ? sanitize_text_field( $site_data['api_key'] ) : '';

			// Only save if required fields are filled.
			if ( empty( $site_name ) || empty( $site_url ) ) {
				continue;
			}

			$sanitized[] = [
				'id'      => $site_id ?: wp_generate_uuid4(),
				'name'    => $site_name,
				'url'     => untrailingslashit( $site_url ),
				'logo'    => $site_logo,
				'logo_id' => $site_logo_id,
				'api_key' => $site_api_key,
			];
		}

		return $sanitized;
	}

	/**
	 * Static setters and getters for the individual settings.
	 */

	/**
	 * Get brand sites configured for this governing site.
	 *
	 * @return array<string,array{
	 *  api_key: string,
	 *  id: string,
	 *  logo: string,
	 *  logo_id: int,
	 *  name: string,
	 *  url: string,
	 * }>
	 */
	public static function get_shared_sites(): array {
		$brands = get_option( self::OPTION_GOVERNING_SHARED_SITES, null ) ?: [];

		$brands_to_return = [];
		foreach ( $brands as $brand ) {
			if ( empty( $brand['url'] ) ) {
				continue;
			}

			$brands_to_return[ $brand['url'] ] = [
				'api_key' => $brand['api_key'] ?? '',
				'id'      => $brand['id'] ?? '',
				'logo'    => $brand['logo'] ?? '',
				'logo_id' => $brand['logo_id'] ?? 0,
				'name'    => $brand['name'] ?? '',
				'url'     => $brand['url'] ?? '',
			];
		}

		return $brands_to_return;
	}

	/**
	 * Get a single brand site by URL
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return ?array{
	 *   api_key: string,
	 *   id: string,
	 *   logo: string,
	 *   logo_id: int,
	 *   name: string,
	 *   url: string,
	 * }
	 */
	public static function get_shared_site_by_url( string $site_url ): ?array {
		$brand_sites = self::get_shared_sites();

		$normalized_url = trailingslashit( $site_url );

		return $brand_sites[ $normalized_url ] ?? null;
	}

	/**
	 * Set the shared sites.
	 *
	 * @param array<string,array<string,mixed>> $sites The sites to set.
	 *
	 * @phpstan-param array<string,array{
	 *   api_key?: string,
	 *   id?: string,
	 *   logo?: string,
	 *   logo_id?: int,
	 *   name?: string,
	 *   url?: string,
	 *   is_editable?: bool
	 * }> $sites The sites to set.
	 */
	public static function set_shared_sites( array $sites ): bool {
		return update_option( self::OPTION_GOVERNING_SHARED_SITES, array_values( $sites ), false );
	}

	/**
	 * Get the current site type.
	 */
	public static function get_site_type(): ?string {
		$value = get_option( self::OPTION_SITE_TYPE, null );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Check if the current site is a governing site.
	 */
	public static function is_governing_site(): bool {
		return self::SITE_TYPE_GOVERNING === self::get_site_type();
	}

	/**
	 * Check if the current site is a consumer site.
	 */
	public static function is_consumer_site(): bool {
		return self::SITE_TYPE_CONSUMER === self::get_site_type();
	}

	/**
	 * Gets the API key, generating a new one if it doesn't exist.
	 *
	 * Returns an empty string on failure.
	 */
	public static function get_api_key(): string {
		$api_key = get_option( self::OPTION_CONSUMER_API_KEY, '' );

		$api_key = ! empty( $api_key ) ? Encryptor::decrypt( $api_key ) : self::regenerate_api_key();

		return $api_key ?: '';
	}

	/**
	 * Regenerates the API key.
	 *
	 * @return string The new (unencrypted) API key.
	 */
	public static function regenerate_api_key(): string {
		$api_key = self::generate_api_key();

		$encrypted_key = Encryptor::encrypt( $api_key );

		if ( ! $encrypted_key ) {
			return '';
		}

		update_option( self::OPTION_CONSUMER_API_KEY, $encrypted_key, false );
		if ( is_multisite() ) {
			/**
			 * Trigger action when API key is generated in multisite setup.
			 *
			 * @param string $secret_key The generated secret key.
			 * @param int $blog_id The blog ID where the key is generated.
			 *
			 * @hook onedesign_regenerate_api_key
			 */
			do_action( 'onedesign_regenerate_api_key', $api_key, get_current_blog_id() );
		}

		return $api_key;
	}

	/**
	 * Get the parent URL for consumer sites.
	 */
	public static function get_parent_site_url(): ?string {
		$value = get_option( self::OPTION_CONSUMER_PARENT_SITE_URL, null );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Set the parent URL for consumer sites.
	 *
	 * @param string $url The parent site URL.
	 */
	public static function set_parent_site_url( string $url ): bool {
		return update_option( self::OPTION_CONSUMER_PARENT_SITE_URL, untrailingslashit( esc_url_raw( $url ) ), false );
	}

	/**
	 * Generate a random API key.
	 */
	private static function generate_api_key(): string {
		return wp_generate_password( 128, false, false );
	}
}
