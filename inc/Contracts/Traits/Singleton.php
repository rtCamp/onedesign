<?php
/**
 * Singleton trait.
 *
 * @package OneDesign\Contracts\Traits
 */

declare(strict_types = 1);

namespace OneDesign\Contracts\Traits;

/**
 * Singleton trait.
 */
trait Singleton {
	/**
	 * Instance of the class.
	 *
	 * @var ?static
	 */
	protected static $instance;

	/**
	 * Prevent the class from being instantiated directly.
	 */
	protected function __construct() {
		// To be implemented by the class using the trait.
	}

	/**
	 * Get the instance of the class.
	 *
	 * @return static
	 */
	final public static function instance() {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Prevent the class from being cloned.
	 */
	final public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'The %s class should not be cloned.', 'onedesign' ),
				esc_html( static::class ),
			),
			'0.0.1'
		);
	}

	/**
	 * Prevent the class from being deserialized.
	 */
	final public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'De-serializing instances of %s is not allowed.', 'onedesign' ),
				esc_html( static::class ),
			),
			'0.0.1'
		);
	}
}
