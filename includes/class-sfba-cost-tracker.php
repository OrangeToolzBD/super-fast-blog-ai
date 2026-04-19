<?php
/**
 * Centralized API Cost Tracker.
 *
 * Every AI generation across the plugin (title, article, brand voice, calendar,
 * repurposing, internal linking, SEO scoring) writes a single row to
 * wp_sfba_generations through this class.  No module should write to that
 * table directly.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ Dashboard headline goal                                                 │
 * │ "This month: 47 generations · $12.34 total · $0.26/generation avg"     │
 * │                                                                         │
 * │ Public API                                                              │
 * │   log()                     → int|false (the main write path)          │
 * │   get_monthly_summary()     → array     (dashboard card)               │
 * │   suggest_cost_optimizations() → array  (downgrade suggestions)        │
 * │   check_budget_alert()      → array     (over/warning/notice/ok)       │
 * │   get_summary()             → array     (arbitrary date range)         │
 * │   get_by_provider()         → array     (cost by provider)             │
 * │   get_by_model()            → array     (cost by model)                │
 * │   get_by_feature()          → array     (cost by feature tag)          │
 * │   prune_old_logs()          → int       (delete aged rows)             │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * REST surface (registered from this class):
 *   GET  /costs/summary        → monthly summary card
 *   GET  /costs/breakdown      → breakdown by provider + model + feature
 *   GET  /costs/optimizations  → model downgrade suggestions
 *   GET  /costs/budget         → budget alert status
 *
 * DB table: {prefix}sfba_generations
 *   id, post_id, user_id, provider, model, feature, prompt_tokens,
 *   completion_tokens, cost_usd, generation_time_ms, created_at
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Cost_Tracker {

	// -------------------------------------------------------------------------
	// Constants.
	// -------------------------------------------------------------------------

	/** Default log retention in days (1 year). */
	private const DEFAULT_RETENTION_DAYS = 365;

	/**
	 * Pricing table — cost per 1 M tokens (USD).
	 *
	 * Format: PRICING[provider][model] = ['input' => float, 'output' => float]
	 * All figures are per-million-token rates as of Q1 2025.
	 *
	 * Sources:
	 *   OpenAI    — platform.openai.com/docs/pricing
	 *   Anthropic — anthropic.com/pricing
	 *   Google    — ai.google.dev/pricing
	 *   OpenRouter— openrouter.ai/models
	 *   DeepSeek  — platform.deepseek.com/api-docs/pricing
	 *   Mistral   — mistral.ai/technology/pricing
	 *   Ollama    — always $0 (local inference)
	 */
	public const PRICING = [
		'openai' => [
			// GPT-4.1 family
			'gpt-4.1'           => [ 'input' => 2.00,  'output' => 8.00  ],
			'gpt-4.1-mini'      => [ 'input' => 0.40,  'output' => 1.60  ],
			'gpt-4.1-nano'      => [ 'input' => 0.10,  'output' => 0.40  ],
			// GPT-4o family
			'gpt-4o'            => [ 'input' => 2.50,  'output' => 10.00 ],
			'gpt-4o-mini'       => [ 'input' => 0.15,  'output' => 0.60  ],
			// O-series reasoning
			'o4-mini'           => [ 'input' => 1.10,  'output' => 4.40  ],
			'o3'                => [ 'input' => 10.00, 'output' => 40.00 ],
			'o3-mini'           => [ 'input' => 1.10,  'output' => 4.40  ],
			'o1'                => [ 'input' => 15.00, 'output' => 60.00 ],
			'o1-mini'           => [ 'input' => 1.10,  'output' => 4.40  ],
			// Legacy
			'gpt-3.5-turbo'     => [ 'input' => 0.50,  'output' => 1.50  ],
		],
		'anthropic' => [
			'claude-opus-4-5'        => [ 'input' => 15.00, 'output' => 75.00 ],
			'claude-sonnet-4-5'      => [ 'input' => 3.00,  'output' => 15.00 ],
			'claude-sonnet-3-7'      => [ 'input' => 3.00,  'output' => 15.00 ],
			'claude-sonnet-3-5'      => [ 'input' => 3.00,  'output' => 15.00 ],
			'claude-haiku-3-5'       => [ 'input' => 0.80,  'output' => 4.00  ],
			'claude-3-opus-20240229' => [ 'input' => 15.00, 'output' => 75.00 ],
			'claude-3-sonnet-20240229' => [ 'input' => 3.00, 'output' => 15.00 ],
			'claude-3-haiku-20240307'  => [ 'input' => 0.25, 'output' => 1.25  ],
		],
		'google' => [
			'gemini-2.5-pro'         => [ 'input' => 1.25,  'output' => 10.00 ],
			'gemini-2.5-flash'       => [ 'input' => 0.15,  'output' => 0.60  ],
			'gemini-2.0-flash'       => [ 'input' => 0.10,  'output' => 0.40  ],
			'gemini-2.0-flash-lite'  => [ 'input' => 0.075, 'output' => 0.30  ],
			'gemini-1.5-pro'         => [ 'input' => 1.25,  'output' => 5.00  ],
			'gemini-1.5-flash'       => [ 'input' => 0.075, 'output' => 0.30  ],
			'gemini-1.5-flash-8b'    => [ 'input' => 0.0375,'output' => 0.15  ],
		],
		'openrouter' => [
			'openai/gpt-4o'                      => [ 'input' => 2.60,  'output' => 10.40 ],
			'openai/gpt-4o-mini'                 => [ 'input' => 0.16,  'output' => 0.64  ],
			'anthropic/claude-sonnet-4-5'        => [ 'input' => 3.15,  'output' => 15.75 ],
			'anthropic/claude-haiku-3-5'         => [ 'input' => 0.85,  'output' => 4.25  ],
			'google/gemini-2.5-flash'            => [ 'input' => 0.16,  'output' => 0.64  ],
			'meta-llama/llama-3.3-70b-instruct'  => [ 'input' => 0.12,  'output' => 0.30  ],
			'mistralai/mixtral-8x7b-instruct'    => [ 'input' => 0.24,  'output' => 0.24  ],
		],
		'deepseek' => [
			'deepseek-chat'     => [ 'input' => 0.27, 'output' => 1.10 ],
			'deepseek-reasoner' => [ 'input' => 0.55, 'output' => 2.19 ],
			'deepseek-coder'    => [ 'input' => 0.14, 'output' => 0.28 ],
		],
		'mistral' => [
			'mistral-large-latest'  => [ 'input' => 2.00, 'output' => 6.00 ],
			'mistral-small-latest'  => [ 'input' => 0.20, 'output' => 0.60 ],
			'mistral-nemo'          => [ 'input' => 0.15, 'output' => 0.15 ],
			'open-mistral-7b'       => [ 'input' => 0.25, 'output' => 0.25 ],
			'open-mixtral-8x7b'     => [ 'input' => 0.70, 'output' => 0.70 ],
			'open-mixtral-8x22b'    => [ 'input' => 2.00, 'output' => 6.00 ],
			'codestral-latest'      => [ 'input' => 0.20, 'output' => 0.60 ],
		],
		'ollama' => [
			// Local inference — always free.
			'llama3.3'    => [ 'input' => 0.00, 'output' => 0.00 ],
			'llama3.1'    => [ 'input' => 0.00, 'output' => 0.00 ],
			'mistral'     => [ 'input' => 0.00, 'output' => 0.00 ],
			'gemma3'      => [ 'input' => 0.00, 'output' => 0.00 ],
			'phi4'        => [ 'input' => 0.00, 'output' => 0.00 ],
			'qwen2.5'     => [ 'input' => 0.00, 'output' => 0.00 ],
			'deepseek-r1' => [ 'input' => 0.00, 'output' => 0.00 ],
		],
	];

	/**
	 * Optimization map — expensive model → cheaper alternative for each feature tag.
	 *
	 * Format: [ from, from_provider, to, to_provider, features[], quality_note ]
	 */
	private const OPTIMIZATION_MAP = [
		[
			'from'          => 'gpt-4o',
			'to'            => 'gpt-4o-mini',
			'from_provider' => 'openai',
			'to_provider'   => 'openai',
			'types'         => [ 'social', 'seo_analysis', 'repurposing' ],
			'quality_note'  => 'GPT-4o-mini handles short-form content with near-identical quality at ~94% lower cost.',
		],
		[
			'from'          => 'claude-opus-4-5',
			'to'            => 'claude-haiku-3-5',
			'from_provider' => 'anthropic',
			'to_provider'   => 'anthropic',
			'types'         => [ 'social', 'seo_analysis', 'repurposing' ],
			'quality_note'  => 'Claude Haiku is ideal for short outputs. Save Opus for long-form articles.',
		],
		[
			'from'          => 'claude-sonnet-4-5',
			'to'            => 'claude-haiku-3-5',
			'from_provider' => 'anthropic',
			'to_provider'   => 'anthropic',
			'types'         => [ 'social', 'seo_analysis' ],
			'quality_note'  => 'Claude Haiku is sufficient for short-form outputs at ~73% lower cost.',
		],
		[
			'from'          => 'gemini-2.5-pro',
			'to'            => 'gemini-2.5-flash',
			'from_provider' => 'google',
			'to_provider'   => 'google',
			'types'         => [ 'social', 'seo_analysis', 'repurposing', 'blog_generation' ],
			'quality_note'  => 'Gemini Flash is 88% cheaper than Pro with comparable quality for most content types.',
		],
		[
			'from'          => 'gpt-4.1',
			'to'            => 'gpt-4.1-mini',
			'from_provider' => 'openai',
			'to_provider'   => 'openai',
			'types'         => [ 'social', 'seo_analysis', 'repurposing' ],
			'quality_note'  => 'GPT-4.1 Mini costs 80% less than GPT-4.1 for short-form content.',
		],
		[
			'from'          => 'mistral-large-latest',
			'to'            => 'mistral-small-latest',
			'from_provider' => 'mistral',
			'to_provider'   => 'mistral',
			'types'         => [ 'social', 'seo_analysis' ],
			'quality_note'  => 'Mistral Small handles short content at 90% lower cost than Mistral Large.',
		],
	];

	// -------------------------------------------------------------------------
	// Properties.
	// -------------------------------------------------------------------------

	/** @var SFBA_Core */
	private SFBA_Core $core;

	// -------------------------------------------------------------------------
	// Constructor.
	// -------------------------------------------------------------------------

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
		// Monthly cron: prune old logs.
		$loader->add_action( 'sfba_prune_generation_log', $this, 'prune_old_logs' );

		// Admin submenu page.
		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );

		// REST routes.
		$this->core->rest_api->register_route( '/costs/summary', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_summary' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'from' => [ 'type' => 'string', 'default' => '' ],
					'to'   => [ 'type' => 'string', 'default' => '' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/costs/breakdown', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_breakdown' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'from' => [ 'type' => 'string', 'default' => '' ],
					'to'   => [ 'type' => 'string', 'default' => '' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/costs/optimizations', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_optimizations' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
		] );

		$this->core->rest_api->register_route( '/costs/budget', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_budget' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
		] );

		$this->core->rest_api->register_route( '/costs/log', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_log' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'per_page' => [ 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 200 ],
					'from'     => [ 'type' => 'string', 'default' => '' ],
					'to'       => [ 'type' => 'string', 'default' => '' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/costs/prune', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_prune' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	/**
	 * Register the Cost Tracker submenu page under Super Fast Blog AI.
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Cost Tracker — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Cost Tracker', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-cost-tracker',
			[ $this, 'render_cost_page' ]
		);
	}

	/**
	 * Render the Cost Tracker admin page.
	 */
	public function render_cost_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$summary = $this->get_monthly_summary();

		$by_provider = [];
		foreach ( $this->get_by_provider() as $label => $data ) {
			$by_provider[] = [
				'provider' => $label,
				'count'    => $data['generations'],
				'cost'     => $data['cost_usd'],
			];
		}
		$summary['by_provider'] = $by_provider;

		$by_feature = [];
		foreach ( $this->get_by_feature() as $label => $data ) {
			$by_feature[] = [
				'feature' => $label,
				'count'   => $data['generations'],
				'cost'    => $data['cost_usd'],
			];
		}
		$summary['by_feature'] = $by_feature;

		$budget   = $this->check_budget_alert( $summary['total_cost_usd'] );
		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-costs.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** GET /costs/summary */
	public function rest_summary( WP_REST_Request $request ): WP_REST_Response {
		$from   = (string) $request->get_param( 'from' );
		$to     = (string) $request->get_param( 'to' );
		return SFBA_Rest_Api::success( $this->get_monthly_summary( $from, $to ) );
	}

	/** GET /costs/breakdown */
	public function rest_breakdown( WP_REST_Request $request ): WP_REST_Response {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );

		return SFBA_Rest_Api::success( [
			'by_provider'  => $this->get_by_provider( $from, $to ),
			'by_model'     => $this->get_by_model( $from, $to ),
			'by_feature'   => $this->get_by_feature( $from, $to ),
			'date_range'   => $this->resolve_date_range( $from, $to ),
		] );
	}

	/** GET /costs/optimizations */
	public function rest_optimizations( WP_REST_Request $request ): WP_REST_Response {
		return SFBA_Rest_Api::success( $this->suggest_cost_optimizations() );
	}

	/** GET /costs/budget */
	public function rest_budget( WP_REST_Request $request ): WP_REST_Response {
		return SFBA_Rest_Api::success( $this->check_budget_alert() );
	}

	/** GET /costs/log */
	public function rest_log( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$per_page = absint( $request->get_param( 'per_page' ) ) ?: 25;
		$from     = sanitize_text_field( (string) $request->get_param( 'from' ) );
		$to       = sanitize_text_field( (string) $request->get_param( 'to' ) );

		$where  = '1=1';
		$params = [];

		if ( $from ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( $params ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, provider, model, feature, prompt_tokens, completion_tokens, cost_usd, created_at
					 FROM {$wpdb->prefix}sfba_generations
					 WHERE {$where}
					 ORDER BY created_at DESC
					 LIMIT %d",
					array_merge( $params, [ $per_page ] )
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, provider, model, feature, prompt_tokens, completion_tokens, cost_usd, created_at
					 FROM {$wpdb->prefix}sfba_generations
					 ORDER BY created_at DESC
					 LIMIT %d",
					$per_page
				)
			);
		}
		// phpcs:enable

		return SFBA_Rest_Api::success( [ 'rows' => $rows ?: [] ] );
	}

	/** POST /costs/prune */
	public function rest_prune( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$pruned = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}sfba_generations WHERE created_at < %s",
				$cutoff
			)
		);
		// phpcs:enable

		return SFBA_Rest_Api::success( [ 'pruned' => (int) $pruned ] );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Log a completed AI generation.
	 *
	 * This is the single write path for ALL generation events across the plugin.
	 * Every module must call this after a successful AI call — never write to
	 * wp_sfba_generations directly.
	 *
	 * If cost_usd is 0.0 or not provided, cost is auto-calculated from the
	 * built-in PRICING table.
	 *
	 * @param array{
	 *     feature: string,
	 *     provider: string,
	 *     model: string,
	 *     prompt_tokens: int,
	 *     completion_tokens: int,
	 *     post_id?: int,
	 *     cost_usd?: float,
	 *     generation_time_ms?: int
	 * } $data Generation metadata. 'feature' is MANDATORY.
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function log( array $data ): int|false {
		global $wpdb;

		$provider          = sanitize_key( $data['provider'] ?? '' );
		$model             = sanitize_text_field( $data['model'] ?? '' );
		$feature           = sanitize_key( $data['feature'] ?? 'unknown' );
		$prompt_tokens     = max( 0, (int) ( $data['prompt_tokens'] ?? 0 ) );
		$completion_tokens = max( 0, (int) ( $data['completion_tokens'] ?? 0 ) );
		$generation_ms     = max( 0, (int) ( $data['generation_time_ms'] ?? 0 ) );

		// Auto-calculate cost if not supplied or is zero.
		$cost_usd = isset( $data['cost_usd'] ) && $data['cost_usd'] > 0.0
			? (float) $data['cost_usd']
			: $this->calculate_cost( $provider, $model, $prompt_tokens, $completion_tokens );

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'sfba_generations',
			[
				'post_id'            => max( 0, (int) ( $data['post_id'] ?? 0 ) ),
				'user_id'            => get_current_user_id(),
				'provider'           => $provider,
				'model'              => $model,
				'feature'            => $feature,
				'prompt_tokens'      => $prompt_tokens,
				'completion_tokens'  => $completion_tokens,
				'cost_usd'           => $cost_usd,
				'generation_time_ms' => $generation_ms,
				'created_at'         => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%d', '%s' ]
		);

		return ( false !== $result ) ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Calculate the USD cost for a generation from the built-in pricing table.
	 *
	 * Returns 0.0 for Ollama (local inference) and unknown models.
	 *
	 * Formula: (prompt_tokens / 1_000_000 × input_rate) + (completion_tokens / 1_000_000 × output_rate)
	 *
	 * @param string $provider          Provider slug.
	 * @param string $model             Model identifier.
	 * @param int    $prompt_tokens     Input token count.
	 * @param int    $completion_tokens Output token count.
	 * @return float Cost in USD, rounded to 6 decimal places.
	 */
	public function calculate_cost( string $provider, string $model, int $prompt_tokens, int $completion_tokens ): float {
		$rates = self::PRICING[ $provider ][ $model ] ?? null;

		// Try a prefix match for versioned model IDs (e.g. gpt-4o-2024-08-06).
		if ( null === $rates && isset( self::PRICING[ $provider ] ) ) {
			foreach ( self::PRICING[ $provider ] as $known_model => $known_rates ) {
				if ( str_starts_with( $model, $known_model ) || str_starts_with( $known_model, $model ) ) {
					$rates = $known_rates;
					break;
				}
			}
		}

		if ( null === $rates ) {
			return 0.0;
		}

		$cost = ( $prompt_tokens / 1_000_000 ) * $rates['input']
			  + ( $completion_tokens / 1_000_000 ) * $rates['output'];

		return round( $cost, 6 );
	}

	/**
	 * Return the headline cost summary for the current (or specified) month.
	 *
	 * @param string $from YYYY-MM-DD start (default: first day of current month).
	 * @param string $to   YYYY-MM-DD end   (default: today).
	 * @return array{
	 *     from: string,
	 *     to: string,
	 *     total_cost_usd: float,
	 *     total_generations: int,
	 *     avg_cost_per_generation: float,
	 *     total_tokens: int,
	 *     prompt_tokens: int,
	 *     completion_tokens: int,
	 *     avg_generation_time_ms: int,
	 *     headline: string,
	 *     budget_alert: array
	 * }
	 */
	public function get_monthly_summary( string $from = '', string $to = '' ): array {
		$range = $this->resolve_date_range( $from, $to );
		$base  = $this->get_summary( $range['from'], $range['to'] );

		$headline = sprintf(
			/* translators: 1: generation count, 2: total cost, 3: avg cost */
			__( 'This month: %1$d generations · $%2$s total · $%3$s/generation avg', 'super-fast-blog-ai' ),
			$base['total_generations'],
			number_format( $base['total_cost_usd'], 2 ),
			number_format( $base['avg_cost_per_generation'], 4 )
		);

		return array_merge( $base, [
			'from'             => $range['from'],
			'to'               => $range['to'],
			'this_month_cost'  => $base['total_cost_usd'],
			'headline'         => $headline,
			'budget_alert'     => $this->check_budget_alert( $base['total_cost_usd'] ),
		] );
	}

	/**
	 * Analyse actual usage and return model downgrade recommendations.
	 *
	 * Only suggestions that apply to models actually in use this month are returned.
	 * Sorted by estimated monthly savings (largest first).
	 *
	 * @return array<int, array{
	 *     current_model: string,
	 *     suggested_model: string,
	 *     content_types: string[],
	 *     quality_note: string,
	 *     estimated_savings_pct: int,
	 *     estimated_savings_usd: float
	 * }>
	 */
	public function suggest_cost_optimizations(): array {
		$usage = $this->get_model_usage_this_month();

		if ( empty( $usage ) ) {
			return [];
		}

		$suggestions = [];

		foreach ( self::OPTIMIZATION_MAP as $opt ) {
			$from_model    = $opt['from'];
			$from_provider = $opt['from_provider'];

			if ( ! isset( $usage[ $from_provider ][ $from_model ] ) ) {
				continue;
			}

			$model_usage      = $usage[ $from_provider ][ $from_model ];
			$applicable_types = array_intersect( $opt['types'], array_keys( $model_usage['by_type'] ) );

			if ( empty( $applicable_types ) ) {
				continue;
			}

			$applicable_cost = 0.0;
			foreach ( $applicable_types as $type ) {
				$applicable_cost += (float) ( $model_usage['by_type'][ $type ]['cost_usd'] ?? 0.0 );
			}

			$from_rates   = self::PRICING[ $from_provider ][ $from_model ] ?? [ 'input' => 0, 'output' => 0 ];
			$to_rates     = self::PRICING[ $opt['to_provider'] ][ $opt['to'] ] ?? [ 'input' => 0, 'output' => 0 ];
			$from_blended = ( $from_rates['input'] + $from_rates['output'] ) / 2;
			$to_blended   = ( $to_rates['input'] + $to_rates['output'] ) / 2;
			$savings_pct  = $from_blended > 0 ? (int) round( ( 1 - $to_blended / $from_blended ) * 100 ) : 0;
			$est_savings  = $from_blended > 0 ? round( $applicable_cost * ( 1 - $to_blended / $from_blended ), 4 ) : 0.0;

			$suggestions[] = [
				'current_model'         => $from_model,
				'current_provider'      => $from_provider,
				'suggested_model'       => $opt['to'],
				'suggested_provider'    => $opt['to_provider'],
				'content_types'         => array_values( $applicable_types ),
				'quality_note'          => $opt['quality_note'],
				'current_rate_per_1m'   => $from_blended,
				'suggested_rate_per_1m' => $to_blended,
				'estimated_savings_pct' => $savings_pct,
				'estimated_savings_usd' => $est_savings,
			];
		}

		usort( $suggestions, fn( $a, $b ) => $b['estimated_savings_usd'] <=> $a['estimated_savings_usd'] );

		return $suggestions;
	}

	/**
	 * Check whether the monthly spend is over, near, or within the configured budget.
	 *
	 * Budget is stored in sfba_settings['monthly_budget_usd'].
	 * Returns status = 'unset' if no budget is configured.
	 *
	 * Thresholds:
	 *   >= 100% → 'over'
	 *   >= 80%  → 'warning'
	 *   >= 50%  → 'notice'
	 *   <  50%  → 'ok'
	 *
	 * @param float|null $current_spend_usd Pre-computed spend (avoids a second DB query).
	 * @return array{status: string, budget_usd: float, spent_usd: float, remaining_usd: float, percent_used: int, message: string}
	 */
	public function check_budget_alert( ?float $current_spend_usd = null ): array {
		$budget_usd = (float) $this->core->settings->get( 'monthly_budget_usd', 0.0 );

		if ( $budget_usd <= 0.0 ) {
			return [
				'status'        => 'unset',
				'budget_usd'    => 0.0,
				'spent_usd'     => 0.0,
				'remaining_usd' => 0.0,
				'percent_used'  => 0,
				'message'       => __( 'No monthly budget configured. Set one in Settings.', 'super-fast-blog-ai' ),
			];
		}

		if ( null === $current_spend_usd ) {
			$range             = $this->resolve_date_range( '', '' );
			$summary           = $this->get_summary( $range['from'], $range['to'] );
			$current_spend_usd = (float) $summary['total_cost_usd'];
		}

		$pct       = $budget_usd > 0 ? (int) round( ( $current_spend_usd / $budget_usd ) * 100 ) : 0;
		$remaining = max( 0.0, round( $budget_usd - $current_spend_usd, 4 ) );

		if ( $pct >= 100 ) {
			$status  = 'over';
			$message = sprintf(
				/* translators: 1: amount overspent */
				__( 'Monthly budget exceeded by $%s. Consider pausing generations or increasing your budget.', 'super-fast-blog-ai' ),
				number_format( $current_spend_usd - $budget_usd, 2 )
			);
		} elseif ( $pct >= 80 ) {
			$status = 'warning';
			$message = sprintf(
				/* translators: 1: percentage used, 2: remaining amount */
				__( '%1$d%% of monthly budget used. $%2$s remaining.', 'super-fast-blog-ai' ),
				$pct,
				number_format( $remaining, 2 )
			);
		} elseif ( $pct >= 50 ) {
			$status = 'notice';
			$message = sprintf(
				/* translators: 1: percentage used, 2: remaining amount */
				__( '%1$d%% of monthly budget used. $%2$s remaining.', 'super-fast-blog-ai' ),
				$pct,
				number_format( $remaining, 2 )
			);
		} else {
			$status = 'ok';
			$message = sprintf(
				/* translators: 1: percentage used, 2: remaining amount */
				__( '%1$d%% of monthly budget used. $%2$s remaining.', 'super-fast-blog-ai' ),
				$pct,
				number_format( $remaining, 2 )
			);
		}

		return [
			'status'        => $status,
			'budget_usd'    => $budget_usd,
			'spent_usd'     => round( $current_spend_usd, 4 ),
			'remaining_usd' => $remaining,
			'percent_used'  => $pct,
			'message'       => $message,
		];
	}

	/**
	 * Return aggregate cost/generation totals for an arbitrary date range.
	 *
	 * @param string $from YYYY-MM-DD (default: first day of current month).
	 * @param string $to   YYYY-MM-DD (default: today).
	 * @return array{total_cost_usd: float, total_generations: int, avg_cost_per_generation: float, total_tokens: int, prompt_tokens: int, completion_tokens: int, avg_generation_time_ms: int}
	 */
	public function get_summary( string $from = '', string $to = '' ): array {
		global $wpdb;

		[ 'from' => $from, 'to' => $to ] = $this->resolve_date_range( $from, $to );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(cost_usd), 0)                AS total_cost_usd,
					COUNT(*)                                   AS total_generations,
					COALESCE(SUM(prompt_tokens), 0)           AS prompt_tokens,
					COALESCE(SUM(completion_tokens), 0)       AS completion_tokens,
					COALESCE(AVG(generation_time_ms), 0)      AS avg_generation_time_ms
				  FROM {$wpdb->prefix}sfba_generations
				 WHERE DATE(created_at) >= %s
				   AND DATE(created_at) <= %s",
				$from,
				$to
			),
			ARRAY_A
		);

		$total_cost = round( (float) ( $row['total_cost_usd'] ?? 0.0 ), 4 );
		$total_gen  = (int) ( $row['total_generations'] ?? 0 );
		$prompt     = (int) ( $row['prompt_tokens'] ?? 0 );
		$completion = (int) ( $row['completion_tokens'] ?? 0 );
		$avg_time   = (int) round( (float) ( $row['avg_generation_time_ms'] ?? 0 ) );

		return [
			'total_cost_usd'          => $total_cost,
			'total_generations'       => $total_gen,
			'avg_cost_per_generation' => $total_gen > 0 ? round( $total_cost / $total_gen, 6 ) : 0.0,
			'total_tokens'            => $prompt + $completion,
			'prompt_tokens'           => $prompt,
			'completion_tokens'       => $completion,
			'avg_generation_time_ms'  => $avg_time,
		];
	}

	/**
	 * Return cost breakdown grouped by provider.
	 *
	 * @return array<string, array{cost_usd: float, generations: int, tokens: int, avg_cost: float}>
	 */
	public function get_by_provider( string $from = '', string $to = '' ): array {
		return $this->aggregate_by( 'provider', $from, $to );
	}

	/**
	 * Return cost breakdown grouped by model.
	 *
	 * @return array<string, array{cost_usd: float, generations: int, tokens: int, avg_cost: float}>
	 */
	public function get_by_model( string $from = '', string $to = '' ): array {
		return $this->aggregate_by( 'model', $from, $to );
	}

	/**
	 * Return cost breakdown grouped by feature tag.
	 *
	 * Feature tags: blog_generation, title_generation, brand_voice, calendar,
	 * repurposing, internal_linking, seo_analysis, rewrite, etc.
	 *
	 * @return array<string, array{cost_usd: float, generations: int, tokens: int, avg_cost: float}>
	 */
	public function get_by_feature( string $from = '', string $to = '' ): array {
		return $this->aggregate_by( 'feature', $from, $to );
	}

	/**
	 * Delete log entries older than the retention period.
	 *
	 * Called monthly by WP-Cron (sfba_prune_generation_log hook).
	 *
	 * @param int $days Rows older than this many days are deleted.
	 * @return int|WP_Error Rows deleted, or WP_Error.
	 */
	public function prune_old_logs( int $days = self::DEFAULT_RETENTION_DAYS ): int|WP_Error {
		global $wpdb;

		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}sfba_generations
				  WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )",
				absint( $days )
			)
		);

		return ( false === $deleted )
			? new WP_Error( 'sfba_db_error', __( 'Failed to prune generation logs.', 'super-fast-blog-ai' ) )
			: (int) $deleted;
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Generic GROUP BY aggregation query on sfba_generations.
	 *
	 * @param string $column Column to group by ('provider', 'model', 'feature').
	 * @return array<string, array{cost_usd: float, generations: int, tokens: int, avg_cost: float}>
	 */
	private function aggregate_by( string $column, string $from, string $to ): array {
		global $wpdb;

		$allowed = [ 'provider', 'model', 'feature' ];
		if ( ! in_array( $column, $allowed, true ) ) {
			return [];
		}

		[ 'from' => $from, 'to' => $to ] = $this->resolve_date_range( $from, $to );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					{$column}                                                AS label,
					COALESCE(SUM(cost_usd), 0)                              AS cost_usd,
					COUNT(*)                                                 AS generations,
					COALESCE(SUM(prompt_tokens + completion_tokens), 0)     AS tokens
				  FROM {$wpdb->prefix}sfba_generations
				 WHERE DATE(created_at) >= %s
				   AND DATE(created_at) <= %s
				   AND {$column} != ''
				 GROUP BY {$column}
				 ORDER BY cost_usd DESC",
				$from,
				$to
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$result = [];
		foreach ( $rows as $row ) {
			$label            = $row['label'];
			$cost             = round( (float) $row['cost_usd'], 6 );
			$gens             = (int) $row['generations'];
			$result[ $label ] = [
				'cost_usd'    => $cost,
				'generations' => $gens,
				'tokens'      => (int) $row['tokens'],
				'avg_cost'    => $gens > 0 ? round( $cost / $gens, 6 ) : 0.0,
			];
		}

		return $result;
	}

	/**
	 * Return model+type usage for the current month, grouped as:
	 * [ provider => [ model => [ 'total_cost' => float, 'by_type' => [ feature => [cost, tokens] ] ] ] ]
	 *
	 * Used by suggest_cost_optimizations() to find what's actually running.
	 */
	private function get_model_usage_this_month(): array {
		global $wpdb;

		$range = $this->resolve_date_range( '', '' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider, model, feature,
				        SUM(cost_usd) AS cost_usd,
				        SUM(prompt_tokens + completion_tokens) AS tokens
				   FROM {$wpdb->prefix}sfba_generations
				  WHERE DATE(created_at) >= %s
				    AND DATE(created_at) <= %s
				    AND provider != ''
				    AND model != ''
				  GROUP BY provider, model, feature",
				$range['from'],
				$range['to']
			),
			ARRAY_A
		);

		$usage = [];
		foreach ( $rows as $row ) {
			$p = $row['provider'];
			$m = $row['model'];
			$t = $row['feature'];

			if ( ! isset( $usage[ $p ][ $m ] ) ) {
				$usage[ $p ][ $m ] = [ 'total_cost' => 0.0, 'by_type' => [] ];
			}

			$cost = (float) $row['cost_usd'];
			$usage[ $p ][ $m ]['total_cost']     += $cost;
			$usage[ $p ][ $m ]['by_type'][ $t ]   = [
				'cost_usd' => $cost,
				'tokens'   => (int) $row['tokens'],
			];
		}

		return $usage;
	}

	/**
	 * Resolve a date range, defaulting to the current calendar month.
	 *
	 * @return array{from: string, to: string}
	 */
	private function resolve_date_range( string $from, string $to ): array {
		$default_from = gmdate( 'Y-m-01' );
		$default_to   = gmdate( 'Y-m-d' );

		$from_ts = $from ? strtotime( $from ) : false;
		$to_ts   = $to   ? strtotime( $to )   : false;

		return [
			'from' => $from_ts ? gmdate( 'Y-m-d', $from_ts ) : $default_from,
			'to'   => $to_ts   ? gmdate( 'Y-m-d', $to_ts )   : $default_to,
		];
	}
}
