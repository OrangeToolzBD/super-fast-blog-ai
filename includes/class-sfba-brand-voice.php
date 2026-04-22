<?php
/**
 * Brand Voice Learning Engine.
 *
 * Reads the site's existing published posts, extracts statistical writing
 * patterns (sentence length, vocabulary level, tone, POV, contractions,
 * questions, list usage), and stores a "voice profile" that is injected into
 * every AI prompt as a system message — making generated content sound like the
 * site owner, not a generic AI.
 *
 * Analysis is entirely local (no AI calls). The profile is stored in
 * {prefix}sfba_brand_voice and rebuilt on demand.
 *
 * REST endpoints:
 *   GET  /brand-voice         → return stored profile
 *   POST /brand-voice/analyze → run analysis and return result
 *   POST /brand-voice/adjust  → save manual voice overrides
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Brand_Voice {

	/**
	 * @var SFBA_Core
	 */
	private SFBA_Core $core;

	/**
	 * In-memory cache of the stored profile row.
	 *
	 * @var array|null
	 */
	private ?array $profile_cache = null;

	/**
	 * Common English function words excluded from industry-term detection.
	 *
	 * @var string[]
	 */
	private const STOPWORDS = [
		'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
		'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been',
		'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
		'could', 'should', 'may', 'might', 'shall', 'can', 'this', 'that',
		'these', 'those', 'it', 'its', 'as', 'if', 'not', 'so', 'up', 'out',
		'about', 'into', 'than', 'then', 'when', 'where', 'which', 'who', 'how',
		'what', 'there', 'their', 'they', 'them', 'we', 'our', 'us', 'you',
		'your', 'he', 'she', 'his', 'her', 'him', 'also', 'more', 'all', 'one',
		'some', 'any', 'no', 'just', 'very', 'only', 'even', 'both', 'most',
		'other', 'such', 'each', 'much', 'same', 'new', 'get', 'use', 'make',
		'like', 'time', 'way', 'work', 'know', 'take', 'see', 'come', 'need',
		'want', 'give', 'look', 'well', 'back', 'think', 'go', 'good', 'great',
		'first', 'last', 'long', 'little', 'own', 'right', 'big', 'high', 'old',
		'here', 'now', 'still', 'every', 'never', 'always', 'however', 'therefore',
		'because', 'since', 'while', 'through', 'between', 'after', 'before',
		'around', 'without', 'within', 'during', 'against', 'under', 'over',
		'again', 'too', 'then', 'those', 'been', 'those', 'had', 'have',
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
		$this->core->rest_api->register_route( '/brand-voice', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_profile' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
		] );

		$this->core->rest_api->register_route( '/brand-voice/analyze', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_analyze' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'post_count' => [ 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 200 ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/brand-voice/adjust', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_adjust' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
			],
		] );

		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Brand Voice — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Brand Voice', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-brand-voice',
			[ $this, 'render_brand_voice_page' ]
		);
	}

	public function render_brand_voice_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}
		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );
		$summary  = $this->get_voice_summary();
		$enabled  = (bool) $this->core->settings->get( 'brand_voice.enabled', false );
		include SFBA_PLUGIN_DIR . 'includes/admin/page-brand-voice.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** GET /brand-voice */
	public function rest_get_profile( WP_REST_Request $request ): WP_REST_Response {
		$summary = $this->get_voice_summary();
		if ( null === $summary ) {
			return SFBA_Rest_Api::success( [ 'analyzed' => false ] );
		}
		return SFBA_Rest_Api::success( array_merge( [ 'analyzed' => true ], $summary ) );
	}

	/** POST /brand-voice/analyze */
	public function rest_analyze( WP_REST_Request $request ): WP_REST_Response {
		$post_count = (int) $request->get_param( 'post_count' );
		$result     = $this->analyze_existing_content( $post_count );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$this->profile_cache = null; // Bust in-memory cache.

		$summary = $this->get_voice_summary();
		if ( null === $summary ) {
			return SFBA_Rest_Api::error( 'sfba_profile_missing', __( 'Profile was not retrievable after analysis.', 'super-fast-blog-ai' ) );
		}

		return SFBA_Rest_Api::success( $summary );
	}

	/** POST /brand-voice/adjust */
	public function rest_adjust( WP_REST_Request $request ): WP_REST_Response {
		$overrides = $request->get_json_params();
		$result    = $this->adjust_voice( (array) $overrides );
		if ( ! $result ) {
			return SFBA_Rest_Api::error( 'sfba_adjust_failed', __( 'Failed to save voice adjustments.', 'super-fast-blog-ai' ) );
		}
		return SFBA_Rest_Api::success( [ 'saved' => true ] );
	}

	// -------------------------------------------------------------------------
	// Core analysis.
	// -------------------------------------------------------------------------

	/**
	 * Read the last N published posts and build a brand voice profile.
	 *
	 * @param int $post_count Posts to analyze (5–100).
	 * @return bool|WP_Error
	 */
	public function analyze_existing_content( int $post_count = 30 ): bool|WP_Error {
		$post_count = max( 1, min( 200, $post_count ) );

		$posts = get_posts( [
			'post_status'    => 'publish',
			'posts_per_page' => $post_count,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_type'      => 'post',
		] );

		if ( empty( $posts ) ) {
			return new WP_Error(
				'sfba_no_posts',
				__( 'No published posts found to analyze. Publish at least 5 posts before running brand voice analysis.', 'super-fast-blog-ai' )
			);
		}

		$totals = [
			'sentence_lengths' => [],
			'word_lengths'     => [],
			'word_freq'        => [],
			'first_person'     => 0,
			'second_person'    => 0,
			'third_person'     => 0,
			'contractions'     => 0,
			'questions'        => 0,
			'total_words'      => 0,
			'uses_lists'       => 0,
			'sample_post_ids'  => [],
		];

		foreach ( $posts as $post ) {
			$this->analyze_post( $post, $totals );
			if ( count( $totals['sample_post_ids'] ) < 5 ) {
				$totals['sample_post_ids'][] = $post->ID;
			}
		}

		$post_count_actual = count( $posts );

		$avg_sentence_length = ! empty( $totals['sentence_lengths'] )
			? (int) round( array_sum( $totals['sentence_lengths'] ) / count( $totals['sentence_lengths'] ) )
			: 15;

		$avg_word_length = ! empty( $totals['word_lengths'] )
			? round( array_sum( $totals['word_lengths'] ) / count( $totals['word_lengths'] ), 1 )
			: 4.5;

		$vocab_level = match ( true ) {
			$avg_word_length > 6.0 => 5,
			$avg_word_length > 5.5 => 4,
			$avg_word_length > 5.0 => 3,
			$avg_word_length > 4.5 => 2,
			default                => 1,
		};

		$contraction_rate = $totals['total_words'] > 0
			? $totals['contractions'] / $totals['total_words']
			: 0;
		$tone = ( $avg_sentence_length > 20 && $contraction_rate < 0.02 ) ? 'formal' : 'casual';

		$pov_counts = [
			'first'  => $totals['first_person'],
			'second' => $totals['second_person'],
			'third'  => $totals['third_person'],
		];
		arsort( $pov_counts );
		$dominant_pov = (string) array_key_first( $pov_counts );

		$word_freq     = array_filter( $totals['word_freq'], fn( $n ) => $n >= 2 );
		arsort( $word_freq );
		$industry_terms = array_slice( array_keys( $word_freq ), 0, 20 );

		$profile = [
			'avg_sentence_length' => $avg_sentence_length,
			'avg_word_length'     => $avg_word_length,
			'vocab_level'         => $vocab_level,
			'tone'                => $tone,
			'pov'                 => $dominant_pov,
			'pov_counts'          => $pov_counts,
			'contraction_rate'    => round( $contraction_rate, 4 ),
			'uses_questions'      => $totals['questions'] > ( $post_count_actual * 0.5 ),
			'uses_lists'          => $totals['uses_lists'] > ( $post_count_actual * 0.3 ),
			'posts_analyzed'      => $post_count_actual,
			'analyzed_at'         => gmdate( 'Y-m-d H:i:s' ),
		];

		if ( $totals['total_words'] === 0 ) {
			return new WP_Error(
				'sfba_no_content',
				__( 'Published posts found but no analyzable text extracted. Ensure posts have at least 80 characters of body content.', 'super-fast-blog-ai' )
			);
		}

		$stored = $this->store_profile( $profile, $industry_terms, $totals['sample_post_ids'] );
		if ( ! $stored ) {
			return new WP_Error(
				'sfba_store_failed',
				__( 'Analysis complete but the profile could not be saved. Check that the sfba_brand_voice database table exists.', 'super-fast-blog-ai' )
			);
		}

		$this->core->settings->set( 'brand_voice.last_run', gmdate( 'Y-m-d H:i:s' ) );
		$this->core->settings->set( 'brand_voice.post_count', $post_count_actual );

		return true;
	}

	/**
	 * Extract writing-pattern data from a single post into the $totals bucket.
	 *
	 * @param WP_Post $post
	 * @param array   $totals Passed by reference.
	 */
	private function analyze_post( WP_Post $post, array &$totals ): void {
		$raw = $post->post_content;

		if ( preg_match( '/<[uo]l\b/i', $raw ) ) {
			$totals['uses_lists']++;
		}

		$content = html_entity_decode( wp_strip_all_tags( $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/\s+/', ' ', trim( $content ) );

		if ( strlen( $content ) < 80 ) {
			return;
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $sentences as $sentence ) {
			$words_in_sentence = preg_split( '/\s+/', trim( $sentence ), -1, PREG_SPLIT_NO_EMPTY );
			$wc                = count( $words_in_sentence );
			if ( $wc >= 3 && $wc <= 100 ) {
				$totals['sentence_lengths'][] = $wc;
			}
		}

		preg_match_all( '/\b[a-zA-Z]{2,}\b/', $content, $word_matches );
		$words = $word_matches[0];

		foreach ( $words as $word ) {
			$clean = strtolower( $word );
			$len   = strlen( $clean );

			$totals['word_lengths'][] = $len;
			$totals['total_words']++;

			if ( $len > 3 && ! in_array( $clean, self::STOPWORDS, true ) ) {
				$totals['word_freq'][ $clean ] = ( $totals['word_freq'][ $clean ] ?? 0 ) + 1;
			}
		}

		$lower = strtolower( $content );
		$totals['first_person']  += (int) preg_match_all( '/\b(i|we|our|my|mine|us|myself|ourselves)\b/', $lower, $m );
		$totals['second_person'] += (int) preg_match_all( '/\b(you|your|yours|yourself|yourselves)\b/', $lower, $m );
		$totals['third_person']  += (int) preg_match_all( '/\b(he|she|they|his|her|their|it|its|him|them|themselves)\b/', $lower, $m );

		$totals['contractions'] += (int) preg_match_all(
			"/\b\w+n't\b|\b(i'm|you're|we're|they're|it's|i've|we've|you've|i'll|we'll|you'll|i'd|we'd|you'd|that's|there's|here's|who's|what's|let's)\b/i",
			$content,
			$m
		);

		$totals['questions'] += substr_count( $content, '?' );
	}

	/**
	 * Upsert the profile into sfba_brand_voice table.
	 */
	private function store_profile( array $profile, array $industry_terms, array $sample_post_ids ): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'sfba_brand_voice';
		$site_id = (int) get_current_blog_id();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE site_id = %d LIMIT 1", $site_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( '' !== $wpdb->last_error ) {
			return false;
		}

		$data = [
			'site_id'             => $site_id,
			'vocabulary_profile'  => wp_json_encode( $profile ),
			'avg_sentence_length' => $profile['avg_sentence_length'],
			'tone_keywords'       => wp_json_encode( $industry_terms ),
			'style_markers'       => wp_json_encode( [
				'tone'             => $profile['tone'],
				'pov'              => $profile['pov'],
				'vocab_level'      => $profile['vocab_level'],
				'uses_questions'   => $profile['uses_questions'],
				'uses_lists'       => $profile['uses_lists'],
				'contraction_rate' => $profile['contraction_rate'],
			] ),
			'sample_posts'        => wp_json_encode( $sample_post_ids ),
			'updated_at'          => gmdate( 'Y-m-d H:i:s' ),
		];

		$formats = array_fill( 0, count( $data ), '%s' );

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->update( $table, $data, [ 'id' => (int) $existing_id ], $formats, [ '%d' ] );
			return false !== $rows;
		}

		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->insert( $table, $data, $formats );
	}

	// -------------------------------------------------------------------------
	// Profile access.
	// -------------------------------------------------------------------------

	/**
	 * Load and cache the stored profile row from the database.
	 *
	 * @return array|null Null when no analysis has been run yet.
	 */
	private function load_profile(): ?array {
		if ( null !== $this->profile_cache ) {
			return $this->profile_cache;
		}

		global $wpdb;
		$site_id = (int) get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sfba_brand_voice WHERE site_id = %d LIMIT 1",
				$site_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['vocabulary_profile'] = json_decode( $row['vocabulary_profile'], true ) ?? [];
		$row['tone_keywords']      = json_decode( $row['tone_keywords'], true ) ?? [];
		$row['style_markers']      = json_decode( $row['style_markers'], true ) ?? [];
		$row['sample_posts']       = json_decode( $row['sample_posts'], true ) ?? [];

		$this->profile_cache = $row;
		return $row;
	}

	/**
	 * Convert the stored voice profile into a system message for AI prompts.
	 *
	 * Returns an empty string when brand voice is disabled or not yet analyzed.
	 *
	 * @return string System message text to prepend to AI prompts, or ''.
	 */
	public function build_system_prompt(): string {
		if ( ! $this->core->settings->get( 'brand_voice.enabled', false ) ) {
			return '';
		}

		$row = $this->load_profile();
		if ( null === $row ) {
			return '';
		}

		$p         = $row['vocabulary_profile'];
		$markers   = $row['style_markers'];
		$terms     = array_slice( $row['tone_keywords'], 0, 10 );
		$site_name = get_bloginfo( 'name' );
		$overrides = (array) $this->core->settings->get( 'brand_voice.overrides', [] );

		$tone = $overrides['tone'] ?? $markers['tone'] ?? 'casual';
		$pov  = $overrides['pov']  ?? $markers['pov']  ?? 'first';

		$pov_label = match ( $pov ) {
			'first'  => 'first person (use "I" or "we")',
			'second' => 'second person (use "you")',
			default  => 'third person',
		};

		$vocab_desc = match ( (int) ( $markers['vocab_level'] ?? 2 ) ) {
			1       => 'simple, everyday language',
			2       => 'clear, accessible language',
			3       => 'moderately technical language',
			4       => 'advanced, domain-specific language',
			5       => 'highly technical, expert-level language',
			default => 'clear language',
		};

		$avg_len    = (int) ( $p['avg_sentence_length'] ?? 15 );
		$uses_q     = (bool) ( $markers['uses_questions'] ?? false );
		$uses_lists = (bool) ( $markers['uses_lists'] ?? false );

		$prompt  = "You are a content writer for {$site_name}.\n";
		$prompt .= "Always match this writing style:\n";
		$prompt .= "- Tone: {$tone}\n";
		$prompt .= "- Voice: {$pov_label}\n";
		$prompt .= "- Average sentence length: approximately {$avg_len} words\n";
		$prompt .= "- Vocabulary: {$vocab_desc}\n";

		if ( ! empty( $terms ) ) {
			$prompt .= '- Use these industry terms naturally where relevant: ' . implode( ', ', $terms ) . "\n";
		}
		if ( $uses_q ) {
			$prompt .= "- Occasionally use rhetorical questions to engage the reader\n";
		}
		if ( $uses_lists ) {
			$prompt .= "- Use bullet lists or numbered lists where appropriate\n";
		}

		if ( ! empty( $overrides['extra_instructions'] ) ) {
			$prompt .= "- Additional style note: " . $overrides['extra_instructions'] . "\n";
		}

		return trim( $prompt );
	}

	/**
	 * Return a human-readable summary of the learned voice for the settings page.
	 *
	 * @return array|null Null if no analysis has been run.
	 */
	public function get_voice_summary(): ?array {
		$row = $this->load_profile();
		if ( null === $row ) {
			return null;
		}

		$p         = $row['vocabulary_profile'];
		$markers   = $row['style_markers'];
		$terms     = $row['tone_keywords'];
		$overrides = (array) $this->core->settings->get( 'brand_voice.overrides', [] );

		$vocab_labels = [ 1 => 'Simple', 2 => 'Accessible', 3 => 'Moderate', 4 => 'Advanced', 5 => 'Expert' ];
		$vocab_level  = (int) ( $markers['vocab_level'] ?? 2 );

		return [
			'tone'                => ucfirst( $overrides['tone'] ?? $markers['tone'] ?? 'casual' ),
			'pov'                 => ucfirst( $overrides['pov'] ?? $markers['pov'] ?? 'first' ) . ' person',
			'avg_sentence_length' => (int) ( $p['avg_sentence_length'] ?? 15 ),
			'vocab_level'         => $vocab_labels[ $vocab_level ] ?? 'Accessible',
			'vocab_level_num'     => $vocab_level,
			'uses_questions'      => (bool) ( $markers['uses_questions'] ?? false ),
			'uses_lists'          => (bool) ( $markers['uses_lists'] ?? false ),
			'contraction_rate'    => round( (float) ( $markers['contraction_rate'] ?? 0 ) * 100, 1 ),
			'posts_analyzed'      => (int) ( $p['posts_analyzed'] ?? 0 ),
			'analyzed_at'         => $row['updated_at'],
			'industry_terms'      => array_slice( $terms, 0, 15 ),
			'overrides'           => $overrides,
		];
	}

	/**
	 * Merge manual voice overrides with the auto-detected profile.
	 *
	 * Overrides are stored separately so re-analysis doesn't erase them.
	 *
	 * @param array $overrides Keys: tone (formal|casual), pov (first|second|third),
	 *                         sentence_length (int), extra_instructions (string).
	 * @return bool
	 */
	public function adjust_voice( array $overrides ): bool {
		$clean = [];

		if ( isset( $overrides['tone'] ) && in_array( $overrides['tone'], [ 'formal', 'casual' ], true ) ) {
			$clean['tone'] = $overrides['tone'];
		}

		if ( isset( $overrides['pov'] ) && in_array( $overrides['pov'], [ 'first', 'second', 'third' ], true ) ) {
			$clean['pov'] = $overrides['pov'];
		}

		if ( isset( $overrides['sentence_length'] ) ) {
			$clean['sentence_length'] = max( 5, min( 50, (int) $overrides['sentence_length'] ) );
		}

		if ( isset( $overrides['extra_instructions'] ) ) {
			$clean['extra_instructions'] = sanitize_textarea_field( $overrides['extra_instructions'] );
		}

		$current = (array) $this->core->settings->get( 'brand_voice.overrides', [] );
		if ( $current === $clean ) {
			return true; // No change — not an error.
		}

		return $this->core->settings->set( 'brand_voice.overrides', $clean );
	}
}
