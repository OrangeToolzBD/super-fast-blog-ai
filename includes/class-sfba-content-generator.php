<?php
/**
 * Hybrid Content Generation Engine.
 *
 * Retains all SFBA content-depth features (40+ languages, 30+ writing styles,
 * 20+ tones, word count, subheadings, FAQ, TOC, pros/cons, images, scheduling)
 * and replaces hardcoded OpenAI calls with the provider-agnostic SFBA_Providers
 * factory.  Every AI call is logged in wp_sfba_generations with the appropriate
 * feature tag.
 *
 * REST endpoints (all registered via SFBA_Rest_Api):
 *   POST /generate/article    → full article generation → draft post
 *   POST /generate/outline    → article outline only
 *   POST /generate/title      → instant title from topic/keywords
 *   POST /generate/rewrite    → rewrite supplied content
 *   POST /seo/keywords        → five SEO keywords for a topic
 *   POST /seo/meta            → 160-char meta description
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Content_Generator {

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
		// Admin notice for missing SEO plugin (kept from SFBA v1).
		$loader->add_action( 'admin_notices', $this, 'show_seo_plugin_notice' );

		// REST routes.
		$this->core->rest_api->register_route( '/generate/article', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_article' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => $this->article_args(),
			],
		] );

		$this->core->rest_api->register_route( '/generate/outline', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_outline' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'title'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'keywords' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/generate/title', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_generate_title' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'topic'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'keywords' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'count'    => [ 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 20 ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/generate/rewrite', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_rewrite_content' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'content'    => [ 'type' => 'string', 'required' => true ],
					'tone'       => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'style'      => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'language'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'save_as_post' => [ 'type' => 'boolean', 'default' => false ],
					'post_title' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/seo/keywords', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_seo_keywords' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'topic' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'count' => [ 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 20 ],
				],
			],
		] );

		$this->core->rest_api->register_route( '/seo/meta', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_seo_meta' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'title'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'keywords' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Admin notice.
	// -------------------------------------------------------------------------

	/**
	 * Show a one-time notice if neither Yoast SEO nor Rank Math is active.
	 * Only shown on plugin pages.
	 */
	public function show_seo_plugin_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'super-fast-blog-ai' ) ) {
			return;
		}

		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			return;
		}

		if ( get_option( 'sfba_seo_notice_dismissed' ) ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible">';
		echo '<p>';
		esc_html_e( 'Super Fast Blog AI: Install Yoast SEO or Rank Math to automatically set SEO keywords and meta descriptions on generated posts.', 'super-fast-blog-ai' );
		echo '</p></div>';
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/**
	 * POST /generate/article
	 *
	 * Generate a full article and save it as a WordPress draft post.
	 */
	public function rest_generate_article( WP_REST_Request $request ): WP_REST_Response {
		$s = $this->core->settings;

		// Gather request params (override settings per-request where supplied).
		$title        = (string) $request->get_param( 'title' );
		$content_type = (string) ( $request->get_param( 'content_type' ) ?: $s->get( 'content.content_type', 'blog' ) );
		$keywords     = (string) $request->get_param( 'keywords' );
		$meta_desc    = (string) $request->get_param( 'meta_desc' );
		$language     = (string) ( $request->get_param( 'language' ) ?: $s->get( 'content.language', 'English' ) );
		$style        = (string) ( $request->get_param( 'writing_style' ) ?: $s->get( 'content.writing_style', 'informative' ) );
		$tone         = (string) ( $request->get_param( 'tone' ) ?: $s->get( 'content.tone', 'formal' ) );
		$word_count   = absint( $request->get_param( 'word_count' ) ?: $s->get( 'content.word_count', 500 ) );

		// Validate content_type against the shared routing type list.
		if ( ! array_key_exists( $content_type, SFBA_Model_Router::CONTENT_TYPES ) ) {
			$content_type = 'blog';
		}

		$content_opts = [
			'content_type'  => $content_type,
			'language'      => $language,
			'writing_style' => $style,
			'tone'          => $tone,
			'word_count'    => $word_count,
			'subheadings'   => (bool) $request->get_param( 'subheadings' ) ?? $s->get( 'content.subheadings', true ),
			'heading_tag'   => (string) ( $request->get_param( 'heading_tag' ) ?: $s->get( 'content.heading_tag', 'h2' ) ),
			'heading_count' => absint( $request->get_param( 'heading_count' ) ?: $s->get( 'content.heading_count', 3 ) ),
			'faq'           => (bool) ( $request->get_param( 'faq' ) ?? $s->get( 'content.faq', false ) ),
			'toc'           => (bool) ( $request->get_param( 'toc' ) ?? $s->get( 'content.toc', false ) ),
			'pros_cons'     => (bool) ( $request->get_param( 'pros_cons' ) ?? $s->get( 'content.pros_cons', false ) ),
		];

		$prompt = $this->build_article_prompt( $title, $keywords, $content_opts );

		// Budget tokens generously — optional sections can add hundreds of extra tokens.
		$base_tokens  = (int) round( $word_count * 1.8 );
		$extra_tokens = 0;
		if ( ! empty( $content_opts['faq'] ) )       $extra_tokens += 900;
		if ( ! empty( $content_opts['toc'] ) )        $extra_tokens += 250;
		if ( ! empty( $content_opts['pros_cons'] ) )  $extra_tokens += 600;
		$estimated_tokens = min( $base_tokens + $extra_tokens, 16000 );

		// Build system message — prepend brand voice profile when enabled.
		$brand_voice_snippet = $this->core->brand_voice->build_system_prompt();
		$system_message = $brand_voice_snippet
			? $brand_voice_snippet . "\n\nIn addition, you specialise in SEO. Every article must be well-structured, keyword-optimized, and SEO-friendly."
			: 'You are an expert SEO assistant that generates well-structured, keyword-optimized, and SEO-friendly content.';

		// Route via the content_type slug — resolves to the matching rule in wp_sfba_routing_rules.
		$result = $this->call_provider( $content_type, $prompt, [
			'system'     => $system_message,
			'max_tokens' => $estimated_tokens,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$content = $result['text'];
		if ( empty( $content ) ) {
			return SFBA_Rest_Api::error( 'sfba_empty_content', __( 'Generated content was empty.', 'super-fast-blog-ai' ) );
		}

		// Strip any leading <h1> the model added despite the instruction (WordPress outputs the title separately).
		$content = preg_replace( '/^\s*<h1[^>]*>.*?<\/h1>\s*/is', '', $content );

		// Insert draft post.
		$post_id = $this->insert_post( $title, $content );
		if ( is_wp_error( $post_id ) ) {
			return SFBA_Rest_Api::error( 'sfba_insert_failed', __( 'Failed to create post.', 'super-fast-blog-ai' ) );
		}

		// SEO meta.
		if ( $s->get( 'seo.keywords' ) || $s->get( 'seo.meta_desc' ) ) {
			$this->update_seo_meta( $post_id, $keywords, $meta_desc );
		}

		// Featured image.
		$this->set_featured_image( $post_id, $keywords, $title );

		// Schedule log.
		$this->insert_schedule_log( $title, $post_id, mb_strlen( $content, 'UTF-8' ), $result );

		// Email notification.
		if ( $s->get( 'publishing.email_notify' ) ) {
			$this->send_email_notification( $post_id );
		}

		return SFBA_Rest_Api::success( [
			'post_id'   => $post_id,
			'title'     => $title,
			'content'   => $content,
			'edit_url'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'model'     => $result['model'] ?? '',
			'cost_usd'  => $result['cost_usd'] ?? 0.0,
		] );
	}

	/**
	 * POST /generate/outline
	 *
	 * Generate a structured article outline.
	 */
	public function rest_generate_outline( WP_REST_Request $request ): WP_REST_Response {
		$title    = (string) $request->get_param( 'title' );
		$keywords = (string) $request->get_param( 'keywords' );

		$kw_hint = ! empty( $keywords ) ? " Focus on these keywords: {$keywords}." : '';
		$prompt  = "Create a detailed article outline for: \"{$title}\".{$kw_hint} "
			. "Use numbered sections and bullet points for sub-topics. Return only the outline, no introduction text.";

		$result = $this->call_provider( 'blog_generation', $prompt, [
			'system'     => 'You are an expert content strategist.',
			'max_tokens' => 800,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'outline' => trim( $result['text'] ) ] );
	}

	/**
	 * POST /generate/title
	 *
	 * Generate a list of title options for a topic.
	 */
	public function rest_generate_title( WP_REST_Request $request ): WP_REST_Response {
		$topic    = (string) $request->get_param( 'topic' );
		$keywords = (string) $request->get_param( 'keywords' );
		$count    = absint( $request->get_param( 'count' ) );

		$kw_hint = ! empty( $keywords ) ? " Include the keywords: {$keywords}." : '';
		$prompt  = "Generate {$count} compelling, SEO-friendly blog post titles for the topic: \"{$topic}\".{$kw_hint} "
			. "Output one title per line, with no numbering or extra text.";

		$result = $this->call_provider( 'blog_generation', $prompt, [
			'system'     => 'You are a helpful assistant.',
			'max_tokens' => 400,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$titles = array_filter(
			array_map( 'trim', explode( "\n", $result['text'] ) ),
			fn( $l ) => strlen( $l ) > 3
		);

		return SFBA_Rest_Api::success( array_values( $titles ) );
	}

	/**
	 * POST /generate/rewrite
	 *
	 * Rewrite supplied content, optionally changing tone, style, and language.
	 */
	public function rest_rewrite_content( WP_REST_Request $request ): WP_REST_Response {
		$s          = $this->core->settings;
		$content    = (string) $request->get_param( 'content' );
		$tone       = (string) ( $request->get_param( 'tone' ) ?: $s->get( 'content.tone', 'formal' ) );
		$style      = (string) ( $request->get_param( 'style' ) ?: $s->get( 'content.writing_style', 'informative' ) );
		$language   = (string) ( $request->get_param( 'language' ) ?: $s->get( 'content.language', 'English' ) );
		$save       = (bool) $request->get_param( 'save_as_post' );
		$post_title = sanitize_text_field( (string) $request->get_param( 'post_title' ) );

		$prompt = "Rewrite the following content using a {$tone} tone, {$style} writing style, in {$language}. "
			. "Preserve all key information and SEO keywords. "
			. "Do not add new sections or headers that weren't in the original.\n\n---\n\n{$content}";

		$result = $this->call_provider( 'rewrite', $prompt, [
			'system'     => 'You are an expert editor and content rewriter.',
			'max_tokens' => min( (int) ( strlen( $content ) / 3 * 2 ), 8192 ),
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$rewritten = $result['text'];
		$post_id   = null;

		if ( $save && ! empty( $post_title ) ) {
			$post_id = $this->insert_post( $post_title, $rewritten );
			if ( is_wp_error( $post_id ) ) {
				$post_id = null;
			}
		}

		return SFBA_Rest_Api::success( [
			'content' => $rewritten,
			'post_id' => $post_id,
		] );
	}

	/**
	 * POST /seo/keywords
	 *
	 * Return N SEO keyword suggestions for a topic.
	 */
	public function rest_seo_keywords( WP_REST_Request $request ): WP_REST_Response {
		$topic  = (string) $request->get_param( 'topic' );
		$count  = absint( $request->get_param( 'count' ) );
		$prompt = "Provide exactly {$count} SEO keywords, one per line (no numbering), for the topic: \"{$topic}\".";

		$result = $this->call_provider( 'seo_analysis', $prompt, [
			'system'     => 'You are a helpful SEO assistant.',
			'max_tokens' => 200,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		$keywords = array_filter(
			array_map( 'trim', explode( "\n", $result['text'] ) ),
			fn( $k ) => strlen( $k ) > 1
		);

		return SFBA_Rest_Api::success( array_values( $keywords ) );
	}

	/**
	 * POST /seo/meta
	 *
	 * Generate a 160-character meta description.
	 */
	public function rest_seo_meta( WP_REST_Request $request ): WP_REST_Response {
		$title    = (string) $request->get_param( 'title' );
		$keywords = (string) $request->get_param( 'keywords' );

		$kw_hint = ! empty( $keywords ) ? " Incorporate the keyword: \"{$keywords}\"." : '';
		$prompt  = "Write a SEO-friendly meta description (maximum 160 characters) for the blog post titled \"{$title}\".{$kw_hint} "
			. "Output only the meta description text — no labels or quotes.";

		$result = $this->call_provider( 'seo_analysis', $prompt, [
			'system'     => 'You are a helpful SEO assistant.',
			'max_tokens' => 80,
		] );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [ 'meta_description' => trim( $result['text'] ) ] );
	}

	// -------------------------------------------------------------------------
	// Prompt builder.
	// -------------------------------------------------------------------------

	/**
	 * Build a comprehensive article generation prompt from the given parameters.
	 *
	 * Incorporates all SFBA content depth features:
	 * language, tone, writing style, word count, subheadings, FAQ, TOC, pros/cons,
	 * SEO keyword placement rules.
	 *
	 * @param string $title       Post title.
	 * @param string $keywords    Comma-separated SEO keywords (may be empty).
	 * @param array  $opts        Content options array:
	 *   - language, writing_style, tone, word_count
	 *   - subheadings (bool), heading_tag, heading_count
	 *   - faq (bool), toc (bool), pros_cons (bool)
	 * @return string Full prompt string.
	 */
	public function build_article_prompt( string $title, string $keywords, array $opts ): string {
		$content_type = $opts['content_type'] ?? 'blog_post';
		$language     = $opts['language'] ?? 'English';
		$style        = $opts['writing_style'] ?? 'informative';
		$tone         = $opts['tone'] ?? 'formal';
		$word_count   = absint( $opts['word_count'] ?? 500 );

		// Human-readable content type label (sourced from the shared routing registry).
		$type_label = SFBA_Model_Router::CONTENT_TYPES[ $content_type ] ?? 'Blog / Long-form Article';

		// Per-type structural guidance injected into the prompt.
		$type_hint = match ( $content_type ) {
			'product' => 'Structure it as a compelling product description: lead with the key benefit, cover features and specs, address objections, and end with a strong CTA.',
			'social'  => 'Write short, punchy, platform-native content. Lead with a hook. Keep paragraphs to 1–2 sentences. End with an engaging call-to-action or question.',
			'meta'    => 'Write in tight, conversion-focused prose. Every sentence must add value. Include the primary keyword naturally within the first 50 words.',
			'email'   => 'Structure it as an email newsletter: a compelling subject line idea, a brief personal intro, the main value section, and a clear CTA.',
			default   => 'Structure it as a long-form blog article with an engaging introduction, well-organised H2/H3 sections, and a clear conclusion.',
		};

		$lines = [];

		// Core instruction.
		$lines[] = sprintf(
			'Generate a detailed, SEO-optimized %s titled "%s" with at least %d words.',
			$type_label,
			$title,
			$word_count
		);
		$lines[] = $type_hint;
		$lines[] = sprintf( 'Write in %s. Use a %s writing style and a %s tone.', $language, $style, $tone );

		// SEO keyword placement rules.
		$keyword_parts = array_filter( array_map( 'trim', explode( ',', $keywords ) ) );
		if ( ! empty( $keyword_parts ) ) {
			$main_kw = $keyword_parts[0];
			$lines[] = "SEO keyword rules:";
			$lines[] = "1. Include the main keyword \"{$main_kw}\" within the first 100 words.";
			$lines[] = "2. Include \"{$main_kw}\" in at least one <h2> or <h3> heading.";
			$lines[] = sprintf(
				'3. Use "%s" 8–12 times per 1000 words, placed naturally.',
				$main_kw
			);
			if ( count( $keyword_parts ) > 1 ) {
				$secondary = array_slice( $keyword_parts, 1, 4 );
				foreach ( $secondary as $i => $kw ) {
					$lines[] = sprintf( '%d. Use secondary keyword "%s" naturally (0.5%%–1%% density).', $i + 4, $kw );
				}
			}
			$lines[] = "End the article with a concluding paragraph that includes the main keyword.";
		}

		// Subheadings — driven by heading_count (no separate bool needed).
		$heading_count = absint( $opts['heading_count'] ?? 0 );
		if ( $heading_count > 0 ) {
			$tag     = sanitize_key( $opts['heading_tag'] ?? 'h2' );
			$lines[] = sprintf(
				'Structure the article with exactly %d %s subheadings. Give each heading a descriptive, keyword-rich title.',
				$heading_count,
				"<{$tag}>"
			);
		}

		// FAQ — explicit instruction to complete all Q&As before stopping.
		if ( ! empty( $opts['faq'] ) ) {
			$lines[] = 'After the main article body, add a complete FAQ section with the heading <h2>Frequently Asked Questions</h2>. '
				. 'Write exactly 5 question-and-answer pairs. Format each pair as: <strong>Q: [question]</strong> followed by <p>[detailed answer in 2–3 sentences]</p>. '
				. 'You MUST complete all 5 Q&As fully before ending your response — do not stop mid-section.';
		}

		// Table of Contents.
		if ( ! empty( $opts['toc'] ) ) {
			$lines[] = "After the introduction, insert a Table of Contents using this exact HTML: "
				. '<div style="background-color:#d7d6d5;padding:16px;border-radius:4px;">'
				. '<strong>Table of Contents</strong><ul><!-- one <li><a href="#section">Section</a></li> per heading --></ul></div>';
		}

		// Pros & Cons.
		if ( ! empty( $opts['pros_cons'] ) ) {
			$lines[] = 'Include a Pros and Cons section as an HTML table. '
				. 'Pros header: style="background-color:red;color:white;". '
				. 'Cons header: style="background-color:green;color:white;".';
		}

		// Readability rules.
		$lines[] = 'Readability requirements: no more than 10% passive sentences; no more than 25% of sentences exceed 20 words; at least 30% of sentences use transition words.';

		// HTML constraints — no H1: WordPress outputs the post title separately.
		$lines[] = 'Do NOT include an H1 tag or repeat the article title at the top of the content — WordPress displays the title automatically above the content area. Start the content directly with the introduction paragraph.'
			. ' Use only these HTML tags: <h2>, <h3>, <h4>, <p>, <ul>, <ol>, <li>, <b>, <i>, <strong>, <em>, <table>, <tr>, <th>, <td>. Do NOT use <h1>, <html>, <body>, <head>, or <article>.';

		return implode( "\n\n", $lines );
	}

	// -------------------------------------------------------------------------
	// Provider routing.
	// -------------------------------------------------------------------------

	/**
	 * Route an AI request through the provider system and log cost.
	 *
	 * @param string $content_type Feature tag used for routing + cost logging.
	 * @param string $prompt       Prompt text.
	 * @param array  $options      Additional options (system, max_tokens, temperature).
	 * @return array|WP_Error
	 */
	private function call_provider( string $content_type, string $prompt, array $options = [] ): array|WP_Error {
		$route = $this->core->providers->resolve_route( $content_type );
		if ( null === $route ) {
			return new WP_Error(
				'sfba_no_provider',
				__( 'No AI provider configured. Please add an API key in Settings.', 'super-fast-blog-ai' )
			);
		}

		$provider = $this->core->providers->get_provider( $route['provider'] );
		if ( null === $provider ) {
			return new WP_Error( 'sfba_provider_missing', __( 'Selected provider is not available.', 'super-fast-blog-ai' ) );
		}

		$options['model']       = $options['model'] ?? $route['model'];
		$options['max_tokens']  = $options['max_tokens'] ?? $route['max_tokens'];
		$options['temperature'] = $options['temperature'] ?? $route['temperature'];

		$result = $provider->generate( $prompt, $options );

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
	// Post helpers.
	// -------------------------------------------------------------------------

	/**
	 * Insert a draft post with the generated content.
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
	 * Insert a generation row into the schedule log table.
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

	// -------------------------------------------------------------------------
	// SEO meta.
	// -------------------------------------------------------------------------

	/**
	 * Write SEO meta to Yoast or Rank Math post meta fields.
	 */
	private function update_seo_meta( int $post_id, string $keyword, string $meta_desc ): void {
		$s         = $this->core->settings;
		$yoast     = 'wordpress-seo/wp-seo.php';
		$rank_math = 'seo-by-rank-math/rank-math.php';

		if ( is_plugin_active( $yoast ) ) {
			if ( $s->get( 'seo.keywords' ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
			}
			if ( $s->get( 'seo.meta_desc' ) && ! empty( $meta_desc ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
			}
		} elseif ( is_plugin_active( $rank_math ) ) {
			if ( $s->get( 'seo.keywords' ) ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
			}
			if ( $s->get( 'seo.meta_desc' ) && ! empty( $meta_desc ) ) {
				update_post_meta( $post_id, 'rank_math_description', $meta_desc );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Featured image.
	// -------------------------------------------------------------------------

	/**
	 * Set the featured image using the configured image source.
	 */
	private function set_featured_image( int $post_id, string $keyword, string $title ): void {
		$source   = $this->core->settings->get( 'images.source', 'dalle3' );
		$is_temp  = false;
		$image    = null;

		if ( 'dalle3' === $source ) {
			$image   = $this->generate_dalle_image( $title );
			$is_temp = true;
		} elseif ( 'pixabay' === $source ) {
			$image = $this->fetch_pixabay_image( $keyword, $title );
		} elseif ( 'unsplash' === $source ) {
			$image = $this->fetch_unsplash_image( $keyword, $title );
		}

		if ( empty( $image ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp = $is_temp ? $image : download_url( $image );
		if ( is_wp_error( $temp ) ) {
			return;
		}

		$file_array = [
			'name'     => sanitize_title( $title ) . '-' . uniqid() . '.jpg',
			'tmp_name' => $temp,
		];

		$attach_id = media_handle_sideload( $file_array, $post_id );
		wp_delete_file( $temp );

		if ( ! is_wp_error( $attach_id ) ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
	}

	/**
	 * Generate DALL-E 3 image; returns local temp file path or null.
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
				'prompt'          => 'A high-quality featured blog image for the article: ' . $title,
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
	 * Fetch image URL from Pixabay.
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
	 * Fetch image URL from Unsplash.
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
			'headers' => [ 'Authorization' => 'Client-ID ' . $access_key ],
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
	 * Send email notification to admin about new generated post.
	 */
	private function send_email_notification( int $post_id ): void {
		$post        = get_post( $post_id );
		$admin_email = get_option( 'admin_email' );
		$subject     = __( 'New post generated by Super Fast Blog AI', 'super-fast-blog-ai' );
		$post_url    = get_permalink( $post_id );
		$post_title  = $post ? $post->post_title : '';

		$message = sprintf(
			/* translators: 1: post title, 2: post URL */
			__( 'A new post titled "%1$s" has been generated. You can view it here: %2$s', 'super-fast-blog-ai' ),
			$post_title,
			$post_url
		);

		wp_mail( $admin_email, $subject, $message );
	}

	// -------------------------------------------------------------------------
	// REST arg definitions.
	// -------------------------------------------------------------------------

	/**
	 * Return the REST args definition for the /generate/article endpoint.
	 */
	private function article_args(): array {
		return [
			'title'         => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
			'content_type'  => [ 'type' => 'string', 'default' => 'blog', 'sanitize_callback' => 'sanitize_key', 'enum' => array_keys( SFBA_Model_Router::CONTENT_TYPES ) ],
			'keywords'      => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'meta_desc'     => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'language'      => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'writing_style' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'tone'          => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
			'word_count'    => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 10000 ],
			'subheadings'   => [ 'type' => 'boolean', 'default' => null ],
			'heading_tag'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key' ],
			'heading_count' => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'maximum' => 20 ],
			'faq'           => [ 'type' => 'boolean', 'default' => null ],
			'toc'           => [ 'type' => 'boolean', 'default' => null ],
			'pros_cons'     => [ 'type' => 'boolean', 'default' => null ],
		];
	}
}
