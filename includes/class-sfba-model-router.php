<?php
/**
 * Smart Model Router — MODULE 11.
 *
 * Manages the sfba_routing_rules table, resolves which provider + model handles
 * each content type, and runs A/B test generations for quality comparison.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │  How routing works                                                       │
 * │                                                                          │
 * │  1. DB lookup  — active rule in sfba_routing_rules for this content_type │
 * │  2. Default    — user's default_provider / default_model setting        │
 * │  3. Any key    — first provider with a configured API key               │
 * │                                                                          │
 * │  SFBA_Providers::resolve_route() implements steps 2-3.                  │
 * │  This class owns step 1 (CRUD on sfba_routing_rules) and wraps all 3   │
 * │  with enriched metadata (display name, pricing, model list).            │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Public API:
 *   get_model_for_content_type( $type )  → array   (enriched route)
 *   set_routing_rule( $type, $provider, $model, $opts ) → int|WP_Error
 *   ab_test_generation( $prompt, $type, $opts ) → array|WP_Error
 *   get_all_rules()                      → array
 *   delete_rule( $rule_id )              → bool|WP_Error
 *   seed_default_rules()                 → int   (rows inserted)
 *
 * REST surface:
 *   GET    /routing/rules                — all rules
 *   POST   /routing/rules                — create / update rule
 *   POST   /routing/rules/{id}           — update single rule
 *   DELETE /routing/rules/{id}           — delete rule
 *   GET    /routing/resolve/{type}       — resolve for a content type
 *   POST   /routing/ab-test             — run A/B test
 *   POST   /routing/seed-defaults        — seed sensible default rules
 *
 * DB: {prefix}sfba_routing_rules
 *   id, content_type, provider, model, max_tokens, temperature, priority, is_active
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFBA_Model_Router
 */
class SFBA_Model_Router {

	// -------------------------------------------------------------------------
	// Supported content types.
	// -------------------------------------------------------------------------

	public const CONTENT_TYPES = [
		'blog'    => 'Blog / Long-form Article',
		'product' => 'WooCommerce Product Description',
		'social'  => 'Social Media Post',
		'meta'    => 'SEO Meta Title & Description',
		'email'   => 'Email Newsletter Excerpt',
	];

	// -------------------------------------------------------------------------
	// Recommended defaults — "golden path" routing per module guide.
	//
	// blog    → Claude Sonnet  (best long-form reasoning)
	// product → GPT-4o-mini    (fast + cheap, short outputs)
	// social  → Gemini 2.5 Flash (free tier, quick)
	// meta    → GPT-4o-mini    (cheap, short)
	// email   → Claude Haiku   (cheap, good prose)
	// -------------------------------------------------------------------------

	private const DEFAULT_RULES = [
		[
			'content_type' => 'blog',
			'provider'     => 'anthropic',
			'model'        => 'claude-sonnet-4-6',
			'max_tokens'   => 8192,
			'temperature'  => 0.70,
			'priority'     => 10,
		],
		[
			'content_type' => 'product',
			'provider'     => 'openai',
			'model'        => 'gpt-4o-mini',
			'max_tokens'   => 1024,
			'temperature'  => 0.65,
			'priority'     => 10,
		],
		[
			'content_type' => 'social',
			'provider'     => 'google',
			'model'        => 'gemini-2.5-flash',
			'max_tokens'   => 512,
			'temperature'  => 0.80,
			'priority'     => 10,
		],
		[
			'content_type' => 'meta',
			'provider'     => 'openai',
			'model'        => 'gpt-4o-mini',
			'max_tokens'   => 256,
			'temperature'  => 0.50,
			'priority'     => 10,
		],
		[
			'content_type' => 'email',
			'provider'     => 'anthropic',
			'model'        => 'claude-haiku-4-5',
			'max_tokens'   => 1024,
			'temperature'  => 0.70,
			'priority'     => 10,
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

		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );

		// GET /routing/rules — all active and inactive rules.
		$this->core->rest_api->register_route( '/routing/rules', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_rules' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
			// POST /routing/rules — create new rule.
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_set_rule' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => $this->rule_schema_args( required: true ),
			],
		] );

		// POST/DELETE /routing/rules/{id} — update or delete a single rule.
		$this->core->rest_api->register_route( '/routing/rules/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_update_rule' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => array_merge(
					[ 'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ] ],
					$this->rule_schema_args( required: false )
				),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'rest_delete_rule' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
				],
			],
		] );

		// GET /routing/resolve/{type} — show effective route for a content type.
		$this->core->rest_api->register_route( '/routing/resolve/(?P<type>[a-z_]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_resolve' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'type' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );

		// POST /routing/ab-test — run two models on the same prompt.
		$this->core->rest_api->register_route( '/routing/ab-test', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_ab_test' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'prompt'       => [ 'type' => 'string',  'required' => true ],
					'content_type' => [ 'type' => 'string',  'required' => false, 'default' => 'blog' ],
					'model_a'      => [ 'type' => 'string',  'required' => false, 'default' => '' ],
					'provider_a'   => [ 'type' => 'string',  'required' => false, 'default' => '' ],
					'model_b'      => [ 'type' => 'string',  'required' => false, 'default' => '' ],
					'provider_b'   => [ 'type' => 'string',  'required' => false, 'default' => '' ],
					'max_tokens'   => [ 'type' => 'integer', 'required' => false, 'default' => 1024 ],
					'post_id'      => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
				],
			],
		] );

		// POST /routing/seed-defaults — populate table with golden-path rules.
		$this->core->rest_api->register_route( '/routing/seed-defaults', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_seed_defaults' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Model Routing — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Model Routing', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-model-routing',
			[ $this, 'render_routing_page' ]
		);
	}

	public function render_routing_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$rules         = $this->get_all_rules();
		$providers     = $this->core->providers->get_all_providers();
		$content_types = self::CONTENT_TYPES;
		$api_base      = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce         = wp_create_nonce( 'wp_rest' );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-routing.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** GET /routing/rules */
	public function rest_get_rules( WP_REST_Request $request ): WP_REST_Response {
		return SFBA_Rest_Api::success( [
			'rules'         => $this->get_all_rules(),
			'content_types' => self::CONTENT_TYPES,
		] );
	}

	/** POST /routing/rules */
	public function rest_set_rule( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->set_routing_rule(
			(string) $request->get_param( 'content_type' ),
			(string) $request->get_param( 'provider' ),
			(string) $request->get_param( 'model' ),
			$this->options_from_request( $request )
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'rule_id' => $result, 'rule' => $this->get_rule( $result ) ] );
	}

	/** POST /routing/rules/{id} */
	public function rest_update_rule( WP_REST_Request $request ): WP_REST_Response {
		$rule_id = (int) $request->get_param( 'id' );
		$result  = $this->update_rule( $rule_id, $this->full_data_from_request( $request ) );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'updated' => true, 'rule' => $this->get_rule( $rule_id ) ] );
	}

	/** DELETE /routing/rules/{id} */
	public function rest_delete_rule( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->delete_rule( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'deleted' => true ] );
	}

	/** GET /routing/resolve/{type} */
	public function rest_resolve( WP_REST_Request $request ): WP_REST_Response {
		$type   = sanitize_key( $request->get_param( 'type' ) );
		$result = $this->get_model_for_content_type( $type );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /routing/ab-test */
	public function rest_ab_test( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->ab_test_generation(
			(string) $request->get_param( 'prompt' ),
			(string) $request->get_param( 'content_type' ),
			[
				'provider_a'  => (string) $request->get_param( 'provider_a' ),
				'model_a'     => (string) $request->get_param( 'model_a' ),
				'provider_b'  => (string) $request->get_param( 'provider_b' ),
				'model_b'     => (string) $request->get_param( 'model_b' ),
				'max_tokens'  => (int) $request->get_param( 'max_tokens' ),
				'post_id'     => (int) $request->get_param( 'post_id' ),
			]
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /routing/seed-defaults */
	public function rest_seed_defaults( WP_REST_Request $request ): WP_REST_Response {
		$inserted = $this->seed_default_rules();

		return SFBA_Rest_Api::success( [
			'inserted' => $inserted,
			'message'  => sprintf(
				/* translators: %d: number of rules seeded */
				_n( '%d default routing rule added.', '%d default routing rules added.', $inserted, 'super-fast-blog-ai' ),
				$inserted
			),
		] );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Resolve which provider + model handles a content type, with full metadata.
	 *
	 * @param string $content_type One of: blog, product, social, meta, email.
	 * @return array|WP_Error
	 */
	public function get_model_for_content_type( string $content_type ): array|WP_Error {
		if ( ! array_key_exists( $content_type, self::CONTENT_TYPES ) ) {
			return new WP_Error(
				'sfba_invalid_input',
				sprintf(
					/* translators: 1: given type, 2: valid types list */
					__( 'Unknown content type "%1$s". Valid types: %2$s.', 'super-fast-blog-ai' ),
					$content_type,
					implode( ', ', array_keys( self::CONTENT_TYPES ) )
				)
			);
		}

		$source = $this->determine_source( $content_type );
		$route  = $this->core->providers->resolve_route( $content_type );

		if ( null === $route ) {
			return new WP_Error(
				'sfba_no_provider',
				__( 'No AI provider is configured. Add an API key in Super Fast Blog AI → Settings.', 'super-fast-blog-ai' )
			);
		}

		// Enrich with display name and pricing.
		$provider_name = $this->provider_display_name( $route['provider'] );
		$pricing       = SFBA_Cost_Tracker::PRICING[ $route['provider'] ][ $route['model'] ]
			?? [ 'input' => 0.0, 'output' => 0.0 ];

		// Blended estimate for 1K tokens (typical 70/30 prompt/completion split).
		$est_per_1k = round(
			( 700 / 1_000_000 ) * $pricing['input'] + ( 300 / 1_000_000 ) * $pricing['output'],
			6
		);

		return [
			'content_type'                 => $content_type,
			'content_type_label'           => self::CONTENT_TYPES[ $content_type ],
			'provider'                     => $route['provider'],
			'provider_display_name'        => $provider_name,
			'model'                        => $route['model'],
			'max_tokens'                   => $route['max_tokens'],
			'temperature'                  => $route['temperature'],
			'source'                       => $source,
			'pricing'                      => $pricing,
			'estimated_cost_per_1k_tokens' => $est_per_1k,
		];
	}

	/**
	 * Create or update a routing rule for a content type.
	 *
	 * @param string $content_type
	 * @param string $provider
	 * @param string $model
	 * @param array  $options
	 * @return int|WP_Error
	 */
	public function set_routing_rule( string $content_type, string $provider, string $model, array $options = [] ): int|WP_Error {
		global $wpdb;

		$content_type = sanitize_key( $content_type );
		$provider     = sanitize_key( $provider );
		$model        = sanitize_text_field( $model );

		if ( ! array_key_exists( $content_type, self::CONTENT_TYPES ) ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Invalid content type.', 'super-fast-blog-ai' ) );
		}

		if ( '' === $provider ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Provider is required.', 'super-fast-blog-ai' ) );
		}

		if ( '' === $model ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Model is required.', 'super-fast-blog-ai' ) );
		}

		$max_tokens  = max( 64, min( 200000, (int) ( $options['max_tokens'] ?? 4096 ) ) );
		$temperature = max( 0.0, min( 2.0, (float) ( $options['temperature'] ?? 0.70 ) ) );
		$priority    = max( 1, (int) ( $options['priority'] ?? 10 ) );
		$is_active   = isset( $options['is_active'] ) ? (int) (bool) $options['is_active'] : 1;

		// Check for an existing rule for this content_type.
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sfba_routing_rules WHERE content_type = %s ORDER BY priority ASC LIMIT 1",
				$content_type
			)
		);

		if ( $existing_id > 0 ) {
			$result = $this->update_rule( $existing_id, compact( 'provider', 'model', 'max_tokens', 'temperature', 'priority', 'is_active' ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $existing_id;
		}

		// Insert new rule.
		$ok = $wpdb->insert(
			$wpdb->prefix . 'sfba_routing_rules',
			[
				'content_type' => $content_type,
				'provider'     => $provider,
				'model'        => $model,
				'max_tokens'   => $max_tokens,
				'temperature'  => $temperature,
				'priority'     => $priority,
				'is_active'    => $is_active,
			],
			[ '%s', '%s', '%s', '%d', '%f', '%d', '%d' ]
		);

		if ( false === $ok ) {
			return new WP_Error( 'sfba_db_error', __( 'Failed to insert routing rule.', 'super-fast-blog-ai' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Run the same prompt through two different models for quality/cost comparison.
	 *
	 * @param string $prompt
	 * @param string $content_type
	 * @param array  $options
	 * @return array|WP_Error
	 */
	public function ab_test_generation( string $prompt, string $content_type = 'blog', array $options = [] ): array|WP_Error {
		if ( trim( $prompt ) === '' ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Prompt cannot be empty.', 'super-fast-blog-ai' ) );
		}

		$max_tokens = (int) ( $options['max_tokens'] ?? 1024 );
		$post_id    = (int) ( $options['post_id'] ?? 0 );

		$config_a = $this->resolve_ab_model(
			$content_type,
			$options['provider_a'] ?? '',
			$options['model_a'] ?? '',
			'a'
		);

		$config_b = $this->resolve_ab_model(
			$content_type,
			$options['provider_b'] ?? '',
			$options['model_b'] ?? '',
			'b',
			$config_a
		);

		if ( is_wp_error( $config_a ) ) {
			return $config_a;
		}

		if ( is_wp_error( $config_b ) ) {
			return $config_b;
		}

		if ( $config_a['provider'] === $config_b['provider'] && $config_a['model'] === $config_b['model'] ) {
			return new WP_Error(
				'sfba_ab_same_model',
				__( 'A/B test requires two different models. Specify model_a and model_b explicitly.', 'super-fast-blog-ai' )
			);
		}

		$gen_a = $this->run_ab_side( $prompt, $config_a, $max_tokens, $content_type, $post_id );

		if ( is_wp_error( $gen_a ) ) {
			return new WP_Error( 'sfba_ab_model_a_failed', $gen_a->get_error_message() );
		}

		$gen_b = $this->run_ab_side( $prompt, $config_b, $max_tokens, $content_type, $post_id );

		if ( is_wp_error( $gen_b ) ) {
			return new WP_Error( 'sfba_ab_model_b_failed', $gen_b->get_error_message() );
		}

		$result_a = $this->format_ab_side( $config_a, $gen_a );
		$result_b = $this->format_ab_side( $config_b, $gen_b );

		return [
			'prompt_length' => mb_strlen( $prompt ),
			'content_type'  => $content_type,
			'model_a'       => $result_a,
			'model_b'       => $result_b,
			'comparison'    => $this->compare_ab( $result_a, $result_b ),
		];
	}

	/**
	 * Return all routing rules from the DB, ordered by content_type + priority.
	 *
	 * @return array
	 */
	public function get_all_rules(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}sfba_routing_rules ORDER BY content_type ASC, priority ASC",
			ARRAY_A
		);

		return array_map( [ $this, 'format_rule' ], $rows ?: [] );
	}

	/**
	 * Delete a routing rule.
	 *
	 * @param int $rule_id
	 * @return bool|WP_Error
	 */
	public function delete_rule( int $rule_id ): bool|WP_Error {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sfba_routing_rules WHERE id = %d LIMIT 1",
				$rule_id
			)
		);

		if ( ! $exists ) {
			return new WP_Error( 'sfba_not_found', __( 'Routing rule not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'sfba_routing_rules',
			[ 'id' => $rule_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Seed the routing_rules table with defaults.
	 * Only inserts rules for content types that have no existing rule.
	 *
	 * @return int Number of rows inserted.
	 */
	public function seed_default_rules(): int {
		$inserted = 0;

		foreach ( self::DEFAULT_RULES as $rule ) {
			$result = $this->set_routing_rule(
				$rule['content_type'],
				$rule['provider'],
				$rule['model'],
				[
					'max_tokens'  => $rule['max_tokens'],
					'temperature' => $rule['temperature'],
					'priority'    => $rule['priority'],
					'is_active'   => 1,
				]
			);

			if ( is_int( $result ) && $result > 0 ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	// -------------------------------------------------------------------------
	// Private — rule CRUD helpers.
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single rule by ID.
	 *
	 * @param int $rule_id
	 * @return array|null
	 */
	private function get_rule( int $rule_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sfba_routing_rules WHERE id = %d LIMIT 1",
				$rule_id
			),
			ARRAY_A
		);

		return $row ? $this->format_rule( $row ) : null;
	}

	/**
	 * Update fields on an existing rule.
	 *
	 * @param int   $rule_id
	 * @param array $data
	 * @return bool|WP_Error
	 */
	private function update_rule( int $rule_id, array $data ): bool|WP_Error {
		global $wpdb;

		$update  = [];
		$formats = [];

		if ( isset( $data['provider'] ) ) {
			$update['provider'] = sanitize_key( $data['provider'] );
			$formats[]          = '%s';
		}

		if ( isset( $data['model'] ) ) {
			$update['model'] = sanitize_text_field( $data['model'] );
			$formats[]       = '%s';
		}

		if ( isset( $data['max_tokens'] ) ) {
			$update['max_tokens'] = max( 64, min( 200000, (int) $data['max_tokens'] ) );
			$formats[]            = '%d';
		}

		if ( isset( $data['temperature'] ) ) {
			$update['temperature'] = max( 0.0, min( 2.0, (float) $data['temperature'] ) );
			$formats[]             = '%f';
		}

		if ( isset( $data['priority'] ) ) {
			$update['priority'] = max( 1, (int) $data['priority'] );
			$formats[]          = '%d';
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
			$formats[]           = '%d';
		}

		if ( empty( $update ) ) {
			return true;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'sfba_routing_rules',
			$update,
			[ 'id' => $rule_id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error( 'sfba_db_error', __( 'Failed to update routing rule.', 'super-fast-blog-ai' ) );
		}

		return true;
	}

	/**
	 * Format a raw DB row for API output.
	 *
	 * @param array $row
	 * @return array
	 */
	private function format_rule( array $row ): array {
		$provider = $row['provider'];
		$model    = $row['model'];
		$pricing  = SFBA_Cost_Tracker::PRICING[ $provider ][ $model ] ?? [ 'input' => 0.0, 'output' => 0.0 ];

		return [
			'id'                    => (int) $row['id'],
			'content_type'          => $row['content_type'],
			'content_type_label'    => self::CONTENT_TYPES[ $row['content_type'] ] ?? $row['content_type'],
			'provider'              => $provider,
			'provider_display_name' => $this->provider_display_name( $provider ),
			'model'                 => $model,
			'max_tokens'            => (int) $row['max_tokens'],
			'temperature'           => (float) $row['temperature'],
			'priority'              => (int) $row['priority'],
			'is_active'             => (bool) $row['is_active'],
			'pricing'               => $pricing,
		];
	}

	// -------------------------------------------------------------------------
	// Private — A/B test helpers.
	// -------------------------------------------------------------------------

	/**
	 * Resolve a model config for one side of the A/B test.
	 *
	 * @param string     $content_type
	 * @param string     $provider
	 * @param string     $model
	 * @param string     $side         'a' or 'b'
	 * @param array|null $other_side
	 * @return array|WP_Error
	 */
	private function resolve_ab_model( string $content_type, string $provider, string $model, string $side, ?array $other_side = null ): array|WP_Error {
		// Explicit override wins.
		if ( '' !== $provider && '' !== $model ) {
			return [ 'provider' => $provider, 'model' => $model ];
		}

		global $wpdb;

		$rules = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT provider, model FROM {$wpdb->prefix}sfba_routing_rules
				  WHERE content_type = %s AND is_active = 1
				  ORDER BY priority ASC",
				$content_type
			),
			ARRAY_A
		);

		if ( 'a' === $side ) {
			if ( ! empty( $rules ) ) {
				return [ 'provider' => $rules[0]['provider'], 'model' => $rules[0]['model'] ];
			}

			$route = $this->core->providers->resolve_route( $content_type );
			if ( $route ) {
				return [ 'provider' => $route['provider'], 'model' => $route['model'] ];
			}

			return new WP_Error( 'sfba_no_provider', __( 'No provider configured for side A.', 'super-fast-blog-ai' ) );
		}

		// Side B: pick a different rule than A.
		if ( null !== $other_side ) {
			foreach ( $rules as $rule ) {
				if ( $rule['provider'] !== $other_side['provider'] || $rule['model'] !== $other_side['model'] ) {
					return [ 'provider' => $rule['provider'], 'model' => $rule['model'] ];
				}
			}
		}

		$cheapest = $this->cheapest_alternative( $content_type, $other_side );
		if ( $cheapest ) {
			return $cheapest;
		}

		return new WP_Error(
			'sfba_no_model_b',
			__( 'Could not find a second model for the A/B test. Specify provider_b and model_b explicitly.', 'super-fast-blog-ai' )
		);
	}

	/**
	 * Find the cheapest available model for a content type that is not the excluded model.
	 *
	 * @param string     $content_type
	 * @param array|null $exclude
	 * @return array|null
	 */
	private function cheapest_alternative( string $content_type, ?array $exclude ): ?array {
		$best_rate = PHP_FLOAT_MAX;
		$best      = null;

		$prompt_share = match( $content_type ) {
			'meta', 'social', 'email' => 0.50,
			default                   => 0.30,
		};

		foreach ( SFBA_Cost_Tracker::PRICING as $prov => $models ) {
			if ( '' === $this->core->settings->get_api_key( $prov ) && 'ollama' !== $prov ) {
				continue;
			}

			foreach ( $models as $mod => $rates ) {
				if ( $exclude && $prov === $exclude['provider'] && $mod === $exclude['model'] ) {
					continue;
				}

				// Skip $0 Ollama (would "win" every comparison trivially).
				if ( 'ollama' === $prov ) {
					continue;
				}

				$blended = $prompt_share * $rates['input'] + ( 1 - $prompt_share ) * $rates['output'];

				if ( $blended < $best_rate ) {
					$best_rate = $blended;
					$best      = [ 'provider' => $prov, 'model' => $mod ];
				}
			}
		}

		return $best;
	}

	/**
	 * Run one side of an A/B test by calling the provider's generate() directly.
	 *
	 * Falls back to a mock response when the provider has no API key configured.
	 *
	 * @param string $prompt
	 * @param array  $config     {provider, model}
	 * @param int    $max_tokens
	 * @param string $content_type
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	private function run_ab_side( string $prompt, array $config, int $max_tokens, string $content_type, int $post_id ): array|WP_Error {
		$provider_slug = $config['provider'];
		$model         = $config['model'];

		$start_ms = (int) round( microtime( true ) * 1000 );

		$provider = $this->core->providers->get_provider( $provider_slug );

		// Fall back to mock when provider is unavailable or has no API key.
		if ( null === $provider || '' === $this->core->settings->get_api_key( $provider_slug ) ) {
			$text    = $this->mock_ab_text( $prompt, $provider_slug, $model );
			$elapsed = (int) round( microtime( true ) * 1000 ) - $start_ms;

			$this->core->cost_tracker->log( [
				'post_id'            => $post_id,
				'provider'           => $provider_slug,
				'model'              => $model,
				'prompt_tokens'      => 0,
				'completion_tokens'  => 0,
				'cost_usd'           => 0.0,
				'feature'            => 'ab_test',
				'generation_time_ms' => $elapsed,
			] );

			return [
				'text'               => $text,
				'tokens_used'        => 0,
				'cost_usd'           => 0.0,
				'generation_time_ms' => $elapsed,
				'mock'               => true,
			];
		}

		// Real generation.
		$gen = $provider->generate( $prompt, [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => 0.70,
		] );

		$elapsed = (int) round( microtime( true ) * 1000 ) - $start_ms;

		if ( is_wp_error( $gen ) ) {
			$text = $this->mock_ab_text( $prompt, $provider_slug, $model );

			$this->core->cost_tracker->log( [
				'post_id'            => $post_id,
				'provider'           => $provider_slug,
				'model'              => $model,
				'prompt_tokens'      => 0,
				'completion_tokens'  => 0,
				'cost_usd'           => 0.0,
				'feature'            => 'ab_test',
				'generation_time_ms' => $elapsed,
			] );

			return [
				'text'               => '[Mock — ' . $gen->get_error_message() . ']',
				'tokens_used'        => 0,
				'cost_usd'           => 0.0,
				'generation_time_ms' => $elapsed,
				'mock'               => true,
			];
		}

		// Log successful real generation.
		$tokens = (int) ( $gen['tokens_used'] ?? 0 );
		$cost   = $this->core->cost_tracker->calculate_cost(
			$provider_slug,
			$model,
			(int) ( $gen['prompt_tokens'] ?? (int) ( $tokens * 0.4 ) ),
			(int) ( $gen['completion_tokens'] ?? (int) ( $tokens * 0.6 ) )
		);

		$this->core->cost_tracker->log( [
			'post_id'            => $post_id,
			'provider'           => $provider_slug,
			'model'              => $model,
			'prompt_tokens'      => (int) ( $gen['prompt_tokens'] ?? 0 ),
			'completion_tokens'  => (int) ( $gen['completion_tokens'] ?? $tokens ),
			'cost_usd'           => $cost,
			'feature'            => 'ab_test',
			'generation_time_ms' => $elapsed,
		] );

		return [
			'text'               => $gen['text'] ?? '',
			'tokens_used'        => $tokens,
			'cost_usd'           => $cost,
			'generation_time_ms' => $elapsed,
			'mock'               => false,
		];
	}

	/**
	 * Return a mock A/B text that makes the comparison display readable.
	 *
	 * @param string $prompt
	 * @param string $provider
	 * @param string $model
	 * @return string
	 */
	private function mock_ab_text( string $prompt, string $provider, string $model ): string {
		$excerpt = mb_substr( trim( wp_strip_all_tags( $prompt ) ), 0, 60 );
		return "[Mock — {$provider}/{$model}] Response to: \"{$excerpt}...\"\n\n"
			. "AI content generation is transforming how businesses approach marketing. "
			. "By automating routine writing tasks, teams can focus on strategy and creativity. "
			. "This is a placeholder response — configure an API key to see real output.\n\n"
			. "[Configure in Super Fast Blog AI → Settings]";
	}

	/**
	 * Format one side of an A/B result for API output.
	 *
	 * @param array $config
	 * @param array $gen
	 * @return array
	 */
	private function format_ab_side( array $config, array $gen ): array {
		return [
			'provider'           => $config['provider'],
			'provider_name'      => $this->provider_display_name( $config['provider'] ),
			'model'              => $config['model'],
			'text'               => $gen['text'] ?? '',
			'text_length'        => mb_strlen( $gen['text'] ?? '' ),
			'cost_usd'           => $gen['cost_usd'] ?? 0.0,
			'generation_time_ms' => $gen['generation_time_ms'] ?? 0,
			'tokens_used'        => $gen['tokens_used'] ?? 0,
			'mock'               => $gen['mock'] ?? false,
		];
	}

	/**
	 * Build a comparison summary between two A/B generation results.
	 *
	 * @param array $a
	 * @param array $b
	 * @return array
	 */
	private function compare_ab( array $a, array $b ): array {
		$cost_diff = round( $a['cost_usd'] - $b['cost_usd'], 6 );
		$time_diff = $a['generation_time_ms'] - $b['generation_time_ms'];
		$len_diff  = $a['text_length'] - $b['text_length'];

		$cost_a   = $a['cost_usd'];
		$cost_b   = $b['cost_usd'];
		$cost_pct = ( $cost_a > 0 )
			? (int) round( abs( $cost_a - $cost_b ) / $cost_a * 100 )
			: 0;

		$cheaper = match( true ) {
			$cost_a < $cost_b => 'model_a',
			$cost_b < $cost_a => 'model_b',
			default            => 'tie',
		};

		$faster = match( true ) {
			$a['generation_time_ms'] < $b['generation_time_ms'] => 'model_a',
			$b['generation_time_ms'] < $a['generation_time_ms'] => 'model_b',
			default                                              => 'tie',
		};

		$longer = match( true ) {
			$a['text_length'] > $b['text_length'] => 'model_a',
			$b['text_length'] > $a['text_length'] => 'model_b',
			default                                => 'tie',
		};

		return [
			'cost_diff_usd'     => abs( $cost_diff ),
			'cost_diff_pct'     => $cost_pct,
			'time_diff_ms'      => abs( $time_diff ),
			'length_diff_chars' => abs( $len_diff ),
			'cheaper_model'     => $cheaper,
			'faster_model'      => $faster,
			'longer_output'     => $longer,
		];
	}

	// -------------------------------------------------------------------------
	// Private — routing source detection.
	// -------------------------------------------------------------------------

	/**
	 * Determine where the current route for a content type comes from.
	 *
	 * @param string $content_type
	 * @return string 'rule' | 'default' | 'fallback' | 'none'
	 */
	private function determine_source( string $content_type ): string {
		global $wpdb;

		$has_rule = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}sfba_routing_rules WHERE content_type = %s AND is_active = 1 LIMIT 1",
				$content_type
			)
		);

		if ( $has_rule ) {
			return 'rule';
		}

		$default_provider = $this->core->settings->get( 'default_provider', '' );
		if ( $default_provider ) {
			return 'default';
		}

		foreach ( $this->core->providers->get_registered_slugs() as $slug ) {
			if ( '' !== $this->core->settings->get_api_key( $slug ) ) {
				return 'fallback';
			}
		}

		return 'none';
	}

	// -------------------------------------------------------------------------
	// Private — misc helpers.
	// -------------------------------------------------------------------------

	/**
	 * Return the human-readable display name for a provider slug.
	 *
	 * @param string $slug
	 * @return string
	 */
	private function provider_display_name( string $slug ): string {
		$names = [
			'openai'     => 'OpenAI',
			'anthropic'  => 'Anthropic',
			'google'     => 'Google',
			'openrouter' => 'OpenRouter',
			'deepseek'   => 'DeepSeek',
			'mistral'    => 'Mistral',
			'ollama'     => 'Ollama (Local)',
		];

		return $names[ $slug ] ?? ucfirst( $slug );
	}

	/**
	 * REST arg schema for rule create/update.
	 *
	 * @param bool $required
	 * @return array
	 */
	private function rule_schema_args( bool $required = true ): array {
		return [
			'content_type' => [
				'type'     => 'string',
				'required' => $required,
				'enum'     => array_keys( self::CONTENT_TYPES ),
			],
			'provider'    => [ 'type' => 'string', 'required' => $required ],
			'model'       => [ 'type' => 'string', 'required' => $required ],
			'max_tokens'  => [ 'type' => 'integer', 'required' => false, 'default' => 4096, 'minimum' => 64, 'maximum' => 200000 ],
			'temperature' => [ 'type' => 'number',  'required' => false, 'default' => 0.70,  'minimum' => 0.0, 'maximum' => 2.0 ],
			'priority'    => [ 'type' => 'integer', 'required' => false, 'default' => 10,   'minimum' => 1 ],
			'is_active'   => [ 'type' => 'boolean', 'required' => false, 'default' => true ],
		];
	}

	/**
	 * Extract rule options from a REST request.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function options_from_request( WP_REST_Request $request ): array {
		return [
			'max_tokens'  => (int) $request->get_param( 'max_tokens' ),
			'temperature' => (float) $request->get_param( 'temperature' ),
			'priority'    => (int) $request->get_param( 'priority' ),
			'is_active'   => (bool) $request->get_param( 'is_active' ),
		];
	}

	/**
	 * Extract all updatable fields from a REST request (for update route).
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function full_data_from_request( WP_REST_Request $request ): array {
		return array_filter( [
			'content_type' => $request->get_param( 'content_type' ),
			'provider'     => $request->get_param( 'provider' ),
			'model'        => $request->get_param( 'model' ),
			'max_tokens'   => $request->get_param( 'max_tokens' ),
			'temperature'  => $request->get_param( 'temperature' ),
			'priority'     => $request->get_param( 'priority' ),
			'is_active'    => $request->get_param( 'is_active' ),
		], fn( $v ) => null !== $v );
	}
}
