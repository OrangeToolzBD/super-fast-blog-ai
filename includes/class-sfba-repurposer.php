<?php
/**
 * Content Repurposing Pipeline — MODULE 7.
 *
 * Takes a published or draft WordPress post and transforms it into platform-
 * optimised content pieces in one click.
 *
 * Supported platforms:
 *   twitter    → { thread: string[], single: string, char_counts: int[] }
 *   linkedin   → { post: string, hashtags: string[], word_count: int }
 *   email      → { subject, preview_text, body_html, cta_text }
 *   facebook   → { post: string, word_count: int }
 *   instagram  → { caption: string, hashtags: string[], char_count: int }
 *   youtube    → { title, description, script_outline: string[] }
 *
 * REST surface:
 *   POST /repurpose              — all platforms in one call
 *   POST /repurpose/twitter      — twitter only
 *   POST /repurpose/linkedin     — linkedin only
 *   POST /repurpose/email        — email only
 *   POST /repurpose/facebook     — facebook only
 *   POST /repurpose/instagram    — instagram only
 *   POST /repurpose/youtube      — youtube only
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFBA_Repurposer
 */
class SFBA_Repurposer {

	// -------------------------------------------------------------------------
	// Platform registry.
	// -------------------------------------------------------------------------

	/** @var array<string, string> */
	public const PLATFORMS = [
		'twitter'   => 'Twitter / X Thread',
		'linkedin'  => 'LinkedIn Post',
		'email'     => 'Email Newsletter Excerpt',
		'facebook'  => 'Facebook Post',
		'instagram' => 'Instagram Caption',
		'youtube'   => 'YouTube Script Outline',
	];

	// -------------------------------------------------------------------------
	// Character / word limits.
	// -------------------------------------------------------------------------

	private const TWITTER_MAX_CHARS   = 280;
	private const TWITTER_MIN_TWEETS  = 5;
	private const TWITTER_MAX_TWEETS  = 10;
	private const LINKEDIN_MIN_WORDS  = 150;
	private const LINKEDIN_MAX_WORDS  = 300;
	private const LINKEDIN_HASHTAGS   = 5;
	private const EMAIL_SUBJECT_MAX   = 50;
	private const EMAIL_PREVIEW_MAX   = 90;
	private const INSTAGRAM_MAX_CHARS = 2200;
	private const INSTAGRAM_HASHTAGS  = 30;

	/** @var SFBA_Core */
	private SFBA_Core $core;

	/**
	 * Hashtag language preference for the current request.
	 * Values: 'english' | 'source' | 'both'
	 *
	 * @var string
	 */
	private string $hashtag_lang = 'english';

	/**
	 * Detected language of the source content (e.g. "English", "Bengali").
	 * Set before any prompt builder is called.
	 *
	 * @var string
	 */
	private string $content_language = 'English';

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
		// Shared args for all repurpose routes.
		$repurpose_args = [
			'post_id'      => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
			'content'      => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'wp_kses_post' ],
			'hashtag_lang' => [ 'type' => 'string',  'required' => false, 'default' => 'english', 'enum' => [ 'english', 'source', 'both' ] ],
		];

		// POST /repurpose — all platforms in a single call.
		$this->core->rest_api->register_route( '/repurpose', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_repurpose' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => array_merge( $repurpose_args, [
					'platforms' => [
						'type'     => 'array',
						'required' => false,
						'default'  => array_keys( self::PLATFORMS ),
						'items'    => [ 'type' => 'string', 'enum' => array_keys( self::PLATFORMS ) ],
					],
				] ),
			],
		] );

		// Per-platform routes — POST /repurpose/{platform}.
		foreach ( array_keys( self::PLATFORMS ) as $platform ) {
			$this->core->rest_api->register_route( '/repurpose/' . $platform, [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'rest_platform_' . $platform ],
					'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
					'args'                => $repurpose_args,
				],
			] );
		}

		$loader->add_action( 'admin_menu', $this, 'register_admin_menu' );
	}

	// -------------------------------------------------------------------------
	// Admin menu.
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void {
		add_submenu_page(
			'super-fast-blog-ai',
			__( 'Repurpose Content — Super Fast Blog AI', 'super-fast-blog-ai' ),
			__( 'Repurpose Content', 'super-fast-blog-ai' ),
			'manage_options',
			'sfba-repurpose',
			[ $this, 'render_repurpose_page' ]
		);
	}

	public function render_repurpose_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'super-fast-blog-ai' ) );
		}

		$api_base     = rest_url( SFBA_Rest_Api::NAMESPACE );
		$nonce        = wp_create_nonce( 'wp_rest' );
		$platforms    = self::PLATFORMS;
		$recent_posts = get_posts( [
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		include SFBA_PLUGIN_DIR . 'includes/admin/page-repurpose.php';
	}

	// -------------------------------------------------------------------------
	// REST callbacks — multi-platform.
	// -------------------------------------------------------------------------

	/** POST /repurpose */
	public function rest_repurpose( WP_REST_Request $request ): WP_REST_Response {
		$post_id   = (int) $request->get_param( 'post_id' );
		$content   = trim( (string) $request->get_param( 'content' ) );
		$platforms = (array) $request->get_param( 'platforms' );

		$this->hashtag_lang = sanitize_key( $request->get_param( 'hashtag_lang' ) ?: 'english' );

		if ( $post_id <= 0 && '' === $content ) {
			return SFBA_Rest_Api::error( 'sfba_invalid_input', __( 'Please select a post or paste content.', 'super-fast-blog-ai' ), 400 );
		}

		$result = $this->repurpose( $post_id, $platforms, $content );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	// -------------------------------------------------------------------------
	// REST callbacks — per-platform.
	// -------------------------------------------------------------------------

	/** POST /repurpose/twitter */
	public function rest_platform_twitter( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'twitter' );
	}

	/** POST /repurpose/linkedin */
	public function rest_platform_linkedin( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'linkedin' );
	}

	/** POST /repurpose/email */
	public function rest_platform_email( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'email' );
	}

	/** POST /repurpose/facebook */
	public function rest_platform_facebook( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'facebook' );
	}

	/** POST /repurpose/instagram */
	public function rest_platform_instagram( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'instagram' );
	}

	/** POST /repurpose/youtube */
	public function rest_platform_youtube( WP_REST_Request $request ): WP_REST_Response {
		return $this->rest_single_platform( $request, 'youtube' );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Generate repurposed content for one or more platforms.
	 *
	 * @param int      $post_id   Pass 0 when using pasted $content.
	 * @param string[] $platforms
	 * @param string   $content   Pasted content; used when $post_id is 0.
	 * @return array|WP_Error
	 */
	public function repurpose( int $post_id, array $platforms = [], string $content = '' ): array|WP_Error {
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				return new WP_Error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), [ 'status' => 404 ] );
			}
			$source     = $this->extract_source( $post );
			$post_title = $post->post_title;
			$url        = (string) get_permalink( $post_id );
		} else {
			$source     = $this->extract_source_from_content( $content );
			$post       = null;
			$post_title = '';
			$url        = '';
		}

		// Set content language once for all prompt builders.
		$this->content_language = $source['language'] ?? 'English';

		if ( empty( $platforms ) ) {
			$platforms = array_keys( self::PLATFORMS );
		} else {
			$platforms = array_values( array_filter(
				$platforms,
				fn( $p ) => array_key_exists( $p, self::PLATFORMS )
			) );
		}

		if ( empty( $platforms ) ) {
			return new WP_Error( 'sfba_invalid_input', __( 'No valid platforms specified.', 'super-fast-blog-ai' ) );
		}

		$results = [];

		foreach ( $platforms as $platform ) {
			$results[ $platform ] = $this->generate_platform( $platform, $source, $post );
		}

		return [
			'post_id'             => $post_id,
			'post_title'          => $post_title,
			'url'                 => $url,
			'platforms_requested' => $platforms,
			'results'             => $results,
		];
	}

	/**
	 * Generate a Twitter / X thread from a blog post.
	 *
	 * @param string   $content
	 * @param string[] $key_points
	 * @param string   $url
	 * @param int      $post_id
	 * @return array|WP_Error
	 */
	public function generate_twitter_thread( string $content, array $key_points = [], string $url = '', int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_twitter( $content, $key_points, $url );
		$gen    = $this->call_ai( $prompt, 'social', 800, 0.8, $post_id, 'repurpose_twitter' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_twitter( $gen['text'], $url, $gen['mock'] );
	}

	/**
	 * Generate an email newsletter excerpt from a blog post.
	 *
	 * @param string $content
	 * @param string $url
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	public function generate_email_newsletter( string $content, string $url = '', int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_email( $content, $url );
		$gen    = $this->call_ai( $prompt, 'email', 600, 0.7, $post_id, 'repurpose_email' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_email( $gen['text'], $url, $gen['mock'] );
	}

	/**
	 * Generate a LinkedIn post from a blog post.
	 *
	 * @param string $content
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	public function generate_linkedin_post( string $content, int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_linkedin( $content );
		$gen    = $this->call_ai( $prompt, 'social', 500, 0.75, $post_id, 'repurpose_linkedin' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_linkedin( $gen['text'], $gen['mock'] );
	}

	/**
	 * Generate a Facebook post from a blog post.
	 *
	 * @param string $content
	 * @param string $url
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	public function generate_facebook_post( string $content, string $url = '', int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_facebook( $content, $url );
		$gen    = $this->call_ai( $prompt, 'social', 350, 0.8, $post_id, 'repurpose_facebook' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_facebook( $gen['text'], $gen['mock'] );
	}

	/**
	 * Generate an Instagram caption from a blog post.
	 *
	 * @param string $content
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	public function generate_instagram_caption( string $content, int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_instagram( $content );
		$gen    = $this->call_ai( $prompt, 'social', 400, 0.85, $post_id, 'repurpose_instagram' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_instagram( $gen['text'], $gen['mock'] );
	}

	/**
	 * Generate a YouTube video script outline from a blog post.
	 *
	 * @param string $content
	 * @param string $post_title
	 * @param string $url
	 * @param int    $post_id
	 * @return array|WP_Error
	 */
	public function generate_youtube_outline( string $content, string $post_title = '', string $url = '', int $post_id = 0 ): array|WP_Error {
		$prompt = $this->prompt_youtube( $content, $post_title, $url );
		$gen    = $this->call_ai( $prompt, 'social', 700, 0.7, $post_id, 'repurpose_youtube' );

		if ( is_wp_error( $gen ) ) {
			return $gen;
		}

		return $this->parse_youtube( $gen['text'], $post_title, $url, $gen['mock'] );
	}

	// -------------------------------------------------------------------------
	// Prompt builders.
	// -------------------------------------------------------------------------

	/**
	 * Return a hashtag language instruction sentence based on the current preference.
	 */
	private function hashtag_lang_instruction(): string {
		return match ( $this->hashtag_lang ) {
			'source' => "Hashtags must be written in {$this->content_language} (NOT in English).",
			'both'   => "Include hashtags in BOTH English AND {$this->content_language} (mix them).",
			default  => 'Hashtags must be in English.',
		};
	}

	/**
	 * Detect the primary language of a plain-text string using Unicode script ranges.
	 *
	 * For non-Latin scripts the detection is reliable (character set is unique).
	 * Latin-based languages (English, Spanish, French, Portuguese, etc.) all share
	 * the same character range, so we fall back to checking common stopwords to
	 * distinguish them; otherwise we default to "English".
	 *
	 * @param string $text Plain text sample (first 600 chars used).
	 * @return string Human-readable language name (e.g. "English", "Bengali").
	 */
	private function detect_language( string $text ): string {
		$sample = mb_substr( $text, 0, 600 );

		$scripts = [
			'Bengali'   => '/[\x{0980}-\x{09FF}]/u',
			'Arabic'    => '/[\x{0600}-\x{06FF}]/u',
			'Hindi'     => '/[\x{0900}-\x{097F}]/u',
			'Chinese'   => '/[\x{4E00}-\x{9FFF}]/u',
			'Japanese'  => '/[\x{3040}-\x{30FF}]/u',
			'Korean'    => '/[\x{AC00}-\x{D7AF}]/u',
			'Thai'      => '/[\x{0E00}-\x{0E7F}]/u',
			'Russian'   => '/[\x{0400}-\x{04FF}]/u',
			'Greek'     => '/[\x{0370}-\x{03FF}]/u',
			'Hebrew'    => '/[\x{0590}-\x{05FF}]/u',
		];

		foreach ( $scripts as $lang => $pattern ) {
			if ( preg_match_all( $pattern, $sample ) > 8 ) {
				return $lang;
			}
		}

		// Latin script — try to distinguish common European languages via stopwords.
		$lower = mb_strtolower( $sample );
		$latin_hints = [
			'Spanish'    => [ ' de ', ' la ', ' el ', ' en ', ' que ', ' los ', ' una ', ' por ' ],
			'French'     => [ ' de ', ' le ', ' la ', ' les ', ' est ', ' une ', ' des ', ' pas ' ],
			'Portuguese' => [ ' de ', ' da ', ' do ', ' em ', ' que ', ' não ', ' para ', ' uma ' ],
			'German'     => [ ' die ', ' der ', ' und ', ' ist ', ' ein ', ' nicht ', ' auch ', ' mit ' ],
			'Italian'    => [ ' di ', ' la ', ' il ', ' che ', ' per ', ' una ', ' del ', ' non ' ],
			'Dutch'      => [ ' de ', ' het ', ' een ', ' van ', ' en ', ' dat ', ' niet ', ' zijn ' ],
		];

		$scores = [];
		foreach ( $latin_hints as $lang => $words ) {
			$scores[ $lang ] = 0;
			foreach ( $words as $w ) {
				$scores[ $lang ] += substr_count( $lower, $w );
			}
		}

		// Only trust the detection if the winner has a significant lead.
		arsort( $scores );
		$top   = array_keys( $scores )[0]   ?? 'English';
		$score = array_values( $scores )[0]  ?? 0;
		$second = array_values( $scores )[1] ?? 0;

		if ( $score >= 4 && ( $score - $second ) >= 2 ) {
			return $top;
		}

		return 'English';
	}

	private function prompt_twitter( string $content, array $key_points, string $url ): string {
		$excerpt    = $this->safe_excerpt( $content, 800 );
		$kp_section = '';
		if ( ! empty( $key_points ) ) {
			$kp_section = "\nKey points to cover:\n- " . implode( "\n- ", $key_points ) . "\n";
		}
		$cta_note = '' !== $url ? "Full article URL for the last tweet's CTA: {$url}" : '';

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Convert this article into a Twitter/X thread.',
			'',
			'Rules:',
			'1. First tweet: a curiosity-inducing hook — do not reveal the conclusion.',
			'2. Each tweet must be ≤280 characters.',
			'3. Use short lines and line breaks for readability.',
			'4. Last tweet: a CTA inviting readers to read the full article.',
			'5. Total tweets: 5–10.',
			'6. No hashtags in the body tweets. 2–3 hashtags on the last tweet only.',
			'7. Hashtag rule: ' . $this->hashtag_lang_instruction(),
			'',
			$cta_note,
			$kp_section,
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return ONLY a numbered list of tweets, one per line (1. … 2. … etc). No extra commentary.',
		] );
	}

	private function prompt_email( string $content, string $url ): string {
		$excerpt  = $this->safe_excerpt( $content, 600 );
		$cta_note = '' !== $url ? "Full article URL: {$url}" : '';

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Create an email newsletter excerpt from this article.',
			'',
			'Include ALL of these elements:',
			'- Subject line: max 50 characters, compelling, creates curiosity.',
			'- Preview text: max 90 characters, complements the subject line.',
			'- Body: exactly 2 short paragraphs (3–4 sentences each) highlighting the key takeaway.',
			'  Tone: conversational, creates FOMO to click through and read more.',
			'- CTA button text: 3–6 words.',
			'',
			$cta_note,
			'',
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return as JSON with keys: subject, preview_text, body_html (use <p> tags), cta_text.',
		] );
	}

	private function prompt_linkedin( string $content ): string {
		$excerpt = $this->safe_excerpt( $content, 600 );

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Convert this article into a LinkedIn post.',
			'',
			'Rules:',
			'1. Open with a bold statement or a thought-provoking question.',
			'2. Use short paragraphs — maximum 2 sentences each.',
			'3. Include a personal perspective or lesson learned from the topic.',
			'4. End with an open question to encourage comments.',
			'5. Length: 150–300 words.',
			'6. Add 3–5 relevant hashtags at the very end on a new line.',
			'7. Hashtag rule: ' . $this->hashtag_lang_instruction(),
			'',
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return ONLY the formatted LinkedIn post text (no JSON, no commentary).',
		] );
	}

	private function prompt_facebook( string $content, string $url ): string {
		$excerpt  = $this->safe_excerpt( $content, 500 );
		$cta_note = '' !== $url ? "Link: {$url}" : '';

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Write a Facebook post promoting this article.',
			'',
			'Rules:',
			'1. Start with an attention-grabbing hook sentence.',
			'2. 2–3 sentences of context or key insight from the article.',
			'3. Casual, friendly tone — like you\'re sharing with a friend.',
			'4. End with a soft CTA inviting readers to check it out.',
			'5. Under 250 words.',
			'6. No hashtags.',
			'',
			$cta_note,
			'',
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return ONLY the post text. No labels, no commentary.',
		] );
	}

	private function prompt_instagram( string $content ): string {
		$excerpt = $this->safe_excerpt( $content, 400 );

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Write an Instagram caption based on this article.',
			'',
			'Rules:',
			'1. First line: a visual hook (shown before the "more" fold). Max 125 characters.',
			'2. 3–5 engaging sentences expanding on the hook.',
			'3. Soft CTA at the end (e.g. "Link in bio" — write this in the detected language).',
			'4. Leave one blank line, then add 15–25 relevant hashtags.',
			'5. Total caption ≤2200 characters.',
			'6. Hashtag rule: ' . $this->hashtag_lang_instruction(),
			'',
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return as JSON with keys: caption (text without hashtags), hashtags (array of strings without #).',
		] );
	}

	private function prompt_youtube( string $content, string $post_title, string $url ): string {
		$excerpt    = $this->safe_excerpt( $content, 700 );
		$title_note = '' !== $post_title ? "Article title: {$post_title}" : '';
		$url_note   = '' !== $url ? "Blog post URL (for description): {$url}" : '';

		return implode( "\n", [
			'IMPORTANT: This article is written in ' . $this->content_language . '. Write your ENTIRE response in ' . $this->content_language . '. Do not translate into any other language.',
			'',
			'Create a YouTube video script outline based on this article.',
			'',
			$title_note,
			$url_note,
			'',
			'Include:',
			'1. Video title: SEO-optimised, under 70 characters, includes primary keyword.',
			'2. Video description: 150–200 words. Include keyword naturally, link to blog post, and timestamps.',
			'3. Script outline: 5–8 sections with timestamps (e.g. 0:00, 1:30, 3:00).',
			'   Each section has a heading and 3–4 talking points as bullet points.',
			'   Include an intro hook, main content sections, and an outro with CTA.',
			'',
			'Article content:',
			'---',
			$excerpt,
			'---',
			'',
			'Return as JSON: { title, description, script_outline: [ { timestamp, section, talking_points: [] } ] }.',
		] );
	}

	// -------------------------------------------------------------------------
	// Response parsers.
	// -------------------------------------------------------------------------

	private function parse_twitter( string $text, string $url, bool $is_mock ): array {
		$json = $this->try_json_decode( $text );
		if ( is_array( $json ) && isset( $json[0] ) ) {
			$tweets = array_map( 'sanitize_textarea_field', $json );
		} else {
			$tweets = $this->parse_numbered_list( $text );
		}

		$tweets = array_map( function ( $tweet ) {
			return mb_substr( trim( $tweet ), 0, self::TWITTER_MAX_CHARS );
		}, $tweets );

		$tweets = array_values( array_filter( $tweets, fn( $t ) => '' !== $t ) );

		if ( count( $tweets ) < self::TWITTER_MIN_TWEETS ) {
			$tweets = $this->mock_twitter_thread( $url, $is_mock );
		}

		$char_counts = array_map( 'mb_strlen', $tweets );

		return [
			'thread'      => $tweets,
			'single'      => $tweets[0] ?? '',
			'char_counts' => $char_counts,
			'tweet_count' => count( $tweets ),
			'mock'        => $is_mock,
		];
	}

	private function parse_email( string $text, string $url, bool $is_mock ): array {
		$json         = $this->try_json_decode( $text );
		$subject      = sanitize_text_field( $json['subject']      ?? '' );
		$preview_text = sanitize_text_field( $json['preview_text'] ?? '' );
		$body_html    = wp_kses_post( $json['body_html']           ?? '' );
		$cta_text     = sanitize_text_field( $json['cta_text']     ?? '' );

		$subject      = mb_substr( $subject,      0, self::EMAIL_SUBJECT_MAX );
		$preview_text = mb_substr( $preview_text, 0, self::EMAIL_PREVIEW_MAX );

		if ( '' === $subject ) {
			return $this->mock_email( $url, $is_mock );
		}

		return [
			'subject'      => $subject,
			'preview_text' => $preview_text,
			'body_html'    => $body_html ?: wpautop( $this->safe_excerpt( $text, 300 ) ),
			'cta_text'     => $cta_text ?: __( 'Read the full article →', 'super-fast-blog-ai' ),
			'cta_url'      => $url,
			'mock'         => $is_mock,
		];
	}

	private function parse_linkedin( string $text, bool $is_mock ): array {
		if ( $is_mock ) {
			return $this->mock_linkedin( true );
		}

		$text      = trim( $text );
		$lines     = explode( "\n", $text );
		$last_line = trim( end( $lines ) );
		$hashtags  = [];

		if ( str_contains( $last_line, '#' ) ) {
			preg_match_all( '/#([\w]+)/u', $last_line, $matches );
			$hashtags = $matches[1] ?? [];
			array_pop( $lines );
			$text = trim( implode( "\n", $lines ) );
		}

		$hashtags   = array_slice( $hashtags, 0, self::LINKEDIN_HASHTAGS );
		$word_count = str_word_count( $text );

		if ( $word_count < 20 ) {
			return $this->mock_linkedin( $is_mock );
		}

		return [
			'post'       => $text,
			'hashtags'   => $hashtags,
			'word_count' => $word_count,
			'mock'       => false,
		];
	}

	private function parse_facebook( string $text, bool $is_mock ): array {
		if ( $is_mock ) {
			return $this->mock_facebook( true );
		}

		$text       = trim( $text );
		$word_count = str_word_count( $text );

		if ( $word_count < 10 ) {
			return $this->mock_facebook( $is_mock );
		}

		return [
			'post'       => $text,
			'word_count' => $word_count,
			'mock'       => false,
		];
	}

	private function parse_instagram( string $text, bool $is_mock ): array {
		if ( $is_mock ) {
			return $this->mock_instagram( true );
		}

		$json     = $this->try_json_decode( $text );
		$caption  = '';
		$hashtags = [];

		if ( is_array( $json ) && isset( $json['caption'] ) ) {
			$caption  = sanitize_textarea_field( $json['caption'] );
			$hashtags = array_map(
				fn( $h ) => ltrim( sanitize_text_field( $h ), '#' ),
				(array) ( $json['hashtags'] ?? [] )
			);
		} else {
			$parts         = preg_split( '/\n\s*\n/', $text, 2 );
			$caption       = trim( $parts[0] ?? $text );
			$hashtag_block = $parts[1] ?? '';

			preg_match_all( '/#([\w]+)/u', $hashtag_block ?: $caption, $matches );
			$hashtags = $matches[1] ?? [];
			$caption  = trim( preg_replace( '/#[\w]+/u', '', $caption ) );
		}

		$hashtags   = array_values( array_unique( array_slice( $hashtags, 0, self::INSTAGRAM_HASHTAGS ) ) );
		$char_count = mb_strlen( $caption );

		if ( '' === $caption ) {
			return $this->mock_instagram( $is_mock );
		}

		return [
			'caption'    => mb_substr( $caption, 0, self::INSTAGRAM_MAX_CHARS ),
			'hashtags'   => $hashtags,
			'char_count' => $char_count,
			'mock'       => $is_mock,
		];
	}

	private function parse_youtube( string $text, string $post_title, string $url, bool $is_mock ): array {
		$json           = $this->try_json_decode( $text );
		$title          = sanitize_text_field( $json['title']       ?? '' );
		$description    = sanitize_textarea_field( $json['description'] ?? '' );
		$script_outline = [];

		if ( isset( $json['script_outline'] ) && is_array( $json['script_outline'] ) ) {
			foreach ( $json['script_outline'] as $section ) {
				$script_outline[] = [
					'timestamp'      => sanitize_text_field( $section['timestamp']     ?? '0:00' ),
					'section'        => sanitize_text_field( $section['section']       ?? '' ),
					'talking_points' => array_map( 'sanitize_text_field', (array) ( $section['talking_points'] ?? [] ) ),
				];
			}
		}

		if ( '' === $title || empty( $script_outline ) ) {
			return $this->mock_youtube( $post_title, $url, $is_mock );
		}

		return [
			'title'          => mb_substr( $title, 0, 70 ),
			'description'    => $description,
			'script_outline' => $script_outline,
			'mock'           => $is_mock,
		];
	}

	// -------------------------------------------------------------------------
	// Mock responses.
	// -------------------------------------------------------------------------

	private function mock_twitter_thread( string $url, bool $is_mock ): array {
		$cta = '' !== $url ? "Read the full article: {$url} #content #writing" : 'Check the full article for more. #content #writing';

		return [
			'Most people get this completely wrong. Here\'s what nobody tells you about content repurposing 🧵',
			'1/ You\'re leaving traffic on the table every time you publish a blog post without repurposing it.',
			'2/ A single article can become: a Twitter thread, a LinkedIn post, an email newsletter, an Instagram caption, and a YouTube outline.',
			'3/ The key is platform-native formatting. Not copy-paste. Each piece adapted to how that audience consumes content.',
			'4/ Twitter/X: short punchy insights. Hashtags only on the last tweet.',
			'5/ LinkedIn: professional tone, personal angle, ends with a question.',
			'6/ Email: subject line creates FOMO. Body = 2 paragraphs. CTA = the click.',
			'7/ Instagram: visual hook first line. Hashtag block at the bottom.',
			'8/ YouTube: turn the article sections into a script outline with timestamps.',
			"9/ [Mock response] Configure an AI provider in Super Fast Blog AI → Settings to generate real threads. {$cta}",
		];
	}

	private function mock_email( string $url, bool $is_mock ): array {
		return [
			'subject'      => 'You\'re missing out on this →',
			'preview_text' => 'One article, six content pieces. Here\'s how.',
			'body_html'    => '<p>Most content creators write a blog post and call it a day. But your article can work ten times harder with one extra step: repurposing.</p><p>We\'ve just published a guide that breaks down exactly how to transform a single article into Twitter threads, LinkedIn posts, email excerpts, and more — all in minutes. [Mock response: configure an AI provider to generate real email content.]</p>',
			'cta_text'     => 'Read the full guide →',
			'cta_url'      => $url,
			'mock'         => $is_mock,
		];
	}

	private function mock_linkedin( bool $is_mock ): array {
		$post = implode( "\n\n", [
			'Most marketers treat their blog like a one-shot channel. It doesn\'t have to be.',
			'Every article you publish is raw material for six platform-native content pieces.',
			'I learned this the hard way — spending hours manually reformatting blog content for Twitter, LinkedIn, and email.',
			'Then I automated the whole pipeline.',
			'Now I write once and distribute everywhere — in the time it used to take me to write one platform post.',
			'The key insight: each platform has its own native format. Don\'t copy-paste. Adapt.',
			'What\'s your biggest content distribution bottleneck?',
			'[Mock response — configure an AI provider in Super Fast Blog AI → Settings for real content.]',
		] );

		return [
			'post'       => $post,
			'hashtags'   => [ 'ContentMarketing', 'Marketing', 'ContentStrategy', 'SocialMedia' ],
			'word_count' => str_word_count( $post ),
			'mock'       => $is_mock,
		];
	}

	private function mock_facebook( bool $is_mock ): array {
		$post = "Did you know one blog post can give you six pieces of content?\n\nMost people write an article and move on. But with the right workflow you can turn that same post into a Twitter thread, a LinkedIn update, an email newsletter, an Instagram caption, and a YouTube outline.\n\nWe just wrote about exactly how to do this. Check out the link to read the full breakdown.\n\n[Mock response — configure an AI provider in Super Fast Blog AI → Settings for real content.]";

		return [
			'post'       => $post,
			'word_count' => str_word_count( $post ),
			'mock'       => $is_mock,
		];
	}

	private function mock_instagram( bool $is_mock ): array {
		$caption = "One blog post. Six pieces of content. ✨\n\nStop leaving content on the table. Every article you publish is raw material for your entire content ecosystem.\n\nRepurpose smarter — not harder. Link in bio to read the full guide.\n\n[Mock response — configure an AI provider in Super Fast Blog AI → Settings for real captions.]";

		return [
			'caption'    => $caption,
			'hashtags'   => [ 'ContentMarketing', 'ContentCreator', 'SocialMediaMarketing', 'Blogging', 'MarketingTips', 'ContentStrategy', 'DigitalMarketing', 'OnlineMarketing' ],
			'char_count' => mb_strlen( $caption ),
			'mock'       => $is_mock,
		];
	}

	private function mock_youtube( string $post_title, string $url, bool $is_mock ): array {
		$title = $post_title ? "How To: {$post_title} (Step-by-Step Guide)" : 'How To Repurpose Blog Content Into 6 Content Pieces';

		return [
			'title'          => mb_substr( $title, 0, 70 ),
			'description'    => "In this video, we break down exactly how to turn one blog post into six platform-native content pieces — saving you hours every week.\n\n⏱ Timestamps below.\n\n📖 Full article: {$url}\n\n[Mock response — configure an AI provider in Super Fast Blog AI → Settings for real YouTube outlines.]",
			'script_outline' => [
				[ 'timestamp' => '0:00',  'section' => 'Hook & Intro',          'talking_points' => [ 'The problem: writing the same content multiple times', 'What we\'ll cover today', 'Quick win teaser' ] ],
				[ 'timestamp' => '1:30',  'section' => 'Why Repurposing Works',  'talking_points' => [ 'Platform-native formats vs copy-paste', 'Time saved with a repurposing system', 'Real traffic impact' ] ],
				[ 'timestamp' => '3:45',  'section' => 'Twitter Thread Method',  'talking_points' => [ 'Hook tweet formula', 'Thread structure', 'CTA tweet template' ] ],
				[ 'timestamp' => '6:00',  'section' => 'LinkedIn + Email',       'talking_points' => [ 'LinkedIn opener formula', 'Email subject line tricks', 'Body + CTA format' ] ],
				[ 'timestamp' => '9:15',  'section' => 'Instagram + YouTube',    'talking_points' => [ 'Caption above the fold', 'Hashtag strategy', 'YouTube description template' ] ],
				[ 'timestamp' => '12:00', 'section' => 'Outro & CTA',            'talking_points' => [ 'Recap of the system', 'Subscribe for more', 'Link in description' ] ],
			],
			'mock' => $is_mock,
		];
	}

	// -------------------------------------------------------------------------
	// AI call helper.
	// -------------------------------------------------------------------------

	/**
	 * Call the AI provider for a repurposing task.
	 *
	 * Routes via SFBA_Providers::resolve_route() for the given content_type,
	 * falls back to mock on no-provider. Returns array{text, mock, tokens_used,
	 * prompt_tokens, completion_tokens} or WP_Error.
	 *
	 * @param string $prompt
	 * @param string $content_type  'social' | 'email' | 'blog'
	 * @param int    $max_tokens
	 * @param float  $temperature
	 * @param int    $post_id
	 * @param string $feature       Cost tracker feature tag.
	 * @return array|WP_Error
	 */
	private function call_ai( string $prompt, string $content_type, int $max_tokens, float $temperature, int $post_id, string $feature ): array|WP_Error {
		$route = $this->core->providers->resolve_route( $content_type );

		// No provider → return mock.
		if ( null === $route ) {
			return [
				'text'              => '',
				'mock'              => true,
				'tokens_used'       => 0,
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
			];
		}

		$provider = $this->core->providers->get_provider( $route['provider'] );
		if ( null === $provider ) {
			return [
				'text'              => '',
				'mock'              => true,
				'tokens_used'       => 0,
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
			];
		}

		$start_ms = (int) round( microtime( true ) * 1000 );

		$gen = $provider->generate( $prompt, [
			'model'       => $route['model'],
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
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
			'post_id'            => $post_id,
			'provider'           => $route['provider'],
			'model'              => $route['model'],
			'prompt_tokens'      => (int) ( $gen['prompt_tokens'] ?? 0 ),
			'completion_tokens'  => (int) ( $gen['completion_tokens'] ?? $tokens ),
			'cost_usd'           => $cost,
			'feature'            => $feature,
			'generation_time_ms' => $elapsed,
		] );

		return [
			'text'              => $gen['text'] ?? '',
			'mock'              => false,
			'tokens_used'       => $tokens,
			'prompt_tokens'     => (int) ( $gen['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $gen['completion_tokens'] ?? 0 ),
		];
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Extract source material from a post for all generators.
	 *
	 * @return array{title: string, plain: string, excerpt: string, url: string, key_points: string[]}
	 */
	private function extract_source( WP_Post $post ): array {
		$plain      = $this->strip_to_plain( $post->post_content );
		$excerpt    = wp_trim_words( $plain, 80 );
		$key_points = $this->extract_key_points( $post->post_content );

		return [
			'title'      => $post->post_title,
			'plain'      => $plain,
			'excerpt'    => $excerpt,
			'url'        => (string) get_permalink( $post->ID ),
			'key_points' => $key_points,
			'language'   => $this->detect_language( $plain ),
		];
	}

	/**
	 * Dispatch to the correct platform generator.
	 *
	 * @param string       $platform
	 * @param array        $source
	 * @param WP_Post|null $post  Null when repurposing pasted content (no post).
	 * @return array|WP_Error
	 */
	private function generate_platform( string $platform, array $source, ?WP_Post $post ): array|WP_Error {
		$post_id = $post instanceof WP_Post ? $post->ID : 0;

		return match ( $platform ) {
			'twitter'   => $this->generate_twitter_thread( $source['plain'], $source['key_points'], $source['url'], $post_id ),
			'linkedin'  => $this->generate_linkedin_post( $source['plain'], $post_id ),
			'email'     => $this->generate_email_newsletter( $source['plain'], $source['url'], $post_id ),
			'facebook'  => $this->generate_facebook_post( $source['plain'], $source['url'], $post_id ),
			'instagram' => $this->generate_instagram_caption( $source['plain'], $post_id ),
			'youtube'   => $this->generate_youtube_outline( $source['plain'], $source['title'], $source['url'], $post_id ),
			default     => new WP_Error( 'sfba_invalid_platform', sprintf( __( 'Unknown platform: %s', 'super-fast-blog-ai' ), $platform ) ),
		};
	}

	/**
	 * Build a source array from raw pasted content (no WP_Post available).
	 *
	 * @return array{title: string, plain: string, excerpt: string, url: string, key_points: string[]}
	 */
	private function extract_source_from_content( string $content ): array {
		$plain      = $this->strip_to_plain( $content );
		$excerpt    = wp_trim_words( $plain, 80 );
		$key_points = $this->extract_key_points( $content );

		return [
			'title'      => '',
			'plain'      => $plain,
			'excerpt'    => $excerpt,
			'url'        => '',
			'key_points' => $key_points,
			'language'   => $this->detect_language( $plain ),
		];
	}

	/**
	 * Single-platform REST handler — shared logic for all per-platform routes.
	 */
	private function rest_single_platform( WP_REST_Request $request, string $platform ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$content = trim( (string) $request->get_param( 'content' ) );

		$this->hashtag_lang = sanitize_key( $request->get_param( 'hashtag_lang' ) ?: 'english' );

		if ( $post_id <= 0 && '' === $content ) {
			return SFBA_Rest_Api::error( 'sfba_invalid_input', __( 'Please select a post or paste content.', 'super-fast-blog-ai' ), 400 );
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				return SFBA_Rest_Api::error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), 404 );
			}
			$source     = $this->extract_source( $post );
			$post_title = $post->post_title;
		} else {
			$source     = $this->extract_source_from_content( $content );
			$post       = null;
			$post_title = '';
		}

		// Set content language for prompt builders.
		$this->content_language = $source['language'] ?? 'English';

		$result = $this->generate_platform( $platform, $source, $post );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( [
			'platform'   => $platform,
			'label'      => self::PLATFORMS[ $platform ],
			'post_id'    => $post_id,
			'post_title' => $post_title,
			'result'     => $result,
		] );
	}

	/**
	 * Extract H2 heading text as key points from the article HTML.
	 *
	 * @return string[]
	 */
	private function extract_key_points( string $html ): array {
		preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches );
		return array_map( 'wp_strip_all_tags', $matches[1] ?? [] );
	}

	/**
	 * Truncate plain text to a safe prompt length.
	 */
	private function safe_excerpt( string $plain, int $max_words ): string {
		return wp_trim_words( $plain, $max_words, '' );
	}

	/**
	 * Strip HTML to plain text preserving word boundaries.
	 */
	private function strip_to_plain( string $html ): string {
		$spaced = preg_replace( '/<\/(p|li|h[1-6]|blockquote|div)>/i', ' ', $html );
		$plain  = wp_strip_all_tags( $spaced );
		$plain  = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $plain ) );
	}

	/**
	 * Try to JSON-decode, stripping markdown code fences first.
	 *
	 * @return array|null
	 */
	private function try_json_decode( string $text ): ?array {
		$clean = trim( preg_replace( '/^```(?:json)?\s*/i', '', preg_replace( '/\s*```$/m', '', trim( $text ) ) ) );
		$data  = json_decode( $clean, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Parse a numbered plain-text list into an array of strings.
	 *
	 * @return string[]
	 */
	private function parse_numbered_list( string $text ): array {
		$lines = array_filter(
			array_map( 'trim', explode( "\n", $text ) ),
			fn( $l ) => '' !== $l
		);

		$items = [];
		foreach ( $lines as $line ) {
			$stripped = preg_replace( '/^[\(\[]?\d+[\.\)\]]?\s*/', '', $line );
			if ( '' !== $stripped ) {
				$items[] = $stripped;
			}
		}

		return array_values( $items );
	}
}
