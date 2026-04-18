<?php
/**
 * Settings management — wraps get_option/update_option with typed helpers.
 *
 * Single source of truth for all plugin configuration.
 * Renders the admin settings page and handles all REST endpoints for the
 * admin UI (Providers, Brand Voice, Content, Schedule, General tabs).
 *
 * REST endpoints registered here:
 *   GET  /settings              → all settings (no raw keys)
 *   POST /settings              → update general / content / schedule settings
 *   POST /settings/api-key      → save or clear a provider API key
 *   POST /settings/test-key     → validate stored key for a provider
 *   GET  /settings/providers    → list providers with connection status
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Settings {

	/**
	 * @var SFBA_Core
	 */
	private SFBA_Core $core;

	/**
	 * In-memory cache of the options array.
	 *
	 * @var array|null
	 */
	private ?array $options = null;

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
		// Top-level menu + dashboard submenu — priority 10.
		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );
		// Settings submenu — priority 99 so it always appears last.
		$loader->add_action( 'admin_menu', $this, 'register_settings_menu', 99 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
		$loader->add_action( 'admin_head', $this, 'inject_menu_icon_css' );

		// REST routes.
		$this->core->rest_api->register_route( '/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_settings' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_update_settings' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
		] );

		$this->core->rest_api->register_route( '/settings/api-key', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_save_api_key' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'provider' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
					'api_key'  => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/settings/test-key', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_test_api_key' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'provider' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/settings/providers', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_providers' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
		] );

		// Dedicated endpoint for Ollama base URL + optional bearer token.
		$this->core->rest_api->register_route( '/settings/ollama', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_save_ollama' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'base_url'     => [ 'type' => 'string', 'required' => false, 'default' => '', 'sanitize_callback' => 'esc_url_raw' ],
					'bearer_token' => [ 'type' => 'string', 'required' => false, 'default' => '' ],
				],
			],
		] );

		// Dedicated endpoint for image-service keys (Pixabay, Unsplash).
		// These are not AI providers so they live outside the providers registry.
		$this->core->rest_api->register_route( '/settings/image-key', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_save_image_key' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'service' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ],
					'api_key' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	/**
	 * Inject CSS to display the SVG logo in the sidebar menu entry.
	 */
	public function inject_menu_icon_css(): void {
		$icon_url = esc_url( SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg' );
		?>
		<style>
		#adminmenu #toplevel_page_super-fast-blog-ai .wp-menu-image {
			background-image: url('<?php echo $icon_url; ?>') !important;
			background-repeat: no-repeat !important;
			background-position: center center !important;
			background-size: 20px 20px !important;
		}
		#adminmenu #toplevel_page_super-fast-blog-ai .wp-menu-image::before {
			content: '' !important;
		}
		</style>
		<?php
	}

	/**
	 * Register the top-level admin menu and Dashboard submenu.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Super Fast Blog AI', 'super-fast-blog-ai' ),
			'manage_options',
			'super-fast-blog-ai',
			[ $this, 'render_dashboard_page' ],
			SFBA_PLUGIN_URL . 'assets/images/menu_icon.svg',
			30
		);

		// First submenu replaces the auto-generated duplicate of the top-level entry.
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Dashboard — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Dashboard', 'super-fast-blog-ai' ),
			'manage_options',
			'super-fast-blog-ai',
			[ $this, 'render_dashboard_page' ]
		);
	}

	/**
	 * Register the Settings submenu at priority 99 so it appears last in the menu.
	 */
	public function register_settings_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Settings — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Settings', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Render the overview dashboard page.
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$summary      = $this->core->cost_tracker->get_monthly_summary();
		$voice_status = $this->core->brand_voice->get_voice_summary() !== null;
		$providers    = $this->core->providers->get_all_providers();
		$connected    = count( array_filter( $providers, fn( $p ) => $p['has_key'] ) );
		$api_base     = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce        = wp_create_nonce( 'wp_rest' );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-dashboard.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$providers     = $this->core->providers->get_all_providers();
		$voice_summary = $this->core->brand_voice->get_voice_summary();
		$settings      = $this->safe_settings_for_page();
		$nonce         = wp_create_nonce( 'wp_rest' );
		$api_base      = rest_url( SFBA_Rest_Api::NAMESPACE );

		// Ollama-specific config passed to the template.
		// Use null sentinel — if null, the user has never saved a URL (just the code default).
		$ollama_base_url_raw = $this->get( 'providers.ollama.base_url', null );
		$ollama_base_url     = is_string( $ollama_base_url_raw ) ? $ollama_base_url_raw : 'http://localhost:11434';
		$ollama_has_token    = '' !== $this->get_api_key( 'ollama' );
		// "Configured" only when the user has explicitly saved something.
		$ollama_configured   = $ollama_has_token || null !== $ollama_base_url_raw;

		// Image service key status — passed separately since keys are never exposed.
		$image_keys = [
			'pixabay'  => $this->has_image_key( 'pixabay' ),
			'unsplash' => $this->has_image_key( 'unsplash' ),
			// DALL-E 3 reuses the OpenAI provider key.
			'dalle3'   => isset( $providers['openai'] ) && $providers['openai']['has_key'],
		];

		// WordPress categories for the Publishing tab dropdown.
		$wp_categories = get_categories( [
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-settings.php';
	}

	/**
	 * Return settings safe for embedding in the page (no raw API keys).
	 */
	private function safe_settings_for_page(): array {
		$all = $this->all();
		unset( $all['providers'] ); // Never expose encrypted keys.
		return $all;
	}

	// -------------------------------------------------------------------------
	// Asset loading.
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin assets on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$sfba_pages = [
			'super-fast-blog-ai',
			'sfba-settings',
			'sfba-cost-tracker',
			'sfba-content-calendar',
			'sfba-performance',
			'sfba-model-routing',
			'sfba-generate',
			'sfba-brand-voice',
			'sfba-repurpose',
			'sfba-internal-links',
		];

		$on_sfba_page = false;
		foreach ( $sfba_pages as $slug ) {
			if ( false !== strpos( $hook_suffix, $slug ) ) {
				$on_sfba_page = true;
				break;
			}
		}

		if ( ! $on_sfba_page ) {
			return;
		}

		wp_enqueue_style(
			'sfba-admin',
			SFBA_ASSETS_URL . 'css/admin.css',
			[],
			SFBA_VERSION
		);

		wp_enqueue_script(
			'sfba-admin',
			SFBA_ASSETS_URL . 'js/admin.js',
			[],
			SFBA_VERSION,
			true // Load in footer.
		);

		// Pass config to JS as window.SFBA.
		wp_localize_script( 'sfba-admin', 'SFBA', [
			'apiBase'     => rest_url( SFBA_Rest_Api::NAMESPACE ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'streamNonce' => wp_create_nonce( 'sfba_stream' ),
			'i18n'        => [
				'saving'          => __( 'Saving…', 'super-fast-blog-ai' ),
				'testing'         => __( 'Testing…', 'super-fast-blog-ai' ),
				'analyzing'       => __( 'Analyzing…', 'super-fast-blog-ai' ),
				'generating'      => __( 'Generating…', 'super-fast-blog-ai' ),
				'saved'           => __( '✓ Key saved', 'super-fast-blog-ai' ),
				'connected'       => __( 'Connection successful', 'super-fast-blog-ai' ),
				'failed'          => __( 'Request failed', 'super-fast-blog-ai' ),
				'key_removed'     => __( 'Key removed', 'super-fast-blog-ai' ),
				'test_connection' => __( 'Test Connection', 'super-fast-blog-ai' ),
				'remove'          => __( 'Remove', 'super-fast-blog-ai' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/**
	 * GET /settings
	 */
	public function rest_get_settings( WP_REST_Request $request ): WP_REST_Response {
		return SFBA_Rest_Api::success( $this->safe_settings_for_page() );
	}

	/**
	 * POST /settings — update general, content, seo, images, publishing, and schedule settings.
	 *
	 * Only keys present in the request body are updated; absent keys are left unchanged.
	 */
	public function rest_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();

		// ── AI Provider ───────────────────────────────────────────────────────
		if ( isset( $body['default_provider'] ) ) {
			$this->set( 'default_provider', sanitize_key( $body['default_provider'] ) );
		}
		if ( isset( $body['default_model'] ) ) {
			$this->set( 'default_model', sanitize_text_field( $body['default_model'] ) );
		}
		if ( isset( $body['routing_enabled'] ) ) {
			$this->set( 'routing_enabled', (bool) $body['routing_enabled'] );
		}
		if ( isset( $body['debug_mode'] ) ) {
			$this->set( 'debug_mode', (bool) $body['debug_mode'] );
		}

		// ── Brand Voice ───────────────────────────────────────────────────────
		if ( isset( $body['brand_voice']['enabled'] ) ) {
			$this->set( 'brand_voice.enabled', (bool) $body['brand_voice']['enabled'] );
		}
		if ( isset( $body['brand_voice']['post_count'] ) ) {
			$this->set( 'brand_voice.post_count', absint( $body['brand_voice']['post_count'] ) );
		}

		// ── Content settings ──────────────────────────────────────────────────
		$content_fields = [
			'language'      => 'sanitize_text_field',
			'writing_style' => 'sanitize_text_field',
			'tone'          => 'sanitize_text_field',
			'heading_tag'   => 'sanitize_text_field',
		];
		foreach ( $content_fields as $field => $sanitizer ) {
			if ( isset( $body['content'][ $field ] ) ) {
				$this->set( "content.{$field}", $sanitizer( $body['content'][ $field ] ) );
			}
		}
		if ( isset( $body['content']['word_count'] ) ) {
			$this->set( 'content.word_count', absint( $body['content']['word_count'] ) );
		}
		if ( isset( $body['content']['heading_count'] ) ) {
			$this->set( 'content.heading_count', absint( $body['content']['heading_count'] ) );
		}
		foreach ( [ 'subheadings', 'faq', 'toc', 'pros_cons' ] as $bool_field ) {
			if ( isset( $body['content'][ $bool_field ] ) ) {
				$this->set( "content.{$bool_field}", (bool) $body['content'][ $bool_field ] );
			}
		}

		// ── SEO settings ──────────────────────────────────────────────────────
		foreach ( [ 'keywords', 'meta_desc' ] as $bool_field ) {
			if ( isset( $body['seo'][ $bool_field ] ) ) {
				$this->set( "seo.{$bool_field}", (bool) $body['seo'][ $bool_field ] );
			}
		}

		// ── Image settings ────────────────────────────────────────────────────
		if ( isset( $body['images']['source'] ) ) {
			$allowed_sources = [ 'dalle3', 'pixabay', 'unsplash' ];
			$source          = sanitize_key( $body['images']['source'] );
			if ( in_array( $source, $allowed_sources, true ) ) {
				$this->set( 'images.source', $source );
			}
		}
		// Note: image API keys are saved via /settings/api-key, not here.

		// ── Publishing settings ───────────────────────────────────────────────
		if ( isset( $body['publishing']['categories'] ) && is_array( $body['publishing']['categories'] ) ) {
			$this->set( 'publishing.categories', array_map( 'absint', $body['publishing']['categories'] ) );
		}
		if ( isset( $body['publishing']['email_notify'] ) ) {
			$this->set( 'publishing.email_notify', (bool) $body['publishing']['email_notify'] );
		}

		// ── Schedule settings ─────────────────────────────────────────────────
		if ( isset( $body['schedule']['type'] ) ) {
			$allowed_types = [ 'sameday', 'later', 'recurring' ];
			$type          = sanitize_key( $body['schedule']['type'] );
			if ( in_array( $type, $allowed_types, true ) ) {
				$this->set( 'schedule.type', $type );
			}
		}
		if ( isset( $body['schedule']['same_day_type'] ) ) {
			$allowed = [ 'immediately', 'later_same_day' ];
			$val     = sanitize_key( $body['schedule']['same_day_type'] );
			if ( in_array( $val, $allowed, true ) ) {
				$this->set( 'schedule.same_day_type', $val );
			}
		}
		foreach ( [ 'hours_delay', 'minutes_delay', 'later_days', 'recur_days' ] as $int_field ) {
			if ( isset( $body['schedule'][ $int_field ] ) ) {
				$this->set( "schedule.{$int_field}", absint( $body['schedule'][ $int_field ] ) );
			}
		}
		foreach ( [ 'later_time', 'recur_time' ] as $time_field ) {
			if ( isset( $body['schedule'][ $time_field ] ) ) {
				// Validate HH:MM format.
				$time = sanitize_text_field( $body['schedule'][ $time_field ] );
				if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
					$this->set( "schedule.{$time_field}", $time );
				}
			}
		}

		// ── Search Console ────────────────────────────────────────────────────
		if ( isset( $body['search_console']['property'] ) ) {
			$this->set( 'search_console.property', esc_url_raw( $body['search_console']['property'] ) );
		}

		return SFBA_Rest_Api::success( [ 'saved' => true ] );
	}

	/**
	 * POST /settings/api-key — save (or clear) a provider API key.
	 *
	 * Send api_key = "" to remove the key.
	 */
	public function rest_save_api_key( WP_REST_Request $request ): WP_REST_Response {
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		$api_key  = trim( (string) $request->get_param( 'api_key' ) );

		$known = $this->core->providers->get_registered_slugs();
		if ( ! in_array( $provider, $known, true ) ) {
			return SFBA_Rest_Api::error(
				'sfba_invalid_provider',
				__( 'Unknown provider.', 'super-fast-blog-ai' ),
				400
			);
		}

		if ( '' === $api_key ) {
			// Remove the key.
			$this->set( "providers.{$provider}.api_key", '' );
			return SFBA_Rest_Api::success( [ 'provider' => $provider, 'connected' => false ] );
		}

		$this->set_api_key( $provider, $api_key );
		return SFBA_Rest_Api::success( [ 'provider' => $provider, 'connected' => true ] );
	}

	/**
	 * POST /settings/test-key — validate the stored API key for a provider.
	 */
	public function rest_test_api_key( WP_REST_Request $request ): WP_REST_Response {
		$provider_slug = sanitize_key( (string) $request->get_param( 'provider' ) );
		$provider_obj  = $this->core->providers->get_provider( $provider_slug );

		if ( null === $provider_obj ) {
			return SFBA_Rest_Api::error(
				'sfba_invalid_provider',
				__( 'Provider not found or no API key set.', 'super-fast-blog-ai' ),
				400
			);
		}

		$result = $provider_obj->validate_key();
		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		return SFBA_Rest_Api::success( [
			'provider'  => $provider_slug,
			'connected' => true,
		] );
	}

	/**
	 * GET /settings/providers — list all providers with connection status.
	 */
	public function rest_get_providers( WP_REST_Request $request ): WP_REST_Response {
		return SFBA_Rest_Api::success( $this->core->providers->get_all_providers() );
	}

	/**
	 * POST /settings/ollama — save Ollama base URL and optional bearer token.
	 */
	public function rest_save_ollama( WP_REST_Request $request ): WP_REST_Response {
		$base_url     = esc_url_raw( trim( (string) $request->get_param( 'base_url' ) ) );
		$bearer_token = trim( (string) $request->get_param( 'bearer_token' ) );

		if ( '' !== $base_url ) {
			$this->set( 'providers.ollama.base_url', $base_url );
		}

		// Bearer token is stored as the provider api_key (encrypted).
		if ( '' !== $bearer_token ) {
			$this->set_api_key( 'ollama', $bearer_token );
		} elseif ( '' === $bearer_token && '' !== $base_url ) {
			// If URL was submitted but token was left blank, clear any stored token.
			$this->set( 'providers.ollama.api_key', '' );
		}

		return SFBA_Rest_Api::success( [
			'base_url'  => $this->get( 'providers.ollama.base_url', 'http://localhost:11434' ),
			'has_token' => '' !== $this->get_api_key( 'ollama' ),
		] );
	}

	/**
	 * POST /settings/image-key — save (or clear) a Pixabay / Unsplash API key.
	 *
	 * Keys are stored encrypted at images.{service}_key.
	 * Send api_key = "" to remove the key.
	 */
	public function rest_save_image_key( WP_REST_Request $request ): WP_REST_Response {
		$service = sanitize_key( (string) $request->get_param( 'service' ) );
		$api_key = trim( (string) $request->get_param( 'api_key' ) );

		$allowed = [ 'pixabay', 'unsplash' ];
		if ( ! in_array( $service, $allowed, true ) ) {
			return SFBA_Rest_Api::error(
				'sfba_invalid_service',
				__( 'Unknown image service.', 'super-fast-blog-ai' ),
				400
			);
		}

		if ( '' === $api_key ) {
			$this->set( "images.{$service}_key", '' );
			return SFBA_Rest_Api::success( [ 'service' => $service, 'connected' => false ] );
		}

		// Encrypt the same way AI provider keys are stored.
		$encrypted = $this->encrypt_api_key( $api_key );
		$this->set( "images.{$service}_key", $encrypted );
		return SFBA_Rest_Api::success( [ 'service' => $service, 'connected' => true ] );
	}

	/**
	 * Return whether a Pixabay / Unsplash key is saved.
	 */
	public function has_image_key( string $service ): bool {
		$val = $this->get( "images.{$service}_key", '' );
		return '' !== $this->decrypt_api_key( (string) $val );
	}

	// -------------------------------------------------------------------------
	// Options API.
	// -------------------------------------------------------------------------

	/**
	 * Return the full options array (cached in memory).
	 */
	public function all(): array {
		if ( null === $this->options ) {
			$this->options = (array) get_option( SFBA_OPTION_KEY, [] );
		}
		return $this->options;
	}

	/**
	 * Get a single option value using dot-notation key.
	 *
	 * @param string $key     Dot-separated path (e.g. 'brand_voice.enabled').
	 * @param mixed  $default Fallback when key is absent.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$options = $this->all();
		foreach ( explode( '.', $key ) as $part ) {
			if ( ! is_array( $options ) || ! array_key_exists( $part, $options ) ) {
				return $default;
			}
			$options = $options[ $part ];
		}
		return $options;
	}

	/**
	 * Set a single option value and persist to the database.
	 *
	 * @param string $key   Dot-separated path.
	 * @param mixed  $value New value.
	 */
	public function set( string $key, mixed $value ): bool {
		$options = $this->all();
		$parts   = explode( '.', $key );
		$ref     = &$options;

		foreach ( $parts as $i => $part ) {
			if ( $i === count( $parts ) - 1 ) {
				$ref[ $part ] = $value;
			} else {
				if ( ! isset( $ref[ $part ] ) || ! is_array( $ref[ $part ] ) ) {
					$ref[ $part ] = [];
				}
				$ref = &$ref[ $part ];
			}
		}

		$this->options = $options;
		return update_option( SFBA_OPTION_KEY, $options );
	}

	// -------------------------------------------------------------------------
	// API key helpers.
	// -------------------------------------------------------------------------

	/**
	 * Store an encrypted API key for a provider.
	 *
	 * We intentionally do NOT use sanitize_text_field() here because API keys
	 * may contain characters that WP would strip. trim() is all that is required
	 * before encryption.
	 *
	 * @param string $provider_slug Provider slug (e.g. 'openai').
	 * @param string $api_key       Plain-text API key.
	 */
	public function set_api_key( string $provider_slug, string $api_key ): bool {
		$encrypted = $this->encrypt_api_key( trim( $api_key ) );
		return $this->set( "providers.{$provider_slug}.api_key", $encrypted );
	}

	/**
	 * Retrieve and decrypt an API key for a provider.
	 *
	 * Returns an empty string if no key is stored or decryption fails.
	 *
	 * @param string $provider_slug Provider slug.
	 */
	public function get_api_key( string $provider_slug ): string {
		$encrypted = $this->get( "providers.{$provider_slug}.api_key", '' );
		if ( '' === $encrypted ) {
			return '';
		}
		return $this->decrypt_api_key( (string) $encrypted );
	}

	// -------------------------------------------------------------------------
	// Encryption helpers.
	// -------------------------------------------------------------------------

	/**
	 * Encrypt an API key using AES-256-CBC + WordPress auth salts as key material.
	 *
	 * @param string $plain_text
	 * @return string Base64-encoded ciphertext (IV prepended).
	 */
	private function encrypt_api_key( string $plain_text ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return base64_encode( $plain_text );
		}

		$key    = $this->derive_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain_text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return base64_encode( $plain_text );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a previously encrypted API key.
	 *
	 * @param string $cipher_text Base64-encoded ciphertext.
	 * @return string Plain-text key, or '' on failure.
	 */
	private function decrypt_api_key( string $cipher_text ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decoded = base64_decode( $cipher_text );
			return ( false !== $decoded ) ? $decoded : '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( $cipher_text );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}

		$key    = $this->derive_key();
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$result = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return ( false !== $result ) ? $result : '';
	}

	/**
	 * Derive a 32-byte encryption key from WordPress auth salts.
	 *
	 * Uses the same salt string as the Legacy Bridge so that keys migrated from
	 * v1 can be decrypted by this class without a separate re-encryption step.
	 */
	private function derive_key(): string {
		$salt  = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$salt .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		return substr( hash( 'sha256', $salt . 'oaw_api_key', true ), 0, 32 );
	}
}
