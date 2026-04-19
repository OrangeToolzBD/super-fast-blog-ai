<?php
/**
 * AI Content Calendar — MODULE 8.
 *
 * Analyses existing site keyword coverage, identifies topic gaps, and suggests
 * a monthly editorial calendar with AI-generated topics, target keywords,
 * difficulty scores, and optimal publish dates.
 *
 * Public API:
 *   suggest_topics( $count )            → int|WP_Error  (rows inserted)
 *   get_calendar_view( $month, $year )  → array|WP_Error
 *   start_writing( $calendar_id )       → array|WP_Error
 *   add_entry( $data )                  → int|WP_Error
 *   update_entry( $id, $data )          → bool|WP_Error
 *   link_post( $entry_id, $post_id )    → bool
 *
 * REST surface:
 *   POST   /calendar/suggest          — AI topic suggestions
 *   GET    /calendar                  — month view (?month=&year=)
 *   POST   /calendar/start-writing    — convert idea → draft post
 *   POST   /calendar/entry            — manual entry
 *   POST   /calendar/entry/{id}       — update entry
 *   DELETE /calendar/entry/{id}       — delete entry
 *   POST   /calendar/link-post        — link published post to entry
 *
 * DB table: {prefix}sfba_content_calendar
 *   id, title, target_keyword, keyword_volume, keyword_difficulty,
 *   suggested_date, status, post_id, source, created_at
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFBA_Content_Calendar
 */
class SFBA_Content_Calendar {

	// -------------------------------------------------------------------------
	// Constants.
	// -------------------------------------------------------------------------

	/** @var string[] */
	public const STATUSES = [ 'idea', 'planned', 'in_progress', 'published' ];

	/** @var string[] */
	public const SOURCES = [ 'ai_suggested', 'manual' ];

	private const MAX_SUGGESTIONS = 20;

	private const COVERAGE_TRANSIENT = 'sfba_coverage_snapshot';

	private const COVERAGE_TTL = 6 * HOUR_IN_SECONDS;

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
		$loader->add_action( 'sfba_refresh_content_ideas', $this, 'suggest_topics' );
		$loader->add_action( 'transition_post_status', $this, 'invalidate_coverage_cache', 10, 3 );
		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );

		// REST: suggest topics.
		$this->core->rest_api->register_route( '/calendar/suggest', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_suggest' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'count' => [
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => self::MAX_SUGGESTIONS,
					],
				],
			],
		] );

		// REST: monthly calendar view.
		$this->core->rest_api->register_route( '/calendar', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_calendar' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'month' => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 12 ],
					'year'  => [ 'type' => 'integer', 'default' => 0 ],
				],
			],
		] );

		// REST: start writing from a calendar entry.
		$this->core->rest_api->register_route( '/calendar/start-writing', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_start_writing' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'calendar_id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
				],
			],
		] );

		// REST: add manual entry.
		$this->core->rest_api->register_route( '/calendar/entry', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_add_entry' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => $this->entry_schema_args(),
			],
		] );

		// REST: update or delete entry.
		$this->core->rest_api->register_route( '/calendar/entry/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_update_entry' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => array_merge(
					[ 'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ] ],
					$this->entry_schema_args( required: false )
				),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'rest_delete_entry' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
				],
			],
		] );

		// REST: link published post to calendar entry.
		$this->core->rest_api->register_route( '/calendar/link-post', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_link_post' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'entry_id' => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
					'post_id'  => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
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
			__( 'Content Calendar — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Content Calendar', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-content-calendar',
			[ $this, 'render_calendar_page' ]
		);
	}

	public function render_calendar_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$month = isset( $_GET['month'] ) ? (int) $_GET['month'] : (int) gmdate( 'n' ); // phpcs:ignore WordPress.Security.NonceVerification
		$year  = isset( $_GET['year'] )  ? (int) $_GET['year']  : (int) gmdate( 'Y' );  // phpcs:ignore WordPress.Security.NonceVerification
		$month = max( 1, min( 12, $month ) );
		$year  = max( 2020, min( 2099, $year ) );

		$view    = $this->get_calendar_view( $month, $year );
		$entries = [];

		if ( ! is_wp_error( $view ) ) {
			foreach ( $view['entries_by_day'] ?? [] as $day_entries ) {
				foreach ( $day_entries as $row ) {
					$entries[] = $row;
				}
			}
			foreach ( $view['unscheduled'] ?? [] as $row ) {
				$entries[] = $row;
			}
		}

		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-calendar.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** POST /calendar/suggest */
	public function rest_suggest( WP_REST_Request $request ): WP_REST_Response {
		$count  = (int) $request->get_param( 'count' );
		$result = $this->suggest_topics( $count );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [
			'inserted' => $result,
			'message'  => sprintf(
				/* translators: %d: number of topics inserted */
				_n( '%d topic suggestion added.', '%d topic suggestions added.', $result, 'super-fast-blog-ai' ),
				$result
			),
		] );
	}

	/** GET /calendar */
	public function rest_get_calendar( WP_REST_Request $request ): WP_REST_Response {
		$month  = (int) $request->get_param( 'month' );
		$year   = (int) $request->get_param( 'year' );
		$result = $this->get_calendar_view( $month, $year );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /calendar/start-writing */
	public function rest_start_writing( WP_REST_Request $request ): WP_REST_Response {
		$calendar_id = (int) $request->get_param( 'calendar_id' );
		$result      = $this->start_writing( $calendar_id );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /calendar/entry */
	public function rest_add_entry( WP_REST_Request $request ): WP_REST_Response {
		$data   = $this->entry_data_from_request( $request );
		$result = $this->add_entry( $data );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'entry_id' => $result, 'entry' => $this->get_entry( $result ) ] );
	}

	/** POST /calendar/entry/{id} */
	public function rest_update_entry( WP_REST_Request $request ): WP_REST_Response {
		$entry_id = (int) $request->get_param( 'id' );
		$data     = $this->entry_data_from_request( $request );
		$result   = $this->update_entry( $entry_id, $data );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'updated' => true, 'entry' => $this->get_entry( $entry_id ) ] );
	}

	/** DELETE /calendar/entry/{id} */
	public function rest_delete_entry( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'sfba_content_calendar',
			[ 'id' => $id ],
			[ '%d' ]
		);

		if ( false === $deleted ) {
			return SFBA_Rest_Api::error( 'db_error', __( 'Failed to delete entry.', 'super-fast-blog-ai' ), 500 );
		}

		return SFBA_Rest_Api::success( [ 'deleted' => $id ] );
	}

	/** POST /calendar/link-post */
	public function rest_link_post( WP_REST_Request $request ): WP_REST_Response {
		$entry_id = (int) $request->get_param( 'entry_id' );
		$post_id  = (int) $request->get_param( 'post_id' );
		$ok       = $this->link_post( $entry_id, $post_id );

		return SFBA_Rest_Api::success( [ 'linked' => $ok ] );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Use AI to suggest content topics based on existing keyword coverage.
	 *
	 * @param int $count Number of topic suggestions to generate.
	 * @return int|WP_Error Number of rows inserted, or WP_Error.
	 */
	public function suggest_topics( int $count = 10 ): int|WP_Error {
		$count = min( max( 1, $count ), self::MAX_SUGGESTIONS );

		$coverage          = $this->get_coverage_snapshot();
		$existing_keywords = $this->get_existing_keywords();
		$prompt            = $this->build_suggestion_prompt( $count, $coverage, $existing_keywords );

		// Resolve provider via model router (blog content type).
		$route = $this->core->providers->resolve_route( 'blog' );
		if ( null === $route ) {
			return new WP_Error( 'sfba_no_provider', __( 'No AI provider configured.', 'super-fast-blog-ai' ) );
		}

		$provider = $this->core->providers->get_provider( $route['provider'] );
		if ( null === $provider ) {
			return new WP_Error( 'sfba_no_provider', __( 'Provider not available.', 'super-fast-blog-ai' ) );
		}

		$start_ms = (int) round( microtime( true ) * 1000 );

		$gen = $provider->generate( $prompt, [
			'model'       => $route['model'],
			'max_tokens'  => 1200,
			'temperature' => 0.8,
		] );

		$elapsed = (int) round( microtime( true ) * 1000 ) - $start_ms;

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		// Log cost.
		$tokens = (int) ( $gen['tokens_used'] ?? 0 );
		$cost   = $this->core->cost_tracker->calculate_cost(
			$route['provider'],
			$route['model'],
			(int) ( $gen['prompt_tokens'] ?? (int) ( $tokens * 0.7 ) ),
			(int) ( $gen['completion_tokens'] ?? (int) ( $tokens * 0.3 ) )
		);

		$this->core->cost_tracker->log( [
			'provider'           => $route['provider'],
			'model'              => $route['model'],
			'prompt_tokens'      => (int) ( $gen['prompt_tokens'] ?? 0 ),
			'completion_tokens'  => (int) ( $gen['completion_tokens'] ?? $tokens ),
			'cost_usd'           => $cost,
			'feature'            => 'content_calendar',
			'generation_time_ms' => $elapsed,
		] );

		$topics   = $this->parse_suggestion_response( $gen['text'] ?? '', $count );
		$inserted = 0;

		foreach ( $topics as $topic ) {
			$id = $this->add_entry( array_merge( $topic, [ 'source' => 'ai_suggested' ] ) );
			if ( is_int( $id ) && $id > 0 ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Return a structured calendar view for a given month.
	 *
	 * @param int $month Month 1-12. 0 = current month.
	 * @param int $year  4-digit year. 0 = current year.
	 * @return array|WP_Error
	 */
	public function get_calendar_view( int $month = 0, int $year = 0 ): array|WP_Error {
		$year  = ( $year  > 0 ) ? $year  : (int) gmdate( 'Y' );
		$month = ( $month > 0 ) ? $month : (int) gmdate( 'n' );

		if ( $month < 1 || $month > 12 ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Month must be 1–12.', 'super-fast-blog-ai' ) );
		}

		global $wpdb;

		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$days_count  = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_count );

		$scheduled = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sfba_content_calendar
				  WHERE suggested_date >= %s
				    AND suggested_date <= %s
				  ORDER BY suggested_date ASC, id ASC",
				$month_start,
				$month_end
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unscheduled = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}sfba_content_calendar
			  WHERE suggested_date IS NULL
			     OR suggested_date = '0000-00-00'
			  ORDER BY created_at DESC
			  LIMIT 50",
			ARRAY_A
		);

		$entries_by_day = [];
		$stats          = [ 'total' => 0, 'idea' => 0, 'planned' => 0, 'in_progress' => 0, 'published' => 0 ];

		foreach ( $scheduled as $row ) {
			$day = (int) gmdate( 'j', strtotime( $row['suggested_date'] ) );

			$entries_by_day[ $day ][] = $this->format_entry( $row );

			$stats['total']++;
			$status = $row['status'] ?? 'idea';
			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ]++;
			}
		}

		$stats['total'] += count( $unscheduled );

		return [
			'year'           => $year,
			'month'          => $month,
			'month_name'     => gmdate( 'F', mktime( 0, 0, 0, $month, 1, $year ) ),
			'days_count'     => $days_count,
			'stats'          => $stats,
			'entries_by_day' => $entries_by_day,
			'unscheduled'    => array_map( [ $this, 'format_entry' ], $unscheduled ),
		];
	}

	/**
	 * Convert a calendar idea into a WordPress draft post and open the editor.
	 *
	 * @param int $calendar_id Calendar entry ID.
	 * @return array|WP_Error
	 */
	public function start_writing( int $calendar_id ): array|WP_Error {
		$entry = $this->get_entry( $calendar_id );

		if ( null === $entry ) {
			return new WP_Error(
				'sfba_not_found',
				__( 'Calendar entry not found.', 'super-fast-blog-ai' ),
				[ 'status' => 404 ]
			);
		}

		// Return existing draft if already linked.
		$existing_post_id = (int) ( $entry['post_id'] ?? 0 );
		if ( $existing_post_id > 0 ) {
			$existing_post = get_post( $existing_post_id );
			if ( $existing_post instanceof WP_Post ) {
				return [
					'post_id'  => $existing_post_id,
					'edit_url' => get_edit_post_link( $existing_post_id, 'raw' ),
					'is_new'   => false,
					'entry'    => $entry,
				];
			}
		}

		// Create a new draft from the calendar entry.
		$post_id = wp_insert_post( [
			'post_title'  => wp_strip_all_tags( $entry['title'] ),
			'post_status' => 'draft',
			'post_author' => get_current_user_id() ?: 1,
			'post_type'   => 'post',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $entry['target_keyword'] ) ) {
			update_post_meta( $post_id, '_sfba_target_keyword', sanitize_text_field( $entry['target_keyword'] ) );
		}

		update_post_meta( $post_id, '_sfba_calendar_id', $calendar_id );

		$this->update_entry( $calendar_id, [
			'status'  => 'in_progress',
			'post_id' => $post_id,
		] );

		$updated_entry = $this->get_entry( $calendar_id );

		return [
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'is_new'   => true,
			'entry'    => $updated_entry,
		];
	}

	/**
	 * Add a calendar entry (AI-suggested or manual).
	 *
	 * Skips insertion if an entry with the same target_keyword already exists.
	 *
	 * @param array $data
	 * @return int|WP_Error Inserted entry ID, or WP_Error.
	 */
	public function add_entry( array $data ): int|WP_Error {
		global $wpdb;

		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( '' === $title ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Entry title is required.', 'super-fast-blog-ai' ) );
		}

		$keyword    = sanitize_text_field( $data['target_keyword'] ?? '' );
		$status     = in_array( $data['status'] ?? '', self::STATUSES, true ) ? $data['status'] : 'idea';
		$source     = in_array( $data['source'] ?? '', self::SOURCES, true ) ? $data['source'] : 'manual';
		$difficulty = min( 100, max( 0, (int) ( $data['keyword_difficulty'] ?? 0 ) ) );
		$volume     = max( 0, (int) ( $data['keyword_volume'] ?? 0 ) );

		$suggested_date = null;
		if ( ! empty( $data['suggested_date'] ) ) {
			$ts = strtotime( $data['suggested_date'] );
			if ( $ts ) {
				$suggested_date = gmdate( 'Y-m-d', $ts );
			}
		}

		// Dedup on target_keyword.
		if ( '' !== $keyword ) {
			$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}sfba_content_calendar WHERE LOWER(target_keyword) = LOWER(%s) LIMIT 1",
					$keyword
				)
			);
			if ( $exists ) {
				return new WP_Error( 'sfba_duplicate', __( 'A calendar entry with this keyword already exists.', 'super-fast-blog-ai' ) );
			}
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'sfba_content_calendar',
			[
				'title'              => $title,
				'target_keyword'     => $keyword,
				'keyword_volume'     => $volume,
				'keyword_difficulty' => $difficulty,
				'suggested_date'     => $suggested_date,
				'status'             => $status,
				'post_id'            => (int) ( $data['post_id'] ?? 0 ),
				'source'             => $source,
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' ]
		);

		if ( false === $result ) {
			return new WP_Error( 'sfba_db_error', __( 'Failed to insert calendar entry.', 'super-fast-blog-ai' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update one or more fields on an existing calendar entry.
	 *
	 * @param int   $entry_id Calendar entry ID.
	 * @param array $data     Fields to update.
	 * @return bool|WP_Error
	 */
	public function update_entry( int $entry_id, array $data ): bool|WP_Error {
		global $wpdb;

		if ( $entry_id < 1 ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Invalid entry ID.', 'super-fast-blog-ai' ) );
		}

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}sfba_content_calendar WHERE id = %d LIMIT 1",
				$entry_id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'sfba_not_found', __( 'Calendar entry not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
		}

		$update  = [];
		$formats = [];

		if ( isset( $data['title'] ) ) {
			$update['title'] = sanitize_text_field( $data['title'] );
			$formats[]       = '%s';
		}

		if ( isset( $data['target_keyword'] ) ) {
			$update['target_keyword'] = sanitize_text_field( $data['target_keyword'] );
			$formats[]                = '%s';
		}

		if ( isset( $data['keyword_volume'] ) ) {
			$update['keyword_volume'] = max( 0, (int) $data['keyword_volume'] );
			$formats[]                = '%d';
		}

		if ( isset( $data['keyword_difficulty'] ) ) {
			$update['keyword_difficulty'] = min( 100, max( 0, (int) $data['keyword_difficulty'] ) );
			$formats[]                    = '%d';
		}

		if ( isset( $data['suggested_date'] ) ) {
			$ts                       = $data['suggested_date'] ? strtotime( $data['suggested_date'] ) : false;
			$update['suggested_date'] = $ts ? gmdate( 'Y-m-d', $ts ) : null;
			$formats[]                = '%s';
		}

		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ) {
			$update['status'] = $data['status'];
			$formats[]        = '%s';
		}

		if ( isset( $data['post_id'] ) ) {
			$update['post_id'] = max( 0, (int) $data['post_id'] );
			$formats[]         = '%d';
		}

		if ( empty( $update ) ) {
			return true;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'sfba_content_calendar',
			$update,
			[ 'id' => $entry_id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error( 'sfba_db_error', __( 'Failed to update calendar entry.', 'super-fast-blog-ai' ) );
		}

		return true;
	}

	/**
	 * Associate a published post with its calendar entry.
	 *
	 * Automatically sets entry status to 'published'.
	 *
	 * @param int $entry_id Calendar entry ID.
	 * @param int $post_id  Published post ID.
	 * @return bool
	 */
	public function link_post( int $entry_id, int $post_id ): bool {
		$result = $this->update_entry( $entry_id, [
			'post_id' => $post_id,
			'status'  => 'published',
		] );

		return true === $result;
	}

	// -------------------------------------------------------------------------
	// Cache invalidation.
	// -------------------------------------------------------------------------

	/**
	 * Clear the coverage snapshot when a post is published or trashed.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function invalidate_coverage_cache( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		delete_transient( self::COVERAGE_TRANSIENT );
	}

	// -------------------------------------------------------------------------
	// Coverage analysis (keyword gap detection).
	// -------------------------------------------------------------------------

	/**
	 * Build a keyword coverage snapshot from existing published posts.
	 *
	 * @return array{titles: string[], categories: string[], tags: string[]}
	 */
	private function get_coverage_snapshot(): array {
		$cached = get_transient( self::COVERAGE_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$posts = get_posts( [
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		] );

		$titles     = [];
		$categories = [];
		$tags       = [];

		foreach ( $posts as $post_id ) {
			$titles[] = get_the_title( $post_id );

			foreach ( get_the_category( $post_id ) as $cat ) {
				$categories[] = $cat->name;
			}

			foreach ( get_the_tags( $post_id ) ?: [] as $tag ) {
				$tags[] = $tag->name;
			}
		}

		$snapshot = [
			'titles'     => array_values( array_unique( $titles ) ),
			'categories' => array_values( array_unique( $categories ) ),
			'tags'        => array_values( array_unique( $tags ) ),
		];

		set_transient( self::COVERAGE_TRANSIENT, $snapshot, self::COVERAGE_TTL );

		return $snapshot;
	}

	/**
	 * Fetch all target_keywords already in the calendar table (for dedup).
	 *
	 * @return string[]
	 */
	private function get_existing_keywords(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			"SELECT LOWER(target_keyword) FROM {$wpdb->prefix}sfba_content_calendar WHERE target_keyword != '' LIMIT 500"
		);

		return array_filter( (array) $rows );
	}

	// -------------------------------------------------------------------------
	// Prompt builder.
	// -------------------------------------------------------------------------

	/**
	 * Build the topic suggestion prompt.
	 *
	 * @param int      $count
	 * @param array    $coverage
	 * @param string[] $existing_keywords
	 * @return string
	 */
	private function build_suggestion_prompt( int $count, array $coverage, array $existing_keywords ): string {
		$today     = gmdate( 'Y-m-d' );
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );

		$existing_titles = implode( "\n- ", array_slice( $coverage['titles'], 0, 50 ) );
		$categories      = implode( ', ', array_slice( $coverage['categories'], 0, 20 ) );
		$tags            = implode( ', ', array_slice( $coverage['tags'], 0, 30 ) );
		$skip_keywords   = implode( ', ', array_slice( $existing_keywords, 0, 40 ) );

		return "You are an expert SEO content strategist analysing an editorial calendar.

SITE: {$site_name}
DESCRIPTION: {$site_desc}
TODAY: {$today}

EXISTING CONTENT (published posts — do NOT suggest these topics):
- {$existing_titles}

EXISTING CATEGORIES: {$categories}
EXISTING TAGS: {$tags}

KEYWORDS ALREADY IN CALENDAR (do NOT repeat): {$skip_keywords}

TASK:
Identify {$count} content gaps and suggest {$count} new blog post topics that:
1. Are NOT already covered by the existing posts above
2. Are relevant to the site's niche based on its categories and tags
3. Have real search potential (people actually search for these)
4. Vary in difficulty (mix of easy wins and competitive topics)
5. Are spread across the next 30 days for a balanced publishing schedule

Respond with ONLY a valid JSON array (no markdown, no explanation). Each object must have exactly these keys:
[
  {
    \"title\": \"Exact post title (compelling, SEO-friendly)\",
    \"target_keyword\": \"primary search keyword (2-4 words)\",
    \"keyword_volume\": 1200,
    \"keyword_difficulty\": 35,
    \"suggested_date\": \"{$today}\",
    \"rationale\": \"One sentence explaining the keyword gap this fills.\"
  }
]

Distribute suggested_date values across the next 30 days starting from {$today}.
keyword_volume: realistic estimated monthly searches (100 to 50000).
keyword_difficulty: 0 (easiest) to 100 (hardest).";
	}

	// -------------------------------------------------------------------------
	// Response parsers.
	// -------------------------------------------------------------------------

	/**
	 * Parse the AI response into an array of topic data arrays.
	 *
	 * @param string $text  Raw AI response text.
	 * @param int    $count Requested count.
	 * @return array
	 */
	private function parse_suggestion_response( string $text, int $count ): array {
		$text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
		$text = preg_replace( '/\s*```$/', '', $text );

		$decoded = json_decode( $text, true );

		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			return $this->normalize_topics( $decoded, $count );
		}

		if ( preg_match( '/\[[\s\S]+\]/m', $text, $m ) ) {
			$decoded = json_decode( $m[0], true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_topics( $decoded, $count );
			}
		}

		return $this->mock_suggestions( $count );
	}

	/**
	 * Validate and normalise decoded topic objects.
	 *
	 * @param array $raw
	 * @param int   $count
	 * @return array
	 */
	private function normalize_topics( array $raw, int $count ): array {
		$today  = gmdate( 'Y-m-d' );
		$result = [];

		foreach ( array_slice( $raw, 0, $count ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['title'] ) ) {
				continue;
			}

			$ts   = isset( $item['suggested_date'] ) ? strtotime( $item['suggested_date'] ) : false;
			$date = ( $ts && $ts >= strtotime( $today ) ) ? gmdate( 'Y-m-d', $ts ) : null;

			$result[] = [
				'title'              => sanitize_text_field( $item['title'] ),
				'target_keyword'     => sanitize_text_field( $item['target_keyword'] ?? '' ),
				'keyword_volume'     => max( 0, (int) ( $item['keyword_volume'] ?? 0 ) ),
				'keyword_difficulty' => min( 100, max( 0, (int) ( $item['keyword_difficulty'] ?? 50 ) ) ),
				'suggested_date'     => $date,
				'status'             => 'idea',
			];
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single calendar entry by ID.
	 *
	 * @param int $entry_id
	 * @return array|null
	 */
	private function get_entry( int $entry_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sfba_content_calendar WHERE id = %d LIMIT 1",
				$entry_id
			),
			ARRAY_A
		);

		return $row ? $this->format_entry( $row ) : null;
	}

	/**
	 * Format a DB row for API output.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function format_entry( array $row ): array {
		$post_id       = (int) ( $row['post_id'] ?? 0 );
		$post_edit_url = null;

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$post_edit_url = get_edit_post_link( $post_id, 'raw' );
			}
		}

		return [
			'id'                 => (int) $row['id'],
			'title'              => $row['title'],
			'target_keyword'     => $row['target_keyword'],
			'keyword_volume'     => (int) $row['keyword_volume'],
			'keyword_difficulty' => (int) $row['keyword_difficulty'],
			'suggested_date'     => $row['suggested_date'],
			'status'             => $row['status'],
			'post_id'            => $post_id,
			'post_edit_url'      => $post_edit_url,
			'source'             => $row['source'],
			'created_at'         => $row['created_at'],
		];
	}

	/**
	 * REST arg schema for entry create/update.
	 *
	 * @param bool $required
	 * @return array
	 */
	private function entry_schema_args( bool $required = true ): array {
		return [
			'title'              => [ 'type' => 'string',  'required' => $required, 'sanitize_callback' => 'sanitize_text_field' ],
			'target_keyword'     => [ 'type' => 'string',  'required' => false, 'default' => '' ],
			'keyword_volume'     => [ 'type' => 'integer', 'required' => false, 'default' => 0,   'minimum' => 0 ],
			'keyword_difficulty' => [ 'type' => 'integer', 'required' => false, 'default' => 50,  'minimum' => 0, 'maximum' => 100 ],
			'suggested_date'     => [ 'type' => 'string',  'required' => false, 'default' => '' ],
			'status'             => [ 'type' => 'string',  'required' => false, 'default' => 'idea', 'enum' => self::STATUSES ],
		];
	}

	/**
	 * Extract entry data from a REST request.
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	private function entry_data_from_request( WP_REST_Request $request ): array {
		return [
			'title'              => (string) $request->get_param( 'title' ),
			'target_keyword'     => (string) $request->get_param( 'target_keyword' ),
			'keyword_volume'     => (int) $request->get_param( 'keyword_volume' ),
			'keyword_difficulty' => (int) $request->get_param( 'keyword_difficulty' ),
			'suggested_date'     => (string) $request->get_param( 'suggested_date' ),
			'status'             => (string) $request->get_param( 'status' ),
			'source'             => 'manual',
		];
	}

	// -------------------------------------------------------------------------
	// Mock fallback.
	// -------------------------------------------------------------------------

	/**
	 * Return mock topic suggestions when AI is unavailable or response parsing fails.
	 *
	 * @param int $count
	 * @return array
	 */
	private function mock_suggestions( int $count ): array {
		$today = gmdate( 'Y-m-d' );

		$bank = [
			[ 'title' => 'How to Create a Content Strategy That Actually Works in 2025',         'target_keyword' => 'content strategy guide',         'keyword_volume' => 4400, 'keyword_difficulty' => 42 ],
			[ 'title' => '10 SEO Mistakes WordPress Sites Make (And How to Fix Them)',           'target_keyword' => 'wordpress seo mistakes',          'keyword_volume' => 2900, 'keyword_difficulty' => 31 ],
			[ 'title' => 'The Complete Guide to Internal Linking for Better Search Rankings',     'target_keyword' => 'internal linking seo',            'keyword_volume' => 3600, 'keyword_difficulty' => 38 ],
			[ 'title' => 'How to Write a Blog Post That Ranks on Page One',                      'target_keyword' => 'how to write blog post seo',      'keyword_volume' => 5400, 'keyword_difficulty' => 55 ],
			[ 'title' => 'AI Content Writing: The Complete Beginner\'s Guide',                   'target_keyword' => 'ai content writing guide',        'keyword_volume' => 6600, 'keyword_difficulty' => 48 ],
			[ 'title' => 'What Is Brand Voice and Why It Matters for Your Blog',                 'target_keyword' => 'brand voice definition',          'keyword_volume' => 1900, 'keyword_difficulty' => 29 ],
			[ 'title' => 'How to Repurpose Blog Content Into Social Media Posts',                'target_keyword' => 'repurpose blog content social',   'keyword_volume' => 2200, 'keyword_difficulty' => 33 ],
			[ 'title' => 'Long-Tail Keywords: How to Find and Use Them Effectively',             'target_keyword' => 'long tail keyword strategy',      'keyword_volume' => 3800, 'keyword_difficulty' => 36 ],
			[ 'title' => 'Editorial Calendar: How to Plan Your Content 3 Months Ahead',         'target_keyword' => 'editorial calendar template',     'keyword_volume' => 4100, 'keyword_difficulty' => 27 ],
			[ 'title' => 'How to Measure Your Content Marketing ROI (With Real Examples)',       'target_keyword' => 'content marketing roi',           'keyword_volume' => 2700, 'keyword_difficulty' => 44 ],
			[ 'title' => 'The Best AI Writing Tools Compared (Free & Paid)',                     'target_keyword' => 'best ai writing tools',           'keyword_volume' => 8100, 'keyword_difficulty' => 62 ],
			[ 'title' => 'Keyword Research for Beginners: A Step-by-Step Guide',                'target_keyword' => 'keyword research tutorial',       'keyword_volume' => 7400, 'keyword_difficulty' => 50 ],
			[ 'title' => 'How to Improve Your Blog\'s Bounce Rate in 7 Simple Steps',           'target_keyword' => 'reduce blog bounce rate',         'keyword_volume' => 1600, 'keyword_difficulty' => 23 ],
			[ 'title' => 'Pillar Content Strategy: Build Topical Authority in Your Niche',      'target_keyword' => 'pillar content strategy seo',     'keyword_volume' => 2100, 'keyword_difficulty' => 40 ],
			[ 'title' => 'How to Write Meta Descriptions That Actually Get Clicks',              'target_keyword' => 'how to write meta descriptions',  'keyword_volume' => 3300, 'keyword_difficulty' => 32 ],
			[ 'title' => 'Content Clusters: The Modern SEO Strategy for WordPress Sites',        'target_keyword' => 'content cluster seo strategy',   'keyword_volume' => 1800, 'keyword_difficulty' => 37 ],
			[ 'title' => 'How Often Should You Post on Your Blog? A Data-Driven Answer',         'target_keyword' => 'how often to blog frequency',     'keyword_volume' => 2400, 'keyword_difficulty' => 26 ],
			[ 'title' => 'Google Search Console: Complete Tutorial for Bloggers',                'target_keyword' => 'google search console tutorial', 'keyword_volume' => 5200, 'keyword_difficulty' => 46 ],
			[ 'title' => 'How to Write Product Descriptions That Convert Browsers into Buyers',  'target_keyword' => 'product description copywriting', 'keyword_volume' => 3000, 'keyword_difficulty' => 35 ],
			[ 'title' => 'The Ultimate Guide to Email Newsletter Marketing for Bloggers',        'target_keyword' => 'email newsletter marketing blog', 'keyword_volume' => 4700, 'keyword_difficulty' => 43 ],
		];

		$count  = min( $count, count( $bank ) );
		$result = [];

		foreach ( array_slice( $bank, 0, $count ) as $i => $item ) {
			$days_ahead = (int) round( ( $i / max( 1, $count - 1 ) ) * 29 );
			$date       = gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days", strtotime( $today ) ) );

			$result[] = [
				'title'              => $item['title'],
				'target_keyword'     => $item['target_keyword'],
				'keyword_volume'     => $item['keyword_volume'],
				'keyword_difficulty' => $item['keyword_difficulty'],
				'suggested_date'     => $date,
				'status'             => 'idea',
			];
		}

		return $result;
	}
}
