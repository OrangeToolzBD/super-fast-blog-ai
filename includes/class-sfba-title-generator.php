<?php
/**
 * Hybrid Title Generator.
 *
 * Keeps all SFBA title-generation UX, DB logging, and scheduling logic.
 * Replaces hardcoded OpenAI calls with the provider-agnostic SFBA_Providers
 * factory, and logs every generation into wp_sfba_generations with
 * feature = 'title_generation'.
 *
 * REST endpoints (registered via SFBA_Rest_Api):
 *   POST /title/generate          → generate title variations
 *   POST /title/generate-content  → instant article from title
 *   POST /title/seo-keywords      → generate SEO keywords for a title
 *   POST /title/meta-description  → generate meta description for a title
 *   PUT  /title/{id}              → update a saved title
 *   DELETE /title/{id}            → delete a single title
 *   POST /title/bulk-delete       → delete multiple titles by protid
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Title_Generator {

	/**
	 * @var SFBA_Core
	 */
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
		// Admin menu.
		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );

		// REST routes.
		$this->core->rest_api->register_route( '/title/generate', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_titles' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'blog_title' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'wvariation' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'tlanguage'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'twstyle'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'writone'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/title/generate-content', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_content_from_title' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'title'       => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'keywords'    => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'meta_desc'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/title/seo-keywords', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_seo_keywords' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'blog_title' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/title/meta-description', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_meta_description' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'blog_title' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/title/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'rest_update_title' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id'    => [ 'type' => 'integer', 'required' => true, 'validate_callback' => 'rest_validate_request_arg' ],
					'title' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'rest_delete_title' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true, 'validate_callback' => 'rest_validate_request_arg' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/title/bulk-delete', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_bulk_delete_titles' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'protids' => [ 'type' => 'array', 'required' => true, 'items' => [ 'type' => 'integer' ] ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin page render.
	// -------------------------------------------------------------------------

	/**
	 * Register the Generate Content submenu page.
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Generate Content — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Generate Content', 'super-fast-blog-ai' ),
			'edit_posts',
			'sfba-generate',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the title-generator admin page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$cache_key = 'sfba_generated_titles';
		$results   = wp_cache_get( $cache_key, 'sfba_cache' );
		if ( false === $results ) {
			$results = $wpdb->get_results(
				"SELECT promt_title, id, ctime, protid,
				 GROUP_CONCAT(CONCAT(id, '||', generate_title) ORDER BY id ASC SEPARATOR '\n') AS titles
				 FROM {$wpdb->prefix}slf_generated_title
				 GROUP BY promt_title
				 ORDER BY ctime DESC"
			);
			wp_cache_set( $cache_key, $results, 'sfba_cache', HOUR_IN_SECONDS );
		}
		// phpcs:enable

		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce    = wp_create_nonce( 'wp_rest' );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-title-generator.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/**
	 * POST /title/generate
	 *
	 * Generate N title variations for a given prompt using the active provider.
	 */
	public function rest_generate_titles( WP_REST_Request $request ): WP_REST_Response {
		$blog_title = (string) $request->get_param( 'blog_title' );
		$wvariation = (string) $request->get_param( 'wvariation' );
		$tlanguage  = (string) $request->get_param( 'tlanguage' );
		$twstyle    = (string) $request->get_param( 'twstyle' );
		$writone    = (string) $request->get_param( 'writone' );

		$prompt  = 'Generate ' . $wvariation . ' concise, single-sentence blog post titles based on the input: "' . $blog_title . '".';
		$prompt .= ' Avoid possessive forms such as "\'s".';
		$prompt .= ' Use the tone: ' . $writone . ', style: ' . $twstyle . ', and language: ' . $tlanguage . '.';
		$prompt .= ' Ensure each title is a single sentence, without multiple periods, clear and easy to understand, relevant to the target audience.';
		$prompt .= ' Output one title per line, with no numbering or quotes.';

		$result = $this->call_provider( 'title_generation', $prompt, [
			'system'     => 'You are a helpful assistant.',
			'max_tokens' => 1000,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$titles = $this->parse_title_list( $result['text'] );

		if ( empty( $titles ) ) {
			return SFBA_Rest_Api::error( 'sfba_empty_titles', __( 'Generated titles are invalid or empty.', 'super-fast-blog-ai' ) );
		}

		// Persist to DB.
		$this->insert_generated_titles( $blog_title, $titles );

		// Invalidate page cache.
		wp_cache_delete( 'sfba_generated_titles', 'sfba_cache' );

		return SFBA_Rest_Api::success( array_values( $titles ) );
	}

	/**
	 * POST /title/generate-content
	 *
	 * Instantly generate a full article from a selected title and publish as draft.
	 */
	public function rest_generate_content_from_title( WP_REST_Request $request ): WP_REST_Response {
		$title      = (string) $request->get_param( 'title' );
		$seokeyword = (string) $request->get_param( 'keywords' );
		$metadescrip = (string) $request->get_param( 'meta_desc' );

		$s = $this->core->settings;

		$word_count      = absint( $s->get( 'content.word_count', 500 ) );
		$estimated_tokens = min( (int) ( $word_count * 1.5 ), 8090 );

		$keyword_parts = array_filter( array_map( 'trim', explode( ',', $seokeyword ) ) );

		// Build prompt messages array.
		$messages = $this->build_article_prompt( $title, $keyword_parts, $word_count, $s );

		$result = $this->call_provider( 'title_generation', $messages, [
			'max_tokens' => $estimated_tokens,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$content = $result['text'];
		if ( empty( $content ) ) {
			return SFBA_Rest_Api::error( 'sfba_empty_content', __( 'Generated content was empty.', 'super-fast-blog-ai' ) );
		}

		$post_id = $this->insert_post( $title, $content );
		if ( is_wp_error( $post_id ) ) {
			return SFBA_Rest_Api::error( 'sfba_insert_failed', __( 'Failed to insert post.', 'super-fast-blog-ai' ) );
		}

		// SEO meta.
		if ( $s->get( 'seo.keywords' ) || $s->get( 'seo.meta_desc' ) ) {
			$this->update_seo_meta( $post_id, $seokeyword, $metadescrip, $s );
		}

		// Featured image.
		$this->set_featured_image( $post_id, $seokeyword, $title );

		// Log entry.
		$this->insert_schedule_log( $title, $post_id, mb_strlen( $content, 'UTF-8' ), $result );

		// Remove from title queue.
		$this->remove_generated_title( $title );

		// Email notification.
		if ( $s->get( 'publishing.email_notify' ) ) {
			$this->send_email_notification( $post_id );
		}

		return SFBA_Rest_Api::success( [
			'message' => __( 'Post published successfully.', 'super-fast-blog-ai' ),
			'post_id' => $post_id,
			'title'   => $title,
		] );
	}

	/**
	 * POST /title/seo-keywords
	 */
	public function rest_generate_seo_keywords( WP_REST_Request $request ): WP_REST_Response {
		$blog_title = (string) $request->get_param( 'blog_title' );
		$prompt     = 'Provide only a list of five SEO keywords, one per line, with no numbering, for: ' . $blog_title;

		$result = $this->call_provider( 'title_generation', $prompt, [
			'system'     => 'You are a helpful assistant.',
			'max_tokens' => 200,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$keywords = $this->parse_title_list( $result['text'] );
		return SFBA_Rest_Api::success( array_values( $keywords ) );
	}

	/**
	 * POST /title/meta-description
	 */
	public function rest_generate_meta_description( WP_REST_Request $request ): WP_REST_Response {
		$blog_title = (string) $request->get_param( 'blog_title' );
		$prompt     = 'Write a SEO-friendly 160-character meta description (output the description only, no labels) for: ' . $blog_title;

		$result = $this->call_provider( 'title_generation', $prompt, [
			'system'     => 'You are a helpful assistant.',
			'max_tokens' => 80,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'meta_description' => trim( $result['text'] ) ] );
	}

	/**
	 * PUT /title/{id}
	 */
	public function rest_update_title( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$id    = absint( $request->get_param( 'id' ) );
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'slf_generated_title',
			[ 'generate_title' => $title ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return SFBA_Rest_Api::error( 'sfba_update_failed', __( 'Failed to update title.', 'super-fast-blog-ai' ), 500 );
		}

		wp_cache_delete( 'sfba_generated_titles', 'sfba_cache' );
		return SFBA_Rest_Api::success( [ 'updated' => true ] );
	}

	/**
	 * DELETE /title/{id}
	 */
	public function rest_delete_title( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$id      = absint( $request->get_param( 'id' ) );
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'slf_generated_title',
			[ 'id' => $id ],
			[ '%d' ]
		);

		if ( false === $deleted ) {
			return SFBA_Rest_Api::error( 'sfba_delete_failed', __( 'Failed to delete title.', 'super-fast-blog-ai' ), 500 );
		}

		wp_cache_delete( 'sfba_generated_titles', 'sfba_cache' );
		return SFBA_Rest_Api::success( [ 'deleted' => true ] );
	}

	/**
	 * POST /title/bulk-delete
	 */
	public function rest_bulk_delete_titles( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$protids = array_map( 'absint', (array) $request->get_param( 'protids' ) );
		if ( empty( $protids ) ) {
			return SFBA_Rest_Api::error( 'sfba_no_ids', __( 'No title IDs provided.', 'super-fast-blog-ai' ) );
		}

		$placeholders = implode( ',', array_fill( 0, count( $protids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}slf_generated_title WHERE protid IN ({$placeholders})", $protids ) );

		if ( false === $result ) {
			return SFBA_Rest_Api::error( 'sfba_delete_failed', __( 'Failed to delete titles.', 'super-fast-blog-ai' ), 500 );
		}

		wp_cache_delete( 'sfba_generated_titles', 'sfba_cache' );
		return SFBA_Rest_Api::success( [ 'deleted' => $result ] );
	}

	// -------------------------------------------------------------------------
	// Provider routing helper.
	// -------------------------------------------------------------------------

	/**
	 * Route an AI request through the provider system.
	 *
	 * Resolves the active provider for 'title_generation', calls generate(),
	 * and logs the result in wp_sfba_generations via SFBA_Cost_Tracker.
	 *
	 * @param string       $content_type Content type tag (e.g. 'title_generation').
	 * @param string|array $prompt       Plain string prompt or messages array.
	 * @param array        $options      Additional options (system, max_tokens, temperature).
	 * @return array|WP_Error
	 */
	private function call_provider( string $content_type, string|array $prompt, array $options = [] ): array|WP_Error {
		$route = $this->core->providers->resolve_route( $content_type );
		if ( null === $route ) {
			return new WP_Error( 'sfba_no_provider', __( 'No AI provider configured. Please add an API key in Settings.', 'super-fast-blog-ai' ) );
		}

		$provider = $this->core->providers->get_provider( $route['provider'] );
		if ( null === $provider ) {
			return new WP_Error( 'sfba_provider_missing', __( 'Selected provider is not available.', 'super-fast-blog-ai' ) );
		}

		$options['model']       = $options['model'] ?? $route['model'];
		$options['max_tokens']  = $options['max_tokens'] ?? $route['max_tokens'];
		$options['temperature'] = $options['temperature'] ?? $route['temperature'];

		// If prompt is an array of messages, join into single string for providers
		// that don't support multi-turn. The abstract provider handles messages arrays
		// natively via the system key, so we pass as-is when it's a string.
		$result = $provider->generate( is_array( $prompt ) ? implode( "\n\n", array_column( $prompt, 'content' ) ) : $prompt, $options );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log cost.
		$this->core->cost_tracker->log( [
			'feature'           => $content_type,
			'provider'          => $route['provider'],
			'model'             => $result['model'] ?? $route['model'],
			'prompt_tokens'     => $result['prompt_tokens'] ?? 0,
			'completion_tokens' => $result['completion_tokens'] ?? 0,
			'cost_usd'          => $result['cost_usd'] ?? 0.0,
		] );

		return $result;
	}

	// -------------------------------------------------------------------------
	// Prompt builder.
	// -------------------------------------------------------------------------

	/**
	 * Build the messages array for a full article generation prompt.
	 *
	 * @param string   $title         Post title.
	 * @param string[] $keyword_parts SEO keywords (may be empty).
	 * @param int      $word_count    Target word count.
	 * @param SFBA_Settings $s
	 * @return string Prompt string with all content instructions.
	 */
	private function build_article_prompt( string $title, array $keyword_parts, int $word_count, SFBA_Settings $s ): string {
		$tone  = $s->get( 'content.tone', 'formal' );
		$style = $s->get( 'content.writing_style', 'informative' );
		$lang  = $s->get( 'content.language', 'English' );

		$lines = [];
		$lines[] = "You are an expert SEO assistant that generates well-structured, keyword-optimized, and SEO-friendly content. Ensure the content is engaging, informative, and easy to read.";
		$lines[] = sprintf( 'Generate a detailed, SEO-optimized article on "%s" with at least %d words.', $title, $word_count );
		$lines[] = sprintf( 'Use tone: %s. Use writing style: %s. Use language: %s.', $tone, $style, $lang );

		if ( ! empty( $keyword_parts ) ) {
			$main_kw = $keyword_parts[0];
			$lines[] = sprintf( 'Use the main keyword "%s" within the first 100 words and in at least one heading.', $main_kw );
			if ( count( $keyword_parts ) > 1 ) {
				$secondary = implode( ', ', array_slice( $keyword_parts, 1 ) );
				$lines[]   = sprintf( 'Use secondary keywords naturally: %s.', $secondary );
			}
		}

		if ( $s->get( 'content.subheadings' ) ) {
			$lines[] = sprintf(
				'Incorporate %d subheadings using %s tags to structure the content.',
				absint( $s->get( 'content.heading_count', 3 ) ),
				esc_html( (string) $s->get( 'content.heading_tag', 'h2' ) )
			);
		}

		if ( $s->get( 'content.faq' ) ) {
			$lines[] = 'Add a FAQ section at the end of the article.';
		}

		if ( $s->get( 'content.toc' ) ) {
			$lines[] = 'After writing the introduction, insert a Table of Contents wrapped in: <div style="background-color:#d7d6d5;padding:16px;border-radius:4px;"><strong>Table of Contents</strong><ul>...</ul></div>';
		}

		if ( $s->get( 'content.pros_cons' ) ) {
			$lines[] = 'Include a Pros and Cons section as an HTML table. Pros header: red background, white text. Cons header: green background, white text.';
		}

		$lines[] = 'Do not use unnecessary HTML tags like <HTML>, <body>, or <article>. Only use <h1>–<h4>, <p>, <ul>, <li>, <b>, <i> tags where relevant.';
		$lines[] = 'Ensure no more than 10% passive sentences, no more than 25% of sentences exceed 20 words, and at least 30% contain transition words.';

		return implode( "\n\n", $lines );
	}

	// -------------------------------------------------------------------------
	// DB helpers.
	// -------------------------------------------------------------------------

	/**
	 * Insert generated titles into wp_slf_generated_title.
	 *
	 * @param string   $prompt_title The user's input prompt.
	 * @param string[] $titles       Cleaned title strings.
	 */
	private function insert_generated_titles( string $prompt_title, array $titles ): void {
		global $wpdb;

		$table      = $wpdb->prefix . 'slf_generated_title';
		$cache_key  = 'sfba_max_protid';
		$last_protid = wp_cache_get( $cache_key );

		if ( false === $last_protid ) {
			$last_protid = (int) $wpdb->get_var( "SELECT MAX(protid) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			wp_cache_set( $cache_key, $last_protid, '', HOUR_IN_SECONDS );
		}

		$new_protid = $last_protid + 1;

		foreach ( $titles as $title ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				[
					'promt_title'    => $prompt_title,
					'generate_title' => $title,
					'protid'         => $new_protid,
				],
				[ '%s', '%s', '%d' ]
			);
		}

		wp_cache_delete( $cache_key );
	}

	/**
	 * Insert a WP post as draft with the given title and content.
	 *
	 * @return int|WP_Error Post ID on success.
	 */
	private function insert_post( string $title, string $content ): int|WP_Error {
		$categories = $this->core->settings->get( 'publishing.categories', [] );

		$post_id = wp_insert_post( [
			'post_title'   => $title,
			'post_content' => wp_kses( $content, wp_kses_allowed_html( 'post' ) ),
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
			'post_date'    => current_time( 'mysql' ),
			'post_name'    => sanitize_title( $title ),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $categories ) ) {
			wp_set_post_terms( $post_id, $categories, 'category' );
		}

		return $post_id;
	}

	/**
	 * Insert a row into wp_slf_schedule_post_title_log.
	 *
	 * @param string $title    Post title.
	 * @param int    $post_id  Inserted post ID.
	 * @param int    $char_len Content character length.
	 * @param array  $result   Provider result array (model, tokens).
	 */
	private function insert_schedule_log( string $title, int $post_id, int $char_len, array $result ): void {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'slf_schedule_post_title_log',
			[
				'title'     => $title,
				'status'    => $this->core->settings->get( 'schedule.type', 'sameday' ),
				'postid'    => $post_id,
				'charaters' => $char_len,
				'modelused' => $result['model'] ?? '',
				'log_time'  => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
	}

	/**
	 * Remove a generated title from the queue (after it has been published).
	 */
	private function remove_generated_title( string $title ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'slf_generated_title',
			[ 'generate_title' => $title ],
			[ '%s' ]
		);

		wp_cache_delete( 'sfba_generated_titles', 'sfba_cache' );
	}

	// -------------------------------------------------------------------------
	// SEO meta.
	// -------------------------------------------------------------------------

	/**
	 * Write SEO keyword and meta description to Yoast SEO or Rank Math post meta.
	 */
	private function update_seo_meta( int $post_id, string $keyword, string $meta_desc, SFBA_Settings $s ): void {
		$yoast     = 'wordpress-seo/wp-seo.php';
		$rank_math = 'seo-by-rank-math/rank-math.php';

		if ( is_plugin_active( $yoast ) ) {
			if ( $s->get( 'seo.keywords' ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
			}
			if ( $s->get( 'seo.meta_desc' ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
			}
		} elseif ( is_plugin_active( $rank_math ) ) {
			if ( $s->get( 'seo.keywords' ) ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
			}
			if ( $s->get( 'seo.meta_desc' ) ) {
				update_post_meta( $post_id, 'rank_math_description', $meta_desc );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Featured image.
	// -------------------------------------------------------------------------

	/**
	 * Set the featured image for a post using the configured image source.
	 *
	 * Supports: DALL-E 3 (OpenAI), Pixabay, Unsplash.
	 */
	private function set_featured_image( int $post_id, string $keyword, string $title ): void {
		$source = $this->core->settings->get( 'images.source', 'dalle3' );

		$is_temp = false;
		$image_path = null;

		if ( 'dalle3' === $source ) {
			$image_path = $this->generate_dalle_image( $title );
			$is_temp    = true;
		} elseif ( 'pixabay' === $source ) {
			$image_path = $this->fetch_pixabay_image( $keyword, $title );
		} elseif ( 'unsplash' === $source ) {
			$image_path = $this->fetch_unsplash_image( $keyword, $title );
		}

		if ( empty( $image_path ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp_file = $is_temp ? $image_path : download_url( $image_path );

		if ( is_wp_error( $temp_file ) ) {
			return;
		}

		$file_array = [
			'name'     => sanitize_title( $title ) . '-' . uniqid() . '.jpg',
			'tmp_name' => $temp_file,
		];

		$attach_id = media_handle_sideload( $file_array, $post_id );
		wp_delete_file( $temp_file );

		if ( ! is_wp_error( $attach_id ) ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
	}

	/**
	 * Generate a DALL-E 3 image for the given title using the OpenAI provider key.
	 *
	 * Returns a local temp file path on success, or null on failure.
	 *
	 * @return string|null Local temp file path or null.
	 */
	private function generate_dalle_image( string $title ): ?string {
		$api_key = $this->core->settings->get_api_key( 'openai' );
		if ( '' === $api_key ) {
			return null;
		}

		$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
			'timeout' => 45,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body' => wp_json_encode( [
				'prompt'          => 'A high-quality featured blog image for: ' . $title,
				'n'               => 1,
				'size'            => '1024x1024',
				'quality'         => 'standard',
				'response_format' => 'url',
				'model'           => 'dall-e-3',
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$url  = $data['data'][0]['url'] ?? null;

		if ( empty( $url ) ) {
			return null;
		}

		$temp = download_url( $url );
		return is_wp_error( $temp ) ? null : $temp;
	}

	/**
	 * Fetch a relevant image URL from Pixabay.
	 *
	 * @return string|null Image URL or null.
	 */
	private function fetch_pixabay_image( string $keyword, string $title ): ?string {
		$api_key = $this->core->settings->get( 'images.pixabay_key', '' );
		if ( '' === $api_key ) {
			return null;
		}

		$terms = array_filter( array_map( 'trim', explode( ',', $keyword ) ) );
		if ( empty( $terms ) ) {
			$terms = [ $title ];
		}

		foreach ( $terms as $term ) {
			$url = add_query_arg( [
				'key'        => $api_key,
				'q'          => urlencode( $term ),
				'image_type' => 'photo',
				'safesearch' => 'true',
				'order'      => 'popular',
				'per_page'   => 5,
			], 'https://pixabay.com/api/' );

			$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $data['hits'] ) ) {
				usort( $data['hits'], fn( $a, $b ) => $b['views'] - $a['views'] );
				return $data['hits'][0]['largeImageURL'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Fetch a relevant image URL from Unsplash.
	 *
	 * @return string|null Image URL or null.
	 */
	private function fetch_unsplash_image( string $keyword, string $title ): ?string {
		$access_key = $this->core->settings->get( 'images.unsplash_key', '' );
		if ( '' === $access_key ) {
			return null;
		}

		$query    = ! empty( $keyword ) ? $keyword : $title;
		$url      = add_query_arg( [
			'query'       => urlencode( $query ),
			'per_page'    => 1,
			'orientation' => 'landscape',
		], 'https://api.unsplash.com/search/photos' );

		$response = wp_remote_get( $url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Client-ID ' . $access_key,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['results'][0]['urls']['regular'] ?? null;
	}

	// -------------------------------------------------------------------------
	// Notification.
	// -------------------------------------------------------------------------

	/**
	 * Send an email notification to the site admin about the new generated post.
	 */
	private function send_email_notification( int $post_id ): void {
		$post      = get_post( $post_id );
		$admin_email = get_option( 'admin_email' );
		$subject   = __( 'New post generated by Super Fast Blog AI', 'super-fast-blog-ai' );
		$post_url  = get_permalink( $post_id );
		$post_title = $post ? $post->post_title : '';

		$message = sprintf(
			/* translators: 1: post title, 2: post URL */
			__( 'A new post titled "%1$s" has been generated. You can view it here: %2$s', 'super-fast-blog-ai' ),
			$post_title,
			$post_url
		);

		wp_mail( $admin_email, $subject, $message );
	}

	// -------------------------------------------------------------------------
	// Text parsing helpers.
	// -------------------------------------------------------------------------

	/**
	 * Parse a line-separated AI response into a clean array of strings.
	 *
	 * Strips numbering (e.g. "1. "), removes possessive "'s", trims quotes.
	 *
	 * @param string $raw Raw AI response text.
	 * @return string[]
	 */
	private function parse_title_list( string $raw ): array {
		$lines = array_filter(
			array_map( 'trim', explode( "\n", $raw ) ),
			fn( $line ) => strlen( $line ) > 3 && ! preg_match( '/^(Here are|Certainly|Sure|Given topic)/i', $line )
		);

		return array_values( array_map( function ( $line ) {
			$line = preg_replace( '/^\d+[\.\)]\s*/', '', $line ); // Remove "1. " or "1) ".
			$line = preg_replace( '/\b(\w+)\'s\b/', '$1', $line ); // Remove possessive "'s".
			return trim( $line, '"\'` ' );
		}, $lines ) );
	}
}
