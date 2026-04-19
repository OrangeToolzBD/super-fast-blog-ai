<?php
/**
 * Core orchestrator — singleton that boots all modules.
 *
 * Single entry point for the plugin. Instantiates every module and wires
 * their hooks through SFBA_Loader. Consumers retrieve module instances via
 * SFBA_Core::get_instance()->{module}.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SFBA_Core {

	/**
	 * Singleton instance.
	 *
	 * @var SFBA_Core|null
	 */
	private static ?SFBA_Core $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var SFBA_Loader
	 */
	public SFBA_Loader $loader;

	// -------------------------------------------------------------------------
	// Infrastructure modules.
	// -------------------------------------------------------------------------

	/** @var SFBA_Settings */
	public SFBA_Settings $settings;

	/** @var SFBA_Providers */
	public SFBA_Providers $providers;

	/** @var SFBA_Rest_Api */
	public SFBA_Rest_Api $rest_api;

	// -------------------------------------------------------------------------
	// Feature modules.
	// -------------------------------------------------------------------------

	/** @var SFBA_Title_Generator */
	public SFBA_Title_Generator $title_generator;

	/** @var SFBA_Content_Generator */
	public SFBA_Content_Generator $content_generator;

	/** @var SFBA_Brand_Voice */
	public SFBA_Brand_Voice $brand_voice;

	/** @var SFBA_Seo_Scorer */
	public SFBA_Seo_Scorer $seo_scorer;

	/** @var SFBA_Internal_Linker */
	public SFBA_Internal_Linker $internal_linker;

	/** @var SFBA_Repurposer */
	public SFBA_Repurposer $repurposer;

	/** @var SFBA_Content_Calendar */
	public SFBA_Content_Calendar $content_calendar;

	/** @var SFBA_Performance */
	public SFBA_Performance $performance;

	/** @var SFBA_Cost_Tracker */
	public SFBA_Cost_Tracker $cost_tracker;

	/** @var SFBA_Model_Router */
	public SFBA_Model_Router $model_router;

	/** @var SFBA_Editor_Integration */
	public SFBA_Editor_Integration $editor_integration;

	/** @var SFBA_Legacy_Bridge */
	public SFBA_Legacy_Bridge $legacy_bridge;

	// -------------------------------------------------------------------------
	// Singleton enforcement.
	// -------------------------------------------------------------------------

	/**
	 * Returns the single instance, creating it on first call.
	 *
	 * @return SFBA_Core
	 */
	public static function get_instance(): SFBA_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/** Prevent direct construction. */
	private function __construct() {}

	/** Prevent cloning. */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'SFBA_Core cannot be cloned.', 'super-fast-blog-ai' ), esc_html( SFBA_VERSION ) );
	}

	/** Prevent unserialization. */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'SFBA_Core cannot be unserialized.', 'super-fast-blog-ai' ), esc_html( SFBA_VERSION ) );
	}

	// -------------------------------------------------------------------------
	// Initialization.
	// -------------------------------------------------------------------------

	/**
	 * Instantiate all modules and store references.
	 *
	 * Modules are constructed in dependency order:
	 *   1. Infrastructure  (loader, legacy bridge, settings, providers, rest api)
	 *   2. Hybrid modules  (title generator, content generator — core SFBA logic)
	 *   3. Feature modules (adopted from OAW)
	 */
	private function init(): void {
		$this->loader = new SFBA_Loader();

		// ── Infrastructure ─────────────────────────────────────────────────────
		// Legacy bridge first — migrates old options before anything reads them.
		$this->legacy_bridge = new SFBA_Legacy_Bridge( $this );
		$this->settings      = new SFBA_Settings( $this );
		$this->providers     = new SFBA_Providers( $this );
		$this->rest_api      = new SFBA_Rest_Api( $this );

		// ── Hybrid modules (SFBA core logic + OAW provider/routing) ───────────
		$this->title_generator   = new SFBA_Title_Generator( $this );
		$this->content_generator = new SFBA_Content_Generator( $this );

		// ── OAW feature modules (adopted as-is, prefix renamed) ───────────────
		$this->brand_voice      = new SFBA_Brand_Voice( $this );
		$this->content_calendar = new SFBA_Content_Calendar( $this );
		$this->repurposer       = new SFBA_Repurposer( $this );
		$this->internal_linker  = new SFBA_Internal_Linker( $this );
		$this->performance      = new SFBA_Performance( $this );
		$this->cost_tracker     = new SFBA_Cost_Tracker( $this );
		$this->model_router     = new SFBA_Model_Router( $this );

		// ── No admin menu ──────────────────────────────────────────────────────
		$this->editor_integration = new SFBA_Editor_Integration( $this );
		$this->seo_scorer         = new SFBA_Seo_Scorer( $this );

		// Let each module register its hooks into the loader.
		$this->register_hooks();
	}

	/**
	 * Tell each module to register its hooks into the loader.
	 *
	 * Order here controls the WP admin submenu order.
	 */
	private function register_hooks(): void {
		// ── Infrastructure ──────────────────────────────────────────────────────
		$this->legacy_bridge->init( $this->loader );    // must run first
		$this->settings->init( $this->loader );         // dashboard (p10) + settings (p99)
		$this->providers->init( $this->loader );
		$this->rest_api->init( $this->loader );

		// ── Submenu order ───────────────────────────────────────────────────────
		// 1. Title Generator   — original SFBA primary feature
		$this->title_generator->init( $this->loader );
		// 2. Generate Content  — unified content engine
		$this->content_generator->init( $this->loader );
		// 3. Brand Voice       — writing style setup
		$this->brand_voice->init( $this->loader );
		// 4. Content Calendar  — plan what to write
		$this->content_calendar->init( $this->loader );
		// 5. Repurpose Content — extend existing content
		$this->repurposer->init( $this->loader );
		// 6. Internal Links    — optimize content
		$this->internal_linker->init( $this->loader );
		// 7. Performance       — track results
		$this->performance->init( $this->loader );
		// 8. Cost Tracker      — monitor spend
		$this->cost_tracker->init( $this->loader );
		// 9. Model Routing     — advanced configuration
		$this->model_router->init( $this->loader );
		// 10. Settings         — always last (registered at priority 99)

		// ── No admin menu ────────────────────────────────────────────────────────
		$this->editor_integration->init( $this->loader );
		$this->seo_scorer->init( $this->loader );
	}

	/**
	 * Execute the loader to register all collected hooks with WordPress.
	 *
	 * Called once from the plugins_loaded callback in the main plugin file.
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Returns the plugin version string.
	 *
	 * @return string
	 */
	public function version(): string {
		return SFBA_VERSION;
	}
}
