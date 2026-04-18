<?php
/**
 * Internal Linking Engine — MODULE 6.
 *
 * Scans published posts, builds a keyword index, and suggests contextually
 * relevant internal links while content is being written or generated.
 *
 * Architecture:
 *   Index layer  (sfba_internal_links table)
 *     build_content_index()  — crawls all published posts, extracts
 *     title words + tags + category slugs as match keywords. Idempotent.
 *     rebuild_content_index() — truncates + rebuilds from scratch.
 *
 *   Suggestion layer
 *     suggest_links($content, $post_id) — tokenise content, match against
 *       index keywords, rank by match_count / already_linked / recency.
 *     suggest_reverse_links($post_id)   — find posts that mention this post's
 *       keywords but don't yet link to it.
 *
 *   Insertion layer
 *     auto_insert_link($post_id, $anchor, $url) — wrap first unlinked
 *       occurrence of $anchor in post content with <a> tag, persist.
 *
 * REST routes:
 *   POST /internal-links/index          — trigger index build
 *   POST /internal-links/suggest        — get suggestions for content
 *   GET  /internal-links/reverse/{id}   — get reverse-link opportunities
 *   POST /internal-links/insert         — auto-insert a link into a post
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFBA_Internal_Linker
 */
class SFBA_Internal_Linker {

	// -------------------------------------------------------------------------
	// Constants.
	// -------------------------------------------------------------------------

	private const CACHE_TTL      = 900; // 15 minutes
	private const MAX_SUGGESTIONS = 10;
	private const MIN_SCORE       = 1;
	private const MIN_WORD_LEN    = 4;
	private const PHRASE_BONUS    = 2;

	private const STOP_WORDS = [
		'about', 'after', 'also', 'back', 'been', 'before', 'being',
		'between', 'both', 'came', 'come', 'could', 'does', 'each',
		'even', 'from', 'have', 'here', 'high', 'into', 'just',
		'like', 'made', 'make', 'many', 'more', 'most', 'much',
		'must', 'never', 'only', 'other', 'over', 'same', 'such',
		'than', 'that', 'their', 'them', 'then', 'there', 'these',
		'they', 'this', 'those', 'time', 'very', 'was', 'well',
		'were', 'what', 'when', 'where', 'which', 'while', 'will',
		'with', 'would', 'your',
	];

	private const WEIGHT = [
		'title'    => 3,
		'tag'      => 2,
		'category' => 1,
	];

	/** @var SFBA_Core */
	private SFBA_Core $core;

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
		$loader->add_action( 'transition_post_status', $this, 'maybe_invalidate_cache', 10, 3 );
		$loader->add_action( 'transition_post_status', $this, 'maybe_index_post', 10, 3 );

		$this->core->rest_api->register_route( '/internal-links/index', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_build_index' ],
				'permission_callback' => [ $this->core->rest_api, 'require_admin' ],
				'args'                => [
					'rebuild' => [ 'type' => 'boolean', 'required' => false, 'default' => false ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/internal-links/suggest', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_suggest' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'content'     => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'wp_kses_post' ],
					'post_id'     => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
					'max_results' => [ 'type' => 'integer', 'required' => false, 'default' => 5, 'minimum' => 1, 'maximum' => self::MAX_SUGGESTIONS ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/internal-links/reverse/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_reverse' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id'          => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
					'max_results' => [ 'type' => 'integer', 'required' => false, 'default' => 5, 'minimum' => 1, 'maximum' => self::MAX_SUGGESTIONS ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/internal-links/insert', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_insert' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'post_id' => [ 'type' => 'integer', 'required' => true,  'minimum' => 1 ],
					'anchor'  => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
					'url'     => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'esc_url_raw' ],
					'title'   => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			'Internal Links — Super Fast Blog AI',
			'Internal Links',
			'manage_options',
			'sfba-internal-links',
			[ $this, 'render_links_page' ]
		);
	}

	public function render_links_page(): void {
		global $wpdb;

		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );

		// Indexed rows (status = 'indexed', target_post_id = 0).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$index_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sfba_internal_links WHERE target_post_id = 0 AND status = 'indexed'"
		);

		// Total accepted / inserted links.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$accepted_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sfba_internal_links WHERE status = 'accepted'"
		);

		// Total published posts + pages.
		$published_count = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;

		$last_built   = get_transient( 'sfba_link_index_built' );
		$index_built  = ! empty( $last_built ) && $index_count > 0;
		$recent_posts = get_posts( [
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-internal-links.php';
	}

	// -------------------------------------------------------------------------
	// Hook callbacks.
	// -------------------------------------------------------------------------

	/**
	 * Invalidate suggestion cache when a post is published or unpublished.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function maybe_invalidate_cache( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		delete_transient( $this->suggestion_transient_key( $post->ID ) );
		delete_transient( 'sfba_link_index_built' );
	}

	/**
	 * Auto-index a post when it transitions to 'publish'.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function maybe_index_post( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'publish' !== $new_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->indexable_post_types(), true ) ) {
			return;
		}
		$this->index_post( $post );
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** POST /internal-links/index */
	public function rest_build_index( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$rebuild = (bool) $request->get_param( 'rebuild' );
		$count   = $rebuild
			? $this->rebuild_content_index()
			: $this->build_content_index();

		// Return fresh totals so the front-end can update in place.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_indexed = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sfba_internal_links WHERE target_post_id = 0 AND status = 'indexed'"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_accepted = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sfba_internal_links WHERE status = 'accepted'"
		);
		$published_count = (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish;
		$last_built      = get_transient( 'sfba_link_index_built' );

		return SFBA_Rest_Api::success( [
			'indexed'         => $count,
			'rebuilt'         => $rebuild,
			'total_indexed'   => $total_indexed,
			'total_accepted'  => $total_accepted,
			'published_count' => $published_count,
			'last_built'      => $last_built ?: current_time( 'mysql' ),
			'coverage_pct'    => $published_count > 0 ? min( 100, round( ( $total_indexed / $published_count ) * 100 ) ) : 0,
		] );
	}

	/** POST /internal-links/suggest */
	public function rest_suggest( WP_REST_Request $request ): WP_REST_Response {
		$content = (string) $request->get_param( 'content' );
		$post_id = (int)    $request->get_param( 'post_id' );

		// If no content provided but a post_id is, load the post content.
		if ( '' === trim( $content ) && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$content = $post->post_content . ' ' . $post->post_title;
			}
		}

		if ( '' === trim( $content ) ) {
			return SFBA_Rest_Api::error( 'sfba_invalid_input', __( 'Please select a post or provide content.', 'super-fast-blog-ai' ), 400 );
		}

		$result = $this->suggest_links(
			$content,
			$post_id,
			(int) $request->get_param( 'max_results' )
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** GET /internal-links/reverse/{id} */
	public function rest_reverse( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$post_id = (int) $request->get_param( 'id' );

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return SFBA_Rest_Api::error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), 404 );
		}

		// ── 1. Accepted / already-inserted links pointing TO this post ──────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_post_id, anchor_text, target_url, created_at
				 FROM {$wpdb->prefix}sfba_internal_links
				 WHERE target_post_id = %d
				   AND status = 'accepted'
				 ORDER BY created_at DESC",
				$post_id
			),
			ARRAY_A
		);

		$links = [];
		foreach ( (array) $rows as $row ) {
			$src_id    = (int) $row['source_post_id'];
			$src_post  = get_post( $src_id );
			$links[]   = [
				'source_post_id'    => $src_id,
				'source_post_title' => $src_post instanceof WP_Post
					? $src_post->post_title
					/* translators: %d: post ID */
					: sprintf( __( 'Post #%d', 'super-fast-blog-ai' ), $src_id ),
				'source_edit_url'   => get_edit_post_link( $src_id, 'raw' ),
				'anchor_text'       => $row['anchor_text'],
				'target_url'        => $row['target_url'],
				'created_at'        => $row['created_at'],
			];
		}

		// ── 2. Opportunities — posts that mention this post's keywords but ───────
		//       don't yet link to it. These are candidates to add the link.
		$opps_result   = $this->suggest_reverse_links( $post_id, (int) $request->get_param( 'max_results' ) );
		$opportunities = is_wp_error( $opps_result ) ? [] : ( $opps_result['suggestions'] ?? [] );

		return SFBA_Rest_Api::success( [
			'target'        => [
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'url'        => (string) get_permalink( $post_id ),
			],
			'links'         => $links,
			'opportunities' => $opportunities,
		] );
	}

	/** POST /internal-links/insert */
	public function rest_insert( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->auto_insert_link(
			(int)    $request->get_param( 'post_id' ),
			(string) $request->get_param( 'anchor' ),
			(string) $request->get_param( 'url' ),
			(string) $request->get_param( 'title' )
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Build the internal link index for all published posts. Idempotent.
	 *
	 * @param string[] $post_types
	 * @return int Number of new posts indexed.
	 */
	public function build_content_index( array $post_types = [] ): int {
		if ( empty( $post_types ) ) {
			$post_types = $this->indexable_post_types();
		}

		$posts = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$indexed = 0;
		foreach ( $posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( $post instanceof WP_Post && $this->index_post( $post, false ) ) {
				$indexed++;
			}
		}

		set_transient( 'sfba_link_index_built', current_time( 'mysql' ), DAY_IN_SECONDS );

		return $indexed;
	}

	/**
	 * Truncate the index and rebuild from scratch.
	 *
	 * @return int Total posts indexed.
	 */
	public function rebuild_content_index(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sfba_internal_links" );

		delete_transient( 'sfba_link_index_built' );

		return $this->build_content_index();
	}

	/**
	 * Suggest relevant internal links for a block of content.
	 *
	 * @param string $content     HTML content being written.
	 * @param int    $post_id     ID of the post being edited (excluded from results).
	 * @param int    $max_results Maximum suggestions to return.
	 * @return array|WP_Error
	 */
	public function suggest_links( string $content, int $post_id = 0, int $max_results = 5 ): array|WP_Error {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Content cannot be empty.', 'super-fast-blog-ai' ) );
		}

		$max_results = max( 1, min( self::MAX_SUGGESTIONS, $max_results ) );

		$cache_key = $this->suggestion_transient_key( $post_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! get_transient( 'sfba_link_index_built' ) ) {
			$this->build_content_index();
		}

		$index = $this->load_index( $post_id );
		if ( empty( $index ) ) {
			return [
				'suggestions'    => [],
				'index_size'     => 0,
				'tokens_checked' => 0,
			];
		}

		$plain         = $this->strip_to_plain( $content );
		$tokens        = $this->extract_tokens( $plain );
		$existing_urls = $this->extract_existing_urls( $content );

		$scored = [];
		foreach ( $index as $row ) {
			if ( in_array( $row['url'], $existing_urls, true ) ) {
				continue;
			}

			[ 'score' => $score, 'matched' => $matched ] = $this->score_index_row( $row, $tokens );

			if ( $score >= self::MIN_SCORE ) {
				$anchor  = $this->best_anchor( $matched, $plain, $row['post_title'] );
				$excerpt = $this->context_excerpt( $plain, $anchor );

				$scored[] = [
					'post_id'          => (int) $row['post_id'],
					'post_title'       => $row['post_title'],
					'url'              => $row['url'],
					'anchor'           => $anchor,
					'match_score'      => round( $score, 2 ),
					'matched_keywords' => $matched,
					'context_excerpt'  => $excerpt,
				];
			}
		}

		usort( $scored, function ( $a, $b ) {
			if ( $a['match_score'] !== $b['match_score'] ) {
				return $b['match_score'] <=> $a['match_score'];
			}
			return $b['post_id'] <=> $a['post_id'];
		} );

		$result = [
			'suggestions'    => array_slice( $scored, 0, $max_results ),
			'index_size'     => count( $index ),
			'tokens_checked' => count( $tokens ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Suggest posts that SHOULD link to the given post but currently don't.
	 *
	 * @param int $post_id     The target post (we want links pointing TO this).
	 * @param int $max_results Maximum results to return.
	 * @return array|WP_Error
	 */
	public function suggest_reverse_links( int $post_id, int $max_results = 5 ): array|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
		}

		$max_results     = max( 1, min( self::MAX_SUGGESTIONS, $max_results ) );
		$target_url      = get_permalink( $post_id );
		$target_row      = $this->get_index_row( $post_id );

		if ( null === $target_row ) {
			$this->index_post( $post );
			$target_row = $this->get_index_row( $post_id );
		}

		$target_keywords = $target_row ? $this->decode_keywords( $target_row['keywords_json'] ) : $this->extract_post_keywords( $post );

		if ( empty( $target_keywords ) ) {
			return new WP_Error( 'sfba_no_keywords', __( 'No keywords could be extracted for this post.', 'super-fast-blog-ai' ) );
		}

		$other_posts = get_posts( [
			'post_type'      => $this->indexable_post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'exclude'        => [ $post_id ],
			'no_found_rows'  => true,
		] );

		$opportunities = [];

		foreach ( $other_posts as $other_post ) {
			$content  = $other_post->post_content;
			$existing = $this->extract_existing_urls( $content );

			if ( in_array( $target_url, $existing, true ) ) {
				continue;
			}

			$plain   = $this->strip_to_plain( $content );
			$tokens  = $this->extract_tokens( $plain );
			$matched = [];
			$score   = 0.0;

			foreach ( $target_keywords as $kw ) {
				if ( $this->token_contains( $tokens, $kw ) ) {
					$word_count = str_word_count( $kw );
					$weight     = $word_count > 1 ? self::PHRASE_BONUS : 1;
					$score     += $weight;
					$matched[]  = $kw;
				}
			}

			if ( $score >= self::MIN_SCORE ) {
				$anchor  = $this->best_anchor( $matched, $plain, $post->post_title );
				$excerpt = $this->context_excerpt( $plain, $anchor );

				$opportunities[] = [
					'post_id'          => (int) $other_post->ID,
					'post_title'       => $other_post->post_title,
					'edit_url'         => get_edit_post_link( $other_post->ID, 'raw' ),
					'match_score'      => round( $score, 2 ),
					'matched_keywords' => $matched,
					'context_excerpt'  => $excerpt,
					'anchor'           => $anchor,
				];
			}
		}

		usort( $opportunities, fn( $a, $b ) => $b['match_score'] <=> $a['match_score'] );

		return [
			'target' => [
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'url'        => (string) $target_url,
			],
			'suggestions' => array_slice( $opportunities, 0, $max_results ),
		];
	}

	/**
	 * Insert a link into a post's content without overwriting existing links.
	 *
	 * @param int    $post_id
	 * @param string $anchor
	 * @param string $url
	 * @param string $title
	 * @return array|WP_Error
	 */
	public function auto_insert_link( int $post_id, string $anchor, string $url, string $title = '' ): array|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'sfba_forbidden', __( 'You do not have permission to edit this post.', 'super-fast-blog-ai' ), [ 'status' => 403 ] );
		}

		$anchor = sanitize_text_field( $anchor );
		$url    = esc_url_raw( $url );
		$title  = sanitize_text_field( $title );

		if ( '' === $anchor ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Anchor text cannot be empty.', 'super-fast-blog-ai' ) );
		}
		if ( '' === $url ) {
			return new WP_Error( 'sfba_invalid_input', __( 'URL cannot be empty.', 'super-fast-blog-ai' ) );
		}

		$site_host = parse_url( get_site_url(), PHP_URL_HOST );
		$url_host  = parse_url( $url, PHP_URL_HOST );
		if ( null !== $url_host && $url_host !== $site_host ) {
			return new WP_Error(
				'sfba_external_url',
				__( 'auto_insert_link only supports internal URLs.', 'super-fast-blog-ai' )
			);
		}

		$content = $post->post_content;

		if ( false === mb_stripos( $content, $anchor ) ) {
			return [
				'post_id'  => $post_id,
				'anchor'   => $anchor,
				'url'      => $url,
				'inserted' => false,
				'message'  => __( 'Anchor text not found in post content.', 'super-fast-blog-ai' ),
			];
		}

		$link_attrs = sprintf( 'href="%s"', esc_url( $url ) );
		if ( '' !== $title ) {
			$link_attrs .= sprintf( ' title="%s"', esc_attr( $title ) );
		}
		$link = '<a ' . $link_attrs . '>' . esc_html( $anchor ) . '</a>';

		$new_content = $this->insert_link_safely( $content, $anchor, $link );

		if ( $new_content === $content ) {
			return [
				'post_id'  => $post_id,
				'anchor'   => $anchor,
				'url'      => $url,
				'inserted' => false,
				/* translators: %s: anchor text */
				'message'  => sprintf( __( '"%s" was found only inside a heading or existing link — cannot insert there. Try a different suggestion.', 'super-fast-blog-ai' ), $anchor ),
			];
		}

		$update_result = wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $new_content,
		], true );

		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		$this->record_inserted_link( $post_id, $anchor, $url );
		delete_transient( $this->suggestion_transient_key( $post_id ) );

		return [
			'post_id'  => $post_id,
			'anchor'   => $anchor,
			'url'      => $url,
			'inserted' => true,
			'message'  => __( 'Link inserted successfully.', 'super-fast-blog-ai' ),
		];
	}

	// -------------------------------------------------------------------------
	// Index management.
	// -------------------------------------------------------------------------

	/**
	 * Write (or update) one post's index row in sfba_internal_links.
	 *
	 * @param WP_Post $post
	 * @param bool    $overwrite
	 * @return bool True if a new row was inserted.
	 */
	private function index_post( WP_Post $post, bool $overwrite = true ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'sfba_internal_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE source_post_id = %d AND target_post_id = 0",
			$post->ID
		) );

		if ( $exists && ! $overwrite ) {
			return false;
		}

		$keywords      = $this->extract_post_keywords( $post );
		$keywords_json = wp_json_encode( $keywords );
		$url           = get_permalink( $post->ID );
		$plain_excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

		$data = [
			'source_post_id' => $post->ID,
			'target_post_id' => 0,
			'anchor_text'    => $post->post_title,
			'target_url'     => $url,
			'context'        => $keywords_json,
			'status'         => 'indexed',
			'created_at'     => current_time( 'mysql', true ),
		];
		$formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ];

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				$data,
				[ 'source_post_id' => $post->ID, 'target_post_id' => 0 ],
				$formats,
				[ '%d', '%d' ]
			);
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $data, $formats );
		return (bool) $wpdb->insert_id;
	}

	/**
	 * Load all index rows except the given post ID.
	 *
	 * @param int $exclude_post_id
	 * @return array
	 */
	private function load_index( int $exclude_post_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'sfba_internal_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT source_post_id, anchor_text, target_url, context, created_at
			 FROM {$table}
			 WHERE target_post_id = 0
			   AND status = 'indexed'
			   AND source_post_id != %d
			 ORDER BY created_at DESC",
			$exclude_post_id
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return [];
		}

		return array_map( function ( $row ) {
			return [
				'post_id'       => (int) $row['source_post_id'],
				'post_title'    => $row['anchor_text'],
				'url'           => $row['target_url'],
				'keywords_json' => $row['context'],
				'date'          => $row['created_at'],
			];
		}, $rows );
	}

	/**
	 * Load a single index row for a post.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	private function get_index_row( int $post_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'sfba_internal_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT source_post_id, anchor_text, target_url, context
			 FROM {$table}
			 WHERE source_post_id = %d AND target_post_id = 0",
			$post_id
		), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return [
			'post_id'       => (int) $row['source_post_id'],
			'post_title'    => $row['anchor_text'],
			'url'           => $row['target_url'],
			'keywords_json' => $row['context'],
		];
	}

	/**
	 * Record a successfully inserted link in sfba_internal_links.
	 */
	private function record_inserted_link( int $source_post_id, string $anchor, string $url ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'sfba_internal_links',
			[
				'source_post_id' => $source_post_id,
				'target_post_id' => url_to_postid( $url ) ?: 0,
				'anchor_text'    => $anchor,
				'target_url'     => $url,
				'context'        => '',
				'status'         => 'accepted',
				'created_at'     => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	// -------------------------------------------------------------------------
	// Keyword extraction.
	// -------------------------------------------------------------------------

	/**
	 * Extract keyword strings for a post.
	 *
	 * @param WP_Post $post
	 * @return string[]
	 */
	private function extract_post_keywords( WP_Post $post ): array {
		$keywords = [];

		$title_clean = mb_strtolower( $post->post_title );
		$title_clean = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title_clean );
		$title_clean = trim( preg_replace( '/\s+/', ' ', $title_clean ) );

		if ( '' !== $title_clean ) {
			$keywords[] = $title_clean;

			foreach ( explode( ' ', $title_clean ) as $word ) {
				if ( mb_strlen( $word ) >= self::MIN_WORD_LEN && ! in_array( $word, self::STOP_WORDS, true ) ) {
					$keywords[] = $word;
				}
			}

			$title_words = explode( ' ', $title_clean );
			for ( $i = 0, $len = count( $title_words ) - 1; $i < $len; $i++ ) {
				$phrase = $title_words[ $i ] . ' ' . $title_words[ $i + 1 ];
				if ( mb_strlen( str_replace( ' ', '', $phrase ) ) >= self::MIN_WORD_LEN * 2 ) {
					$keywords[] = $phrase;
				}
			}
		}

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'slugs' ] );
		foreach ( (array) $tags as $slug ) {
			$readable = str_replace( '-', ' ', (string) $slug );
			if ( mb_strlen( $readable ) >= self::MIN_WORD_LEN ) {
				$keywords[] = mb_strtolower( $readable );
			}
		}

		$cats = wp_get_post_categories( $post->ID, [ 'fields' => 'slugs' ] );
		foreach ( (array) $cats as $slug ) {
			$readable = str_replace( '-', ' ', (string) $slug );
			if ( mb_strlen( $readable ) >= self::MIN_WORD_LEN && 'uncategorized' !== $slug ) {
				$keywords[] = mb_strtolower( $readable );
			}
		}

		return array_values( array_unique( $keywords ) );
	}

	/**
	 * Decode a keywords_json column back to a string array.
	 *
	 * @param string $json
	 * @return string[]
	 */
	private function decode_keywords( string $json ): array {
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	// -------------------------------------------------------------------------
	// Scoring.
	// -------------------------------------------------------------------------

	/**
	 * Score an index row against content tokens.
	 *
	 * @param array    $row
	 * @param string[] $tokens
	 * @return array{score: float, matched: string[]}
	 */
	private function score_index_row( array $row, array $tokens ): array {
		$keywords = $this->decode_keywords( $row['keywords_json'] );
		$score    = 0.0;
		$matched  = [];

		foreach ( $keywords as $kw ) {
			if ( ! $this->token_contains( $tokens, $kw ) ) {
				continue;
			}

			$word_count = str_word_count( $kw );
			$is_phrase  = $word_count > 1;
			$weight     = $is_phrase ? self::PHRASE_BONUS : 1;
			$score     += $weight;
			$matched[]  = $kw;
		}

		return [ 'score' => $score, 'matched' => array_values( array_unique( $matched ) ) ];
	}

	/**
	 * Check whether $needle exists in the token list (case-insensitive).
	 *
	 * @param string[] $tokens
	 * @param string   $needle
	 */
	private function token_contains( array $tokens, string $needle ): bool {
		$needle_lc = mb_strtolower( trim( $needle ) );
		foreach ( $tokens as $token ) {
			if ( mb_strtolower( $token ) === $needle_lc ) {
				return true;
			}
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// Anchor and excerpt helpers.
	// -------------------------------------------------------------------------

	/**
	 * Choose the best anchor text from matched keywords.
	 *
	 * @param string[] $matched
	 * @param string   $plain
	 * @param string   $fallback
	 * @return string
	 */
	private function best_anchor( array $matched, string $plain, string $fallback ): string {
		if ( empty( $matched ) ) {
			return $fallback;
		}

		$plain_lc = mb_strtolower( $plain );

		usort( $matched, fn( $a, $b ) => str_word_count( $b ) <=> str_word_count( $a ) );

		foreach ( $matched as $kw ) {
			$pos = mb_strpos( $plain_lc, mb_strtolower( $kw ) );
			if ( false !== $pos ) {
				return mb_substr( $plain, $pos, mb_strlen( $kw ) );
			}
		}

		return $fallback;
	}

	/**
	 * Return a short excerpt showing the anchor in context.
	 *
	 * @param string $plain
	 * @param string $anchor
	 * @return string
	 */
	private function context_excerpt( string $plain, string $anchor ): string {
		if ( '' === $anchor ) {
			return '';
		}

		$pos = mb_stripos( $plain, $anchor );
		if ( false === $pos ) {
			return '';
		}

		$start   = max( 0, $pos - 60 );
		$end     = min( mb_strlen( $plain ), $pos + mb_strlen( $anchor ) + 60 );
		$snippet = mb_substr( $plain, $start, $end - $start );

		if ( $start > 0 ) {
			$snippet = '…' . ltrim( mb_substr( $snippet, mb_strpos( $snippet, ' ' ) ?: 0 ) );
		}
		if ( $end < mb_strlen( $plain ) ) {
			$snippet = rtrim( mb_substr( $snippet, 0, mb_strrpos( $snippet, ' ' ) ?: mb_strlen( $snippet ) ) ) . '…';
		}

		return $snippet;
	}

	// -------------------------------------------------------------------------
	// Content tokeniser.
	// -------------------------------------------------------------------------

	/**
	 * Extract search tokens from plain text: single words + 2-word + 3-word phrases.
	 *
	 * @param string $plain
	 * @return string[]
	 */
	private function extract_tokens( string $plain ): array {
		$plain_lc = mb_strtolower( $plain );
		preg_match_all( '/\b[a-z\'-]+\b/u', $plain_lc, $raw_words );
		$words = array_values( array_filter(
			$raw_words[0] ?? [],
			fn( $w ) => mb_strlen( $w ) >= self::MIN_WORD_LEN && ! in_array( $w, self::STOP_WORDS, true )
		) );

		$tokens = $words;

		for ( $i = 0, $len = count( $words ) - 1; $i < $len; $i++ ) {
			$tokens[] = $words[ $i ] . ' ' . $words[ $i + 1 ];
		}

		for ( $i = 0, $len = count( $words ) - 2; $i < $len; $i++ ) {
			$tokens[] = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
		}

		return $tokens;
	}

	/**
	 * Strip HTML to plain text, preserving word boundaries.
	 */
	private function strip_to_plain( string $html ): string {
		$spaced = preg_replace( '/<\/(p|li|h[1-6]|blockquote|div)>/i', ' ', $html );
		$plain  = wp_strip_all_tags( $spaced );
		$plain  = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $plain ) );
	}

	/**
	 * Extract all href values from <a> tags in HTML content.
	 *
	 * @return string[]
	 */
	private function extract_existing_urls( string $html ): array {
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches );
		return array_map( 'esc_url_raw', $matches[1] ?? [] );
	}

	// -------------------------------------------------------------------------
	// Link insertion helpers.
	// -------------------------------------------------------------------------

	/**
	 * Insert $link_html around the first occurrence of $anchor in $content
	 * that is NOT already inside an <a> or heading tag.
	 *
	 * Uses a proper tag-state-machine: splits content into HTML tags and text
	 * nodes, tracks open/close context, inserts only into safe text nodes.
	 *
	 * @param string $content
	 * @param string $anchor
	 * @param string $link_html
	 * @return string Modified content, or original if anchor wasn't safely insertable.
	 */
	private function insert_link_safely( string $content, string $anchor, string $link_html ): string {
		// Build word-boundary-aware pattern (case-insensitive).
		$anchor_re = '#(?<![a-zA-Z0-9])' . preg_quote( $anchor, '#' ) . '(?![a-zA-Z0-9])#iu';

		// Split into alternating [text, tag, text, tag, …] parts.
		$parts = preg_split( '/(<[^>]+>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $content;
		}

		$inside_link    = false;
		$inside_heading = false;
		$inserted       = false;
		$result         = '';

		foreach ( $parts as $part ) {
			// HTML opening tag?
			if ( preg_match( '/^<([a-z][a-z0-9]*)[\s\/>]/i', $part, $m ) ) {
				$tag = mb_strtolower( $m[1] );
				if ( 'a' === $tag )                        { $inside_link    = true; }
				if ( preg_match( '/^h[1-6]$/', $tag ) )   { $inside_heading = true; }
				$result .= $part;
				continue;
			}

			// HTML closing tag?
			if ( preg_match( '/^<\/([a-z][a-z0-9]*)>/i', $part, $m ) ) {
				$tag = mb_strtolower( $m[1] );
				if ( 'a' === $tag )                        { $inside_link    = false; }
				if ( preg_match( '/^h[1-6]$/', $tag ) )   { $inside_heading = false; }
				$result .= $part;
				continue;
			}

			// Pure text node — safe to insert if not inside a link/heading and not yet inserted.
			if ( ! $inserted && ! $inside_link && ! $inside_heading && '' !== trim( $part ) ) {
				$new_part = preg_replace_callback(
					$anchor_re,
					function ( array $matches ) use ( $link_html, $anchor, &$inserted ): string {
						if ( $inserted ) {
							return $matches[0]; // only insert once.
						}
						$inserted   = true;
						$actual     = $matches[0]; // preserve original casing from content.
						$final_link = str_ireplace( esc_html( $anchor ), esc_html( $actual ), $link_html );
						return $final_link;
					},
					$part
				);
				$result .= $new_part ?? $part;
				continue;
			}

			$result .= $part;
		}

		return $inserted ? $result : $content;
	}

	// -------------------------------------------------------------------------
	// Utility.
	// -------------------------------------------------------------------------

	/**
	 * Return the post types that should be included in the index.
	 *
	 * @return string[]
	 */
	private function indexable_post_types(): array {
		return (array) apply_filters( 'sfba_indexable_post_types', [ 'post', 'page' ] );
	}

	/**
	 * Build the transient key for suggestion cache.
	 */
	private function suggestion_transient_key( int $post_id ): string {
		return 'sfba_links_' . $post_id;
	}
}
