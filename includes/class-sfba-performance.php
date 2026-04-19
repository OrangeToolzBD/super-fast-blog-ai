<?php
/**
 * Content Performance Tracker — MODULE 17.
 *
 * Tracks real-world performance of AI-generated content by syncing data from
 * Google Search Console and storing weekly snapshots in sfba_performance.
 *
 * REST surface:
 *   POST /performance/sync          — trigger manual SC sync
 *   GET  /performance/post/{id}     — weekly history for a post
 *   GET  /performance/comparison    — AI vs manual aggregate comparison
 *   GET  /performance/dashboard     — dashboard summary card
 *   POST /performance/seed          — seed mock data (demo/testing)
 *
 * DB table: {prefix}sfba_performance
 *   id, post_id, week_start, impressions, clicks, avg_position, ctr, source, created_at
 *   UNIQUE KEY: (post_id, week_start)
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Performance {

	// -------------------------------------------------------------------------
	// Constants.
	// -------------------------------------------------------------------------

	/** Search Console API endpoint. */
	private const SC_API_BASE = 'https://searchconsole.googleapis.com/v1/sites';

	/** Rows fetched per SC request. */
	private const SC_ROW_LIMIT = 1000;

	/** Weeks of history stored per post. */
	private const HISTORY_WEEKS = 52;

	/** Option key for SC credentials (nested inside SFBA_OPTION_KEY). */
	private const SC_OPTION_KEY = 'search_console';

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
		// Weekly cron sync.
		$loader->add_action( 'sfba_sync_search_console', $this, 'sync' );

		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );

		// REST: manual sync trigger.
		$this->core->rest_api->register_route( '/performance/sync', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_sync' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'weeks' => [ 'type' => 'integer', 'default' => 4, 'minimum' => 1, 'maximum' => 16 ],
				],
			],
		] );

		// REST: single-post weekly history.
		$this->core->rest_api->register_route( '/performance/post/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_post_stats' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id'    => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
					'weeks' => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => self::HISTORY_WEEKS ],
				],
			],
		] );

		// REST: AI vs manual comparison.
		$this->core->rest_api->register_route( '/performance/comparison', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_comparison' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
		] );

		// REST: dashboard summary.
		$this->core->rest_api->register_route( '/performance/dashboard', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_dashboard' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
			],
		] );

		// REST: seed mock data (demo / testing).
		$this->core->rest_api->register_route( '/performance/seed', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_seed' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'weeks' => [ 'type' => 'integer', 'default' => 8, 'minimum' => 1, 'maximum' => 24 ],
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
			__( 'Performance — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Performance', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-performance',
			[ $this, 'render_performance_page' ]
		);
	}

	public function render_performance_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$dashboard    = $this->get_dashboard_data();
		$dashboard    = is_wp_error( $dashboard ) ? [] : $dashboard;
		$comparison   = $this->get_comparison();
		$comparison   = is_wp_error( $comparison ) ? [ 'ai' => [], 'manual' => [], 'has_data' => false ] : $comparison;
		$sc_connected = '' !== (string) $this->core->settings->get( 'search_console.access_token', '' );
		$api_base     = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce        = wp_create_nonce( 'wp_rest' );

		$page_file = SFBA_INCLUDES_DIR . 'admin/page-performance.php';
		if ( file_exists( $page_file ) ) {
			include $page_file;
		} else {
			$this->render_placeholder_page(
				__( 'Performance', 'super-fast-blog-ai' ),
				__( 'Track how your AI-generated content performs in Google Search Console.', 'super-fast-blog-ai' ),
				'sfba-performance'
			);
		}
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** POST /performance/sync */
	public function rest_sync( WP_REST_Request $request ): WP_REST_Response {
		$weeks  = (int) $request->get_param( 'weeks' );
		$result = $this->sync( $weeks );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [
			'posts_updated' => $result,
			'message'       => sprintf(
				/* translators: %d: post count */
				_n( 'Performance data updated for %d post.', 'Performance data updated for %d posts.', $result, 'super-fast-blog-ai' ),
				$result
			),
		] );
	}

	/** GET /performance/post/{id} */
	public function rest_post_stats( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$weeks   = (int) $request->get_param( 'weeks' );
		$result  = $this->get_post_stats( $post_id, $weeks );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** GET /performance/comparison */
	public function rest_comparison( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->get_comparison();

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** GET /performance/dashboard */
	public function rest_dashboard( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->get_dashboard_data();

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /performance/seed */
	public function rest_seed( WP_REST_Request $request ): WP_REST_Response {
		$weeks  = (int) $request->get_param( 'weeks' );
		$seeded = $this->seed_mock_data( $weeks );

		return SFBA_Rest_Api::success( [
			'rows_inserted' => $seeded,
			'message'       => "Seeded mock performance data ({$seeded} rows across {$weeks} weeks).",
		] );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Fetch performance data from Google Search Console and store weekly snapshots.
	 *
	 * @param int $weeks_back How many weeks of history to fetch (default 4).
	 * @return int|WP_Error Number of posts with updated snapshots, or WP_Error.
	 */
	public function sync( int $weeks_back = 4 ): int|WP_Error {
		$sc = $this->get_sc_credentials();

		if ( is_wp_error( $sc ) ) {
			return $sc;
		}

		[ 'token' => $token, 'site_url' => $site_url ] = $sc;

		$permalink_map = $this->build_permalink_map();
		if ( empty( $permalink_map ) ) {
			return new WP_Error( 'sfba_no_posts', __( 'No published posts found to sync.', 'super-fast-blog-ai' ) );
		}

		$posts_updated = 0;
		$weeks_back    = max( 1, min( 16, $weeks_back ) );

		for ( $w = $weeks_back; $w >= 1; $w-- ) {
			$week_start = gmdate( 'Y-m-d', strtotime( "-{$w} weeks monday this week" ) );
			$week_end   = gmdate( 'Y-m-d', strtotime( $week_start . ' +6 days' ) );

			$rows = $this->fetch_sc_rows( $token, $site_url, $week_start, $week_end );

			if ( is_wp_error( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$page_url = $row['keys'][0] ?? '';
				$post_id  = $permalink_map[ trailingslashit( $page_url ) ]
					?? $permalink_map[ untrailingslashit( $page_url ) ]
					?? 0;

				if ( ! $post_id ) {
					continue;
				}

				$this->upsert_snapshot( $post_id, $week_start, [
					'impressions'  => (int) $row['impressions'],
					'clicks'       => (int) $row['clicks'],
					'avg_position' => round( (float) $row['position'], 2 ),
					'ctr'          => round( (float) $row['ctr'], 4 ),
					'source'       => 'search_console',
				] );

				$posts_updated++;
			}
		}

		return $posts_updated;
	}

	/**
	 * Return weekly performance history for a single post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $weeks   Weeks of history to return (default 12, max 52).
	 * @return array|WP_Error
	 */
	public function get_post_stats( int $post_id, int $weeks = 12 ): array|WP_Error {
		if ( $post_id < 1 ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Invalid post ID.', 'super-fast-blog-ai' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
		}

		global $wpdb;

		$weeks = max( 1, min( self::HISTORY_WEEKS, $weeks ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT week_start, impressions, clicks, avg_position, ctr, source
				   FROM {$wpdb->prefix}sfba_performance
				  WHERE post_id = %d
				  ORDER BY week_start DESC
				  LIMIT %d",
				$post_id,
				$weeks
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total_impressions = 0;
		$total_clicks      = 0;
		$sum_position      = 0.0;
		$sum_ctr           = 0.0;
		$count             = count( $rows );

		foreach ( $rows as &$row ) {
			$row['impressions']  = (int) $row['impressions'];
			$row['clicks']       = (int) $row['clicks'];
			$row['avg_position'] = (float) $row['avg_position'];
			$row['ctr']          = (float) $row['ctr'];

			$total_impressions += $row['impressions'];
			$total_clicks      += $row['clicks'];
			$sum_position      += $row['avg_position'];
			$sum_ctr           += $row['ctr'];
		}
		unset( $row );

		return [
			'post_id'         => $post_id,
			'post_title'      => $post->post_title,
			'post_url'        => get_permalink( $post_id ),
			'is_ai_generated' => $this->is_ai_generated( $post_id ),
			'weeks'           => array_reverse( $rows ),
			'totals'          => [
				'impressions' => $total_impressions,
				'clicks'      => $total_clicks,
			],
			'averages'        => [
				'impressions'  => $count > 0 ? round( $total_impressions / $count, 1 ) : 0,
				'clicks'       => $count > 0 ? round( $total_clicks / $count, 1 ) : 0,
				'avg_position' => $count > 0 ? round( $sum_position / $count, 2 ) : 0,
				'ctr'          => $count > 0 ? round( $sum_ctr / $count, 4 ) : 0,
			],
		];
	}

	/**
	 * Compare aggregate performance: AI-generated posts vs. manually written posts.
	 *
	 * @return array|WP_Error
	 */
	public function get_comparison(): array|WP_Error {
		global $wpdb;

		$ai_post_ids = $this->get_ai_post_ids();

		if ( empty( $ai_post_ids ) ) {
			return [
				'ai'       => $this->empty_aggregate(),
				'manual'   => $this->empty_aggregate(),
				'has_data' => false,
			];
		}

		$ids_placeholder = implode( ',', array_map( 'intval', $ai_post_ids ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ai = $wpdb->get_row(
			"SELECT
				COUNT(DISTINCT post_id)                         AS post_count,
				COALESCE(AVG(impressions), 0)                   AS avg_impressions,
				COALESCE(AVG(clicks), 0)                        AS avg_clicks,
				COALESCE(AVG(NULLIF(avg_position, 0)), 0)       AS avg_position,
				COALESCE(AVG(ctr), 0)                           AS avg_ctr,
				COALESCE(SUM(impressions), 0)                   AS total_impressions,
				COALESCE(SUM(clicks), 0)                        AS total_clicks
			  FROM {$wpdb->prefix}sfba_performance
			 WHERE post_id IN ($ids_placeholder)",
			ARRAY_A
		);

		$manual = $wpdb->get_row(
			"SELECT
				COUNT(DISTINCT post_id)                         AS post_count,
				COALESCE(AVG(impressions), 0)                   AS avg_impressions,
				COALESCE(AVG(clicks), 0)                        AS avg_clicks,
				COALESCE(AVG(NULLIF(avg_position, 0)), 0)       AS avg_position,
				COALESCE(AVG(ctr), 0)                           AS avg_ctr,
				COALESCE(SUM(impressions), 0)                   AS total_impressions,
				COALESCE(SUM(clicks), 0)                        AS total_clicks
			  FROM {$wpdb->prefix}sfba_performance
			 WHERE post_id NOT IN ($ids_placeholder)",
			ARRAY_A
		);
		// phpcs:enable

		$has_data = ( (int) ( $ai['post_count'] ?? 0 ) + (int) ( $manual['post_count'] ?? 0 ) ) > 0;

		return [
			'ai'       => $this->format_aggregate( $ai ),
			'manual'   => $this->format_aggregate( $manual ),
			'has_data' => $has_data,
		];
	}

	/**
	 * Return summary data for the admin dashboard performance widget.
	 *
	 * @return array|WP_Error
	 */
	public function get_dashboard_data(): array|WP_Error {
		global $wpdb;

		$has_sc       = ! is_wp_error( $this->get_sc_credentials() );
		$ai_post_ids  = $this->get_ai_post_ids();
		$month_start  = gmdate( 'Y-m-01' );

		$top_posts = [];
		if ( ! empty( $ai_post_ids ) ) {
			$ids_ph = implode( ',', array_map( 'intval', $ai_post_ids ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$top_rows = $wpdb->get_results(
				"SELECT post_id, SUM(impressions) AS impressions, SUM(clicks) AS clicks,
				        MIN(avg_position) AS best_position
				   FROM {$wpdb->prefix}sfba_performance
				  WHERE post_id IN ($ids_ph)
				  GROUP BY post_id
				  ORDER BY clicks DESC
				  LIMIT 5",
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( $top_rows as $row ) {
				$pid = (int) $row['post_id'];
				$top_posts[] = [
					'post_id'       => $pid,
					'post_title'    => get_the_title( $pid ),
					'post_url'      => get_permalink( $pid ),
					'impressions'   => (int) $row['impressions'],
					'clicks'        => (int) $row['clicks'],
					'best_position' => (float) $row['best_position'],
				];
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$month = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(impressions), 0) AS impressions,
				        COALESCE(SUM(clicks), 0)      AS clicks,
				        COUNT(DISTINCT post_id)        AS posts_tracked
				   FROM {$wpdb->prefix}sfba_performance
				  WHERE week_start >= %s",
				$month_start
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$comparison   = $this->get_comparison();
		$headline     = '';
		if ( ! is_wp_error( $comparison ) && $comparison['has_data'] ) {
			$ai_avg   = (int) round( $comparison['ai']['avg_clicks'] );
			$man_avg  = (int) round( $comparison['manual']['avg_clicks'] );
			$headline = ( $ai_avg >= $man_avg )
				? sprintf(
					/* translators: 1: AI avg clicks, 2: manual avg clicks */
					__( 'Your AI posts avg %1$d clicks/week vs %2$d for manual posts.', 'super-fast-blog-ai' ),
					$ai_avg,
					$man_avg
				)
				: sprintf(
					/* translators: 1: manual avg clicks, 2: AI avg clicks */
					__( 'Manual posts avg %1$d clicks/week vs %2$d for AI posts — try improving your prompts.', 'super-fast-blog-ai' ),
					$man_avg,
					$ai_avg
				);
		}

		$tracked_ai_ids = [];
		if ( ! empty( $ai_post_ids ) ) {
			$ids_ph = implode( ',', array_map( 'intval', $ai_post_ids ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tracked_ai_ids = $wpdb->get_col(
				"SELECT DISTINCT post_id FROM {$wpdb->prefix}sfba_performance WHERE post_id IN ($ids_ph)"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$untracked = count( $ai_post_ids ) - count( $tracked_ai_ids );

		return [
			'top_posts'            => $top_posts,
			'this_month'           => [
				'impressions'   => (int) ( $month['impressions'] ?? 0 ),
				'clicks'        => (int) ( $month['clicks'] ?? 0 ),
				'posts_tracked' => (int) ( $month['posts_tracked'] ?? 0 ),
			],
			'comparison_headline'  => $headline,
			'untracked_ai_posts'   => max( 0, $untracked ),
			'has_sc_configured'    => $has_sc,
		];
	}

	/**
	 * Insert or replace mock weekly performance snapshots for all AI-generated posts.
	 *
	 * @param int $weeks Number of weeks of history to generate.
	 * @return int Total rows inserted/replaced.
	 */
	public function seed_mock_data( int $weeks = 8 ): int {
		$ai_post_ids = $this->get_ai_post_ids();

		$manual_ids = get_posts( [
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'post__not_in'   => $ai_post_ids ?: [ 0 ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		] );

		$all_ids = array_merge( $ai_post_ids, $manual_ids );
		if ( empty( $all_ids ) ) {
			$all_ids = [ 1 ];
		}

		$inserted = 0;
		$weeks    = max( 1, min( 24, $weeks ) );

		foreach ( $all_ids as $post_id ) {
			$is_ai = in_array( $post_id, $ai_post_ids, true );

			$base_impressions = $is_ai ? wp_rand( 600, 2400 ) : wp_rand( 300, 1200 );
			$base_clicks      = $is_ai ? wp_rand( 40, 200 )   : wp_rand( 15, 90 );
			$base_position    = $is_ai ? (float) wp_rand( 60, 150 ) / 10 : (float) wp_rand( 100, 250 ) / 10;

			for ( $w = $weeks; $w >= 1; $w-- ) {
				$week_start = gmdate( 'Y-m-d', strtotime( "-{$w} weeks monday this week" ) );

				$variance     = (float) wp_rand( 80, 120 ) / 100;
				$impressions  = (int) round( $base_impressions * $variance );
				$clicks       = max( 0, (int) round( $base_clicks * $variance ) );
				$ctr          = $impressions > 0 ? round( $clicks / $impressions, 4 ) : 0.0;
				$avg_position = round( $base_position * ( (float) wp_rand( 90, 110 ) / 100 ), 2 );

				$ok = $this->upsert_snapshot( $post_id, $week_start, [
					'impressions'  => $impressions,
					'clicks'       => $clicks,
					'avg_position' => $avg_position,
					'ctr'          => $ctr,
					'source'       => 'mock',
				] );

				if ( $ok ) {
					$inserted++;
				}
			}
		}

		return $inserted;
	}

	// -------------------------------------------------------------------------
	// Search Console HTTP helpers.
	// -------------------------------------------------------------------------

	/**
	 * Retrieve Search Console credentials from plugin settings.
	 *
	 * @return array{token: string, site_url: string}|WP_Error
	 */
	private function get_sc_credentials(): array|WP_Error {
		$token    = (string) $this->core->settings->get( self::SC_OPTION_KEY . '.access_token', '' );
		$site_url = (string) $this->core->settings->get( self::SC_OPTION_KEY . '.site_url', '' );

		if ( '' === $token ) {
			return new WP_Error(
				'sfba_sc_not_configured',
				__( 'Google Search Console is not configured. Add your OAuth access token in Super Fast Blog AI → Settings → Search Console.', 'super-fast-blog-ai' )
			);
		}

		if ( '' === $site_url ) {
			$site_url = get_site_url();
		}

		return [ 'token' => $token, 'site_url' => $site_url ];
	}

	/**
	 * Call the Search Console searchAnalytics/query endpoint for one week.
	 *
	 * @param string $token      OAuth access token.
	 * @param string $site_url   Verified site URL.
	 * @param string $date_start YYYY-MM-DD.
	 * @param string $date_end   YYYY-MM-DD.
	 * @return array|WP_Error    Decoded SC rows, or WP_Error.
	 */
	private function fetch_sc_rows( string $token, string $site_url, string $date_start, string $date_end ): array|WP_Error {
		$encoded_site = rawurlencode( $site_url );
		$endpoint     = self::SC_API_BASE . "/{$encoded_site}/searchAnalytics/query";

		$body = wp_json_encode( [
			'startDate'  => $date_start,
			'endDate'    => $date_end,
			'dimensions' => [ 'page' ],
			'rowLimit'   => self::SC_ROW_LIMIT,
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'sfba_sc_api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Search Console API returned HTTP %d.', 'super-fast-blog-ai' ),
					$code
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['rows'] ?? [];
	}

	// -------------------------------------------------------------------------
	// DB helpers.
	// -------------------------------------------------------------------------

	/**
	 * Upsert (INSERT or REPLACE) a weekly performance snapshot.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $week_start ISO date YYYY-MM-DD.
	 * @param array  $metrics    {impressions, clicks, avg_position, ctr, source}.
	 * @return bool
	 */
	private function upsert_snapshot( int $post_id, string $week_start, array $metrics ): bool {
		global $wpdb;

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"REPLACE INTO {$wpdb->prefix}sfba_performance
				 (post_id, week_start, impressions, clicks, avg_position, ctr, source)
				 VALUES (%d, %s, %d, %d, %f, %f, %s)",
				$post_id,
				$week_start,
				(int) ( $metrics['impressions'] ?? 0 ),
				(int) ( $metrics['clicks'] ?? 0 ),
				(float) ( $metrics['avg_position'] ?? 0.0 ),
				(float) ( $metrics['ctr'] ?? 0.0 ),
				$metrics['source'] ?? 'search_console'
			)
		);

		return false !== $result;
	}

	/**
	 * Return all post IDs that have at least one row in sfba_generations.
	 *
	 * @return int[]
	 */
	private function get_ai_post_ids(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->prefix}sfba_generations WHERE post_id > 0 LIMIT 5000"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $ids );
	}

	/**
	 * Check whether a specific post was AI-generated.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	private function is_ai_generated( int $post_id ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}sfba_generations WHERE post_id = %d LIMIT 1",
				$post_id
			)
		);
	}

	/**
	 * Build a permalink → post_id map for all published posts.
	 *
	 * @return array<string, int>
	 */
	private function build_permalink_map(): array {
		$post_ids = get_posts( [
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$map = [];
		foreach ( $post_ids as $id ) {
			$url = get_permalink( $id );
			if ( $url ) {
				$map[ trailingslashit( $url ) ]   = $id;
				$map[ untrailingslashit( $url ) ] = $id;
			}
		}

		return $map;
	}

	/**
	 * Format a raw DB aggregate row for API output.
	 *
	 * @param array|null $row
	 * @return array
	 */
	private function format_aggregate( ?array $row ): array {
		if ( ! $row ) {
			return $this->empty_aggregate();
		}

		return [
			'post_count'        => (int) $row['post_count'],
			'avg_impressions'   => round( (float) $row['avg_impressions'], 1 ),
			'avg_clicks'        => round( (float) $row['avg_clicks'], 1 ),
			'avg_position'      => round( (float) $row['avg_position'], 2 ),
			'avg_ctr'           => round( (float) $row['avg_ctr'], 4 ),
			'total_impressions' => (int) $row['total_impressions'],
			'total_clicks'      => (int) $row['total_clicks'],
		];
	}

	/**
	 * Return a zeroed aggregate structure.
	 *
	 * @return array
	 */
	private function empty_aggregate(): array {
		return [
			'post_count'        => 0,
			'avg_impressions'   => 0.0,
			'avg_clicks'        => 0.0,
			'avg_position'      => 0.0,
			'avg_ctr'           => 0.0,
			'total_impressions' => 0,
			'total_clicks'      => 0,
		];
	}

	// -------------------------------------------------------------------------
	// Fallback page renderer.
	// -------------------------------------------------------------------------

	/**
	 * Render a simple placeholder page when the full template hasn't been built yet.
	 *
	 * @param string $title
	 * @param string $description
	 * @param string $page_slug
	 */
	private function render_placeholder_page( string $title, string $description, string $page_slug ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
			<p>
				<?php esc_html_e( 'Full UI coming in Phase 18. Use the REST API endpoints in the meantime.', 'super-fast-blog-ai' ); ?>
				<br>
				<code>POST /wp-json/super-fast-blog-ai/v1/performance/sync</code><br>
				<code>GET  /wp-json/super-fast-blog-ai/v1/performance/dashboard</code>
			</p>
		</div>
		<?php
	}
}
