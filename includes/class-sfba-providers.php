<?php
/**
 * AI provider registry and smart model router.
 *
 * Central registry for all AI provider classes. Every provider must implement
 * SFBA_Provider_Interface. Third-party developers can inject custom providers
 * via the 'sfba_register_providers' filter.
 *
 * Responsibilities:
 *   - Register 7 built-in providers on init.
 *   - Allow 3rd-party providers via filter hook.
 *   - Instantiate provider objects with decrypted API credentials.
 *   - Route a content_type to the correct provider/model based on
 *     wp_sfba_routing_rules DB table or the user's default provider setting.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Providers {

	/**
	 * @var SFBA_Core
	 */
	private SFBA_Core $core;

	/**
	 * Registered provider slugs → class names.
	 *
	 * @var array<string, string>
	 */
	private array $registry = [];

	/**
	 * Instantiated provider objects (keyed by slug, lazy-loaded).
	 *
	 * @var array<string, SFBA_Provider_Interface>
	 */
	private array $instances = [];

	/**
	 * Built-in providers: slug → class name.
	 *
	 * @var array<string, string>
	 */
	private const BUILT_IN = [
		'openai'     => 'SFBA_Provider_OpenAI',
		'anthropic'  => 'SFBA_Provider_Anthropic',
		'google'     => 'SFBA_Provider_Google',
		'openrouter' => 'SFBA_Provider_OpenRouter',
		'deepseek'   => 'SFBA_Provider_DeepSeek',
		'mistral'    => 'SFBA_Provider_Mistral',
		'ollama'     => 'SFBA_Provider_Ollama',
	];

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
		$loader->add_action( 'init', $this, 'register_built_in_providers' );
		$loader->add_filter( 'cron_schedules', $this, 'add_cron_schedules' );
	}

	/**
	 * Add a monthly cron recurrence (30 days) for the log pruning job.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['sfba_monthly'] ) ) {
			$schedules['sfba_monthly'] = [
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly (Super Fast Blog AI)', 'super-fast-blog-ai' ),
			];
		}
		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Provider registration.
	// -------------------------------------------------------------------------

	/**
	 * Register all built-in providers and allow 3rd-party additions via filter.
	 *
	 * Hooked on 'init' so 3rd-party plugins loaded on 'plugins_loaded' have
	 * already registered before we apply the filter.
	 */
	public function register_built_in_providers(): void {
		$providers = self::BUILT_IN;

		/**
		 * Filter: sfba_register_providers
		 *
		 * Allows 3rd-party plugins to register custom AI providers.
		 *
		 * @param array<string, string> $providers Slug → fully-qualified class name.
		 *
		 * Example:
		 *   add_filter( 'sfba_register_providers', function( $providers ) {
		 *       $providers['myprovider'] = 'MyPlugin_SFBA_Provider';
		 *       return $providers;
		 *   } );
		 */
		$providers = apply_filters( 'sfba_register_providers', $providers );

		foreach ( $providers as $slug => $class ) {
			$this->register_provider( sanitize_key( $slug ), $class );
		}
	}

	/**
	 * Register a single provider.
	 *
	 * @param string $slug  Unique provider identifier (e.g. 'openai').
	 * @param string $class Fully-qualified class name implementing SFBA_Provider_Interface.
	 * @return bool True on success, false if class is invalid.
	 */
	public function register_provider( string $slug, string $class ): bool {
		if ( ! class_exists( $class ) ) {
			return false;
		}

		$interfaces = class_implements( $class );
		if ( empty( $interfaces ) || ! in_array( 'SFBA_Provider_Interface', $interfaces, true ) ) {
			return false;
		}

		$this->registry[ $slug ] = $class;
		return true;
	}

	// -------------------------------------------------------------------------
	// Provider access.
	// -------------------------------------------------------------------------

	/**
	 * Return an initialized provider instance, injected with its API key.
	 *
	 * Instances are cached; repeated calls for the same slug return the same object.
	 *
	 * @param string $slug Provider slug.
	 * @return SFBA_Provider_Interface|null Null if slug is not registered.
	 */
	public function get_provider( string $slug ): ?SFBA_Provider_Interface {
		if ( isset( $this->instances[ $slug ] ) ) {
			return $this->instances[ $slug ];
		}

		if ( ! isset( $this->registry[ $slug ] ) ) {
			return null;
		}

		$api_key  = $this->core->settings->get_api_key( $slug );
		$class    = $this->registry[ $slug ];
		$instance = new $class( $api_key, $this->core->settings );

		$this->instances[ $slug ] = $instance;
		return $instance;
	}

	/**
	 * Return all registered provider slugs.
	 *
	 * @return string[]
	 */
	public function get_registered_slugs(): array {
		return array_keys( $this->registry );
	}

	/**
	 * Return metadata for all registered providers (for admin UI dropdowns).
	 *
	 * @return array<string, array{slug: string, name: string, has_key: bool}>
	 */
	public function get_all_providers(): array {
		$result = [];
		foreach ( $this->registry as $slug => $class ) {
			$api_key       = $this->core->settings->get_api_key( $slug );
			$instance      = new $class( $api_key, $this->core->settings );
			$result[ $slug ] = [
				'slug'          => $slug,
				'name'          => $class::display_name(),
				'has_key'       => '' !== $api_key,
				'default_model' => $instance->get_default_model(),
			];
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Smart routing.
	// -------------------------------------------------------------------------

	/**
	 * Resolve which provider + model to use for a given content type.
	 *
	 * Lookup order:
	 *   1. Active routing rule in wp_sfba_routing_rules for this content_type.
	 *   2. User's default_provider + default_model settings.
	 *   3. First provider that has an API key configured.
	 *
	 * Results cached in WP object cache for 5 minutes.
	 *
	 * @param string $content_type One of: blog, product, social, meta, email, title_generation, etc.
	 * @return array{provider: string, model: string, max_tokens: int, temperature: float}|null
	 *         Null when no configured provider is available.
	 */
	public function resolve_route( string $content_type ): ?array {
		$cache_key = 'sfba_route_' . sanitize_key( $content_type );
		$cached    = wp_cache_get( $cache_key, 'sfba_routes' );

		if ( false !== $cached ) {
			return 'none' === $cached ? null : $cached;
		}

		$result = $this->resolve_route_uncached( $content_type );

		wp_cache_set( $cache_key, $result ?? 'none', 'sfba_routes', 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Invalidate the object-cache entry for a specific content type's route.
	 * Call this whenever a routing rule or the default provider is updated.
	 *
	 * @param string $content_type Pass '' to invalidate all known types.
	 */
	public function invalidate_route_cache( string $content_type = '' ): void {
		if ( '' !== $content_type ) {
			wp_cache_delete( 'sfba_route_' . sanitize_key( $content_type ), 'sfba_routes' );
			return;
		}

		$all_types = [
			'blog', 'product', 'social', 'meta', 'email',
			'rewrite', 'outline', 'title_generation', 'blog_generation',
			'seo_analysis', 'repurposing', 'internal_linking', 'brand_voice', 'calendar',
		];

		foreach ( $all_types as $type ) {
			wp_cache_delete( 'sfba_route_' . $type, 'sfba_routes' );
		}
	}

	/**
	 * Un-cached route resolution. Called exclusively by resolve_route().
	 *
	 * @param string $content_type
	 * @return array|null
	 */
	private function resolve_route_uncached( string $content_type ): ?array {
		global $wpdb;

		// 1. Check routing rules table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT provider, model, max_tokens, temperature
				 FROM {$wpdb->prefix}sfba_routing_rules
				 WHERE content_type = %s AND is_active = 1
				 ORDER BY priority ASC
				 LIMIT 1",
				sanitize_key( $content_type )
			),
			ARRAY_A
		);

		if ( $rule ) {
			return [
				'provider'    => sanitize_key( $rule['provider'] ),
				'model'       => sanitize_text_field( $rule['model'] ),
				'max_tokens'  => (int) $rule['max_tokens'],
				'temperature' => (float) $rule['temperature'],
			];
		}

		// 2. User's default provider + model.
		$default_provider = $this->core->settings->get( 'default_provider', '' );
		$default_model    = $this->core->settings->get( 'default_model', '' );

		if ( $default_provider && '' !== $this->core->settings->get_api_key( $default_provider ) ) {
			return [
				'provider'    => $default_provider,
				'model'       => $default_model,
				'max_tokens'  => 4096,
				'temperature' => 0.7,
			];
		}

		// 3. First provider with a configured API key.
		foreach ( $this->get_registered_slugs() as $slug ) {
			if ( '' !== $this->core->settings->get_api_key( $slug ) ) {
				return [
					'provider'    => $slug,
					'model'       => '',
					'max_tokens'  => 4096,
					'temperature' => 0.7,
				];
			}
		}

		return null;
	}
}
