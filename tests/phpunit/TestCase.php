<?php
/**
 * Provide a base class for all unit tests by extending WP_UnitTestCase.
 *
 * @package OneDesign\Tests
 */

declare( strict_types = 1 );

namespace OneDesign\Tests;

use WP_UnitTestCase;

/**
 * Class - TestCase
 */
abstract class TestCase extends WP_UnitTestCase {
	/**
	 * REST server replaced by a test, restored after its routes are discarded.
	 */
	private ?\WP_REST_Server $original_rest_server = null;

	/**
	 * Whether this test owns a temporary REST server.
	 */
	private bool $rest_server_replaced = false;

	/**
	 * Active HTTP interceptor, replaced when a test changes its responder.
	 */
	private ?\Closure $http_mock = null;

	/**
	 * Globals replaced by fixtures, including whether they originally existed.
	 *
	 * @var array<string,array{exists:bool,value:mixed}>
	 */
	private array $original_globals = [];

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		parent::tearDown();

		if ( $this->rest_server_replaced ) {
			$GLOBALS['wp_rest_server'] = $this->original_rest_server; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the server replaced by this test.
		}
		foreach ( $this->original_globals as $name => $original ) {
			if ( $original['exists'] ) {
				$GLOBALS[ $name ] = $original['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
	}

	/**
	 * Preserve global fixtures that WordPress does not reset between plugin tests.
	 *
	 * @param string[] $names Global names.
	 */
	protected function preserve_globals( array $names ): void {
		foreach ( $names as $name ) {
			$this->original_globals[ $name ] = [
				'exists' => array_key_exists( $name, $GLOBALS ),
				'value'  => $GLOBALS[ $name ] ?? null,
			];
		}
	}

	/**
	 * Give REST integration tests fresh routes without leaking them to later tests.
	 *
	 * @param array<\OneDesign\Modules\Rest\Abstract_REST_Controller> $controllers Controllers to register.
	 */
	protected function initialize_rest_server( array $controllers ): void {
		$this->original_rest_server = $GLOBALS['wp_rest_server'] ?? null;
		$this->rest_server_replaced = true;
		$GLOBALS['wp_rest_server']  = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate route registration.

		foreach ( $controllers as $controller ) {
			$controller->register_hooks();
		}
		do_action( 'rest_api_init', $GLOBALS['wp_rest_server'] );
	}

	/**
	 * Dispatch JSON through WordPress validation and permission callbacks.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $route   Absolute REST route.
	 * @param array<string,mixed>  $body    JSON body.
	 * @param array<string,string> $headers Request headers.
	 */
	protected function dispatch_rest( string $method, string $route, array $body = [], array $headers = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Prevents wp-phpunit failures with PHPUnit 11.5.
	 *
	 * @return array<string, array<string, list<string>>>
	 */
	public function getAnnotations(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Required compatibility method name.
		$class_reflection  = new \ReflectionClass( static::class );
		$method_name       = method_exists( $this, 'name' ) ? $this->name() : $this->getName( false );
		$method_reflection = $class_reflection->hasMethod( $method_name )
			? $class_reflection->getMethod( $method_name )
			: null;

		return [
			'class'  => self::parse_docblock_annotations( $class_reflection->getDocComment() ?: '' ),
			'method' => self::parse_docblock_annotations( $method_reflection?->getDocComment() ?: '' ),
		];
	}

	/**
	 * Parse selected docblock tags used in WP unit testing expectations.
	 *
	 * @param string $docblock Source docblock.
	 *
	 * @return array<string, list<string>>
	 */
	private static function parse_docblock_annotations( string $docblock ): array {
		if ( '' === trim( $docblock ) ) {
			return [];
		}

		$annotations = [];
		$tags        = [
			'ticket',
			'group',
			'expectedDeprecated',
			'expectedIncorrectUsage',
		];

		foreach ( $tags as $tag ) {
			$matches = [];
			preg_match_all( '/^[ \\t\\*]*@' . preg_quote( $tag, '/' ) . '\\s+([^\\r\\n\\*]+)/mi', $docblock, $matches );

			if ( ! empty( $matches[1] ) ) {
				$annotations[ $tag ] = array_values(
					array_filter(
						array_map( 'trim', $matches[1] ),
						static fn ( string $value ): bool => '' !== $value
					)
				);
			}
		}

		return $annotations;
	}

	/**
	 * Hides legacy WordPress docblock metadata from PHPUnit 11.
	 *
	 * @deprecated
	 */
	protected function checkRequirements(): void { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Prevent PHPUnit from parsing the inherited docblock.
		parent::checkRequirements();
	}

	/**
	 * Outgoing HTTP requests captured by mock_http().
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	protected array $http_requests = [];

	/**
	 * Short-circuit outgoing HTTP requests and record them in $http_requests.
	 *
	 * @param callable(string, array<string, mixed>): (array<string, mixed>|\WP_Error) $responder Builds the response for a URL and request args.
	 */
	protected function mock_http( callable $responder ): void {
		if ( null !== $this->http_mock ) {
			remove_filter( 'pre_http_request', $this->http_mock );
		}
		$this->http_mock = function ( $preempt, $args, $url ) use ( $responder ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
				$this->http_requests[] = [
					'url'  => $url,
					'args' => $args,
				];

				return $responder( $url, $args );
		};
		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );
	}

	/**
	 * Build a mocked HTTP response array.
	 *
	 * @param int                 $code Response status code.
	 * @param array<mixed>|string $body Response body; arrays are JSON-encoded.
	 *
	 * @return array<string, mixed>
	 */
	protected static function http_response( int $code, array|string $body = [] ): array {
		return [
			'headers'  => [],
			'cookies'  => [],
			'body'     => is_array( $body ) ? (string) wp_json_encode( $body ) : $body,
			'response' => [
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			],
		];
	}

	/**
	 * Turn redirects into exceptions so code that calls `exit` after redirecting can be tested.
	 *
	 * The exception message is the redirect location.
	 */
	protected function intercept_redirects(): void {
		add_filter(
			'wp_redirect',
			static function ( string $location ): never {
				throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
	}
}
