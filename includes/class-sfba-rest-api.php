<?php
/**
 * REST API namespace registration and shared utilities.
 *
 * Registers the 'super-fast-blog-ai/v1' REST namespace and provides shared
 * helper methods (authentication, response helpers) used by all route handlers.
 *
 * Individual modules register their own routes by calling
 * SFBA_Rest_Api::register_route() from within their init() methods.
 *
 * Route naming convention:
 *   /super-fast-blog-ai/v1/generate          → content generation
 *   /super-fast-blog-ai/v1/generate/stream   → SSE streaming
 *   /super-fast-blog-ai/v1/titles            → title generation
 *   /super-fast-blog-ai/v1/settings          → settings CRUD
 *   /super-fast-blog-ai/v1/settings/api-key  → API key management
 *   /super-fast-blog-ai/v1/settings/test-key → connection test
 *   /super-fast-blog-ai/v1/settings/providers → provider list
 *   /super-fast-blog-ai/v1/brand-voice       → voice profile
 *   /super-fast-blog-ai/v1/calendar          → content calendar
 *   /super-fast-blog-ai/v1/performance       → performance data
 *   /super-fast-blog-ai/v1/routing           → model routing rules
 *   /super-fast-blog-ai/v1/repurpose         → content repurposing
 *   /super-fast-blog-ai/v1/internal-links    → internal linking
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Rest_Api {

	/**
	 * REST API namespace.
	 */
	public const NAMESPACE = 'super-fast-blog-ai/v1';

	/**
	 * @var SFBA_Core
	 */
	private SFBA_Core $core;

	/**
	 * Queued route registrations from modules.
	 *
	 * @var array<int, array{route: string, args: array}>
	 */
	private array $routes = [];

	/**
	 * @param SFBA_Core $core
	 */
	public function __construct( SFBA_Core $core ) {
		$this->core = $core;
	}

	// -------------------------------------------------------------------------
	// Hook registration.
	// -------------------------------------------------------------------------

	/**
	 * @param SFBA_Loader $loader
	 */
	public function init( SFBA_Loader $loader ): void {
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	// -------------------------------------------------------------------------
	// Route management.
	// -------------------------------------------------------------------------

	/**
	 * Queue a REST route for registration.
	 *
	 * Modules call this in their own init() before rest_api_init fires.
	 *
	 * @param string $route Relative route (e.g. '/generate').
	 * @param array  $args  register_rest_route() $args parameter.
	 */
	public function register_route( string $route, array $args ): void {
		$this->routes[] = [ 'route' => $route, 'args' => $args ];
	}

	/**
	 * Register all queued routes with WordPress.
	 *
	 * Hooked on 'rest_api_init'.
	 */
	public function register_routes(): void {
		foreach ( $this->routes as $entry ) {
			register_rest_route( self::NAMESPACE, $entry['route'], $entry['args'] );
		}
	}

	// -------------------------------------------------------------------------
	// Shared permission callbacks.
	// -------------------------------------------------------------------------

	/**
	 * Permission callback: requires logged-in user with 'edit_posts' capability.
	 *
	 * Suitable for: content generation, title generation, brand voice, calendar.
	 *
	 * @return bool|WP_Error
	 */
	public function require_editor( WP_REST_Request $request ): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'sfba_unauthenticated',
				__( 'You must be logged in to use Super Fast Blog AI.', 'super-fast-blog-ai' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'sfba_forbidden',
				__( 'You do not have permission to perform this action.', 'super-fast-blog-ai' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission callback: requires 'manage_options' (admin only).
	 *
	 * Suitable for: settings, routing rules, provider configuration.
	 *
	 * @return bool|WP_Error
	 */
	public function require_admin( WP_REST_Request $request ): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'sfba_forbidden',
				__( 'Administrator access required.', 'super-fast-blog-ai' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Shared response helpers.
	// -------------------------------------------------------------------------

	/**
	 * Build a standardized success response.
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	public static function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			[ 'success' => true, 'data' => $data ],
			$status
		);
	}

	/**
	 * Build a standardized error response.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable message (already translated).
	 * @param int    $status  HTTP status code (default 400).
	 * @return WP_REST_Response
	 */
	public static function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			[ 'success' => false, 'code' => $code, 'message' => $message ],
			$status
		);
	}
}
