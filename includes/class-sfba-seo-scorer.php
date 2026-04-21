<?php
/**
 * SEO Scoring Engine — MODULE 5.
 *
 * Pure-PHP, dependency-free content analysis. No Yoast/RankMath required.
 *
 * score_content()  — main public entry point.
 *   Runs 9 discrete checks, each worth a fixed number of points (total = 100).
 *   Each check returns: { id, label, status, points_earned, points_max, value, message }
 *
 * detect_ai_patterns() — parallel analysis, returns its own struct.
 *
 * Check catalogue (id → max points):
 *   keyword_in_title        → 15   keyword appears in meta title or H1
 *   keyword_density         → 15   0.5 – 2.5 % of word count
 *   headings_structure      → 15   H2 count ≥ 2; H1 count = 1; H3 optional
 *   paragraph_length        → 10   avg paragraph ≤ 150 words; no para > 300
 *   internal_links          → 10   ≥ 1 internal link per 300 words
 *   content_length          → 15   ≥ 300 words; bonus tiers at 600 / 1000
 *   meta_description        → 10   present, 50–160 chars, contains keyword
 *   image_alt_text          → 5    all <img> have non-empty alt attributes
 *   keyword_in_intro        → 5    keyword in first 100 words
 *
 * REST routes:
 *   POST /seo/score          — full score_content() call
 *   POST /seo/detect-ai      — detect_ai_patterns() call
 *   GET  /seo/score/{id}     — re-score a saved post by ID
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SFBA_Seo_Scorer
 */
class SFBA_Seo_Scorer {

	// -------------------------------------------------------------------------
	// Check weights (must sum to 100).
	// -------------------------------------------------------------------------

	private const WEIGHTS = [
		'keyword_in_title'   => 15,
		'keyword_density'    => 15,
		'headings_structure' => 15,
		'content_length'     => 15,
		'paragraph_length'   => 10,
		'internal_links'     => 10,
		'meta_description'   => 10,
		'keyword_in_intro'   => 5,
		'image_alt_text'     => 5,
	];

	// -------------------------------------------------------------------------
	// Grade thresholds.
	// -------------------------------------------------------------------------

	private const GRADES = [
		90 => [ 'grade' => 'A', 'label' => 'Excellent' ],
		75 => [ 'grade' => 'B', 'label' => 'Good' ],
		60 => [ 'grade' => 'C', 'label' => 'Needs improvement' ],
		40 => [ 'grade' => 'D', 'label' => 'Poor' ],
		0  => [ 'grade' => 'F', 'label' => 'Very poor' ],
	];

	// -------------------------------------------------------------------------
	// Keyword density targets.
	// -------------------------------------------------------------------------

	private const DENSITY_MIN   = 0.5;
	private const DENSITY_IDEAL = 1.5;
	private const DENSITY_MAX   = 2.5;
	private const DENSITY_OVER  = 4.0;

	// -------------------------------------------------------------------------
	// Content length tiers (words).
	// -------------------------------------------------------------------------

	private const LENGTH_MIN   = 300;
	private const LENGTH_GOOD  = 600;
	private const LENGTH_GREAT = 1000;

	// -------------------------------------------------------------------------
	// Paragraph length limits (words).
	// -------------------------------------------------------------------------

	private const PARA_AVG_MAX  = 150;
	private const PARA_HARD_MAX = 300;

	// -------------------------------------------------------------------------
	// Internal links: 1 per N words.
	// -------------------------------------------------------------------------

	private const LINK_DENSITY = 300;

	// -------------------------------------------------------------------------
	// AI pattern detection constants.
	// -------------------------------------------------------------------------

	private const AI_PHRASES = [
		'in conclusion', 'in summary', 'to summarize', 'it is worth noting',
		'it is important to note', 'it is worth mentioning', 'needless to say',
		'as mentioned earlier', 'as previously mentioned', 'as noted above',
		'it is crucial to', 'it is essential to', 'it is vital to',
		'it is imperative to', 'plays a crucial role', 'plays a vital role',
		'plays an important role', 'a wide range of', 'a wide variety of',
		'a myriad of', 'dive into', 'delve into', 'in the realm of',
		'in the world of', 'the landscape of', 'leverage', 'foster', 'robust',
		'comprehensive', 'cutting-edge', 'state-of-the-art', 'game-changing',
		'revolutionary', 'seamless', 'streamline', 'utilize',
		'in today\'s fast-paced', 'in the ever-evolving', 'in the digital age',
		'in the modern era', 'with the advent of', 'at its core', 'when it comes to',
	];

	private const AI_SENTENCE_CV_MAX = 0.30;
	private const AI_PASSIVE_RATIO   = 0.35;

	/** @var SFBA_Core */
	private $core;

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
		// POST /seo/score
		$this->core->rest_api->register_route( '/seo/score', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_score' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'content'          => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'wp_kses_post' ],
					'keyword'          => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'meta_title'       => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'meta_description' => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					'site_url'         => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		// POST /seo/detect-ai
		$this->core->rest_api->register_route( '/seo/detect-ai', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_detect_ai' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'content' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'wp_kses_post' ],
				],
			],
		] );

		// GET /seo/score/{id} — re-score a saved post.
		$this->core->rest_api->register_route( '/seo/score/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_score_post' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'id'      => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
					'keyword' => [ 'type' => 'string',  'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// REST callbacks.
	// -------------------------------------------------------------------------

	/** POST /seo/score */
	public function rest_score( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->score_content(
			(string) $request->get_param( 'content' ),
			(string) $request->get_param( 'keyword' ),
			[
				'meta_title'       => (string) $request->get_param( 'meta_title' ),
				'meta_description' => (string) $request->get_param( 'meta_description' ),
				'site_url'         => (string) $request->get_param( 'site_url' ),
			]
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** POST /seo/detect-ai */
	public function rest_detect_ai( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->detect_ai_patterns( (string) $request->get_param( 'content' ) );

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( $result );
	}

	/** GET /seo/score/{id} */
	public function rest_score_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return SFBA_Rest_Api::error( 'sfba_not_found', __( 'Post not found.', 'super-fast-blog-ai' ), 404 );
		}

		$keyword = (string) $request->get_param( 'keyword' );
		if ( '' === $keyword ) {
			$keyword = $this->get_post_focus_keyword( $post_id );
		}

		$result = $this->score_content(
			$post->post_content,
			$keyword,
			[
				'meta_title'       => get_post_meta( $post_id, '_yoast_wpseo_title',    true ) ?: $post->post_title,
				'meta_description' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ?: '',
				'site_url'         => get_site_url(),
			]
		);

		if ( is_wp_error( $result ) ) {
			return SFBA_Rest_Api::error( $result->get_error_code(), $result->get_error_message() );
		}

		return SFBA_Rest_Api::success( array_merge( $result, [ 'post_id' => $post_id ] ) );
	}

	// -------------------------------------------------------------------------
	// Public API.
	// -------------------------------------------------------------------------

	/**
	 * Score content against SEO best practices.
	 *
	 * @param string $content  HTML or plain-text content.
	 * @param string $keyword  Primary focus keyword.
	 * @param array  $meta     { meta_title, meta_description, site_url }
	 * @return array|WP_Error
	 */
	public function score_content( string $content, string $keyword = '', array $meta = [] ) {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Content cannot be empty.', 'super-fast-blog-ai' ) );
		}

		$keyword  = mb_strtolower( trim( $keyword ) );
		$site_url = sanitize_text_field( $meta['site_url'] ?? get_site_url() );

		$parsed = $this->parse_content( $content );

		$checks = [
			$this->check_keyword_in_title(   $parsed, $keyword, $meta ),
			$this->check_keyword_in_intro(   $parsed, $keyword ),
			$this->check_keyword_density(    $parsed, $keyword ),
			$this->check_headings_structure( $parsed ),
			$this->check_paragraph_length(   $parsed ),
			$this->check_internal_links(     $parsed, $site_url ),
			$this->check_content_length(     $parsed ),
			$this->check_meta_description(   $meta,   $keyword ),
			$this->check_image_alt_text(     $parsed ),
		];

		$total_earned = array_sum( array_column( $checks, 'points_earned' ) );
		$total_max    = array_sum( array_column( $checks, 'points_max' ) );

		$score = $total_max > 0
			? (int) round( ( $total_earned / $total_max ) * 100 )
			: 0;
		$score = max( 0, min( 100, $score ) );

		[ 'grade' => $grade, 'label' => $grade_label ] = $this->grade( $score );
		$suggestions = $this->build_suggestions( $checks );

		return [
			'score'       => $score,
			'grade'       => $grade,
			'grade_label' => $grade_label,
			'keyword'     => $keyword,
			'word_count'  => $parsed['word_count'],
			'checks'      => $checks,
			'suggestions' => $suggestions,
		];
	}

	/**
	 * Detect common AI-generated text patterns in content.
	 *
	 * Analyses four independent signals and combines them into an overall
	 * likelihood score (0–100).
	 *
	 * @param string $content HTML or plain-text content.
	 * @return array|WP_Error
	 */
	public function detect_ai_patterns( string $content ) {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'sfba_invalid_input', __( 'Content cannot be empty.', 'super-fast-blog-ai' ) );
		}

		$plain      = $this->strip_to_plain( $content );
		$plain_lc   = mb_strtolower( $plain );
		$words      = $this->tokenize_words( $plain );
		$sentences  = $this->tokenize_sentences( $plain );
		$word_count = count( $words );

		if ( $word_count < 30 ) {
			return new WP_Error( 'sfba_too_short', __( 'Content must be at least 30 words for AI pattern detection.', 'super-fast-blog-ai' ) );
		}

		// Signal 1: AI phrase density.
		$flagged_phrases = [];
		$phrase_hits     = 0;
		foreach ( self::AI_PHRASES as $phrase ) {
			$count = substr_count( $plain_lc, $phrase );
			if ( $count > 0 ) {
				$phrase_hits++;
				$flagged_phrases[] = $phrase;
			}
		}
		$phrase_density   = $word_count > 0 ? $phrase_hits / $word_count : 0;
		$phrase_triggered = $phrase_density > 0.01 || $phrase_hits >= 3;
		$phrase_score     = min( 40, (int) round( $phrase_hits * 4 ) );

		// Signal 2: Sentence length uniformity (coefficient of variation).
		$sent_lengths = array_map(
			function( $s ) { return str_word_count( $s ); },
			array_filter( $sentences, function( $s ) { return str_word_count( $s ) > 0; } )
		);
		$cv                   = $this->coefficient_of_variation( array_values( $sent_lengths ) );
		$uniformity_triggered = count( $sent_lengths ) >= 5 && $cv < self::AI_SENTENCE_CV_MAX;
		$uniformity_score     = $uniformity_triggered ? 25 : 0;

		// Signal 3: Passive voice ratio.
		$passive_count = 0;
		foreach ( $sentences as $sentence ) {
			if ( $this->is_passive_voice( $sentence ) ) {
				$passive_count++;
			}
		}
		$passive_ratio     = count( $sentences ) > 0 ? $passive_count / count( $sentences ) : 0;
		$passive_triggered = $passive_ratio >= self::AI_PASSIVE_RATIO;
		$passive_score     = (int) round( min( 20, $passive_ratio * 50 ) );

		// Signal 4: Transition phrase overuse.
		$transitions = [
			'furthermore', 'moreover', 'additionally', 'consequently',
			'nevertheless', 'nonetheless', 'henceforth', 'thus',
			'therefore', 'hence', 'accordingly', 'subsequently',
		];
		$transition_hits = 0;
		foreach ( $transitions as $t ) {
			$transition_hits += substr_count( $plain_lc, $t );
		}
		$transition_ratio     = $word_count > 0 ? $transition_hits / $word_count : 0;
		$transition_triggered = $transition_ratio > 0.015;
		$transition_score     = (int) round( min( 15, $transition_ratio * 500 ) );

		// Combine into overall likelihood.
		$likelihood = max( 0, min( 100, $phrase_score + $uniformity_score + $passive_score + $transition_score ) );

		if ( $likelihood >= 70 ) {
			$label = __( 'Likely AI-generated',   'super-fast-blog-ai' );
		} elseif ( $likelihood >= 40 ) {
			$label = __( 'Possibly AI-assisted',  'super-fast-blog-ai' );
		} elseif ( $likelihood >= 20 ) {
			$label = __( 'Minimal AI indicators', 'super-fast-blog-ai' );
		} else {
			$label = __( 'Appears human-written', 'super-fast-blog-ai' );
		}

		$signals = [
			[
				'id'        => 'ai_phrase_density',
				'label'     => __( 'AI phrase density', 'super-fast-blog-ai' ),
				'triggered' => $phrase_triggered,
				'value'     => $phrase_hits,
				'message'   => sprintf(
					/* translators: %d is the number of overused AI phrases found */
					_n( '%d overused AI phrase found.', '%d overused AI phrases found.', $phrase_hits, 'super-fast-blog-ai' ),
					$phrase_hits
				),
			],
			[
				'id'        => 'sentence_uniformity',
				'label'     => __( 'Sentence length uniformity', 'super-fast-blog-ai' ),
				'triggered' => $uniformity_triggered,
				'value'     => round( $cv, 3 ),
				'message'   => $uniformity_triggered
					? __( 'Sentences are unusually uniform in length (possible AI pattern).', 'super-fast-blog-ai' )
					: __( 'Sentence lengths vary naturally.', 'super-fast-blog-ai' ),
			],
			[
				'id'        => 'passive_voice_ratio',
				'label'     => __( 'Passive voice ratio', 'super-fast-blog-ai' ),
				'triggered' => $passive_triggered,
				'value'     => round( $passive_ratio * 100, 1 ),
				'message'   => sprintf(
					/* translators: %s is the percentage of sentences using passive voice */
					__( '%s%% of sentences use passive voice.', 'super-fast-blog-ai' ),
					round( $passive_ratio * 100, 1 )
				),
			],
			[
				'id'        => 'transition_overuse',
				'label'     => __( 'Formal transition overuse', 'super-fast-blog-ai' ),
				'triggered' => $transition_triggered,
				'value'     => $transition_hits,
				'message'   => sprintf(
					/* translators: %d is the number of formal transition words found */
					_n( '%d formal transition word.', '%d formal transition words.', $transition_hits, 'super-fast-blog-ai' ),
					$transition_hits
				),
			],
		];

		$suggestions = [];
		if ( $phrase_triggered ) {
			$suggestions[] = __( 'Replace overused AI phrases with specific, concrete language.', 'super-fast-blog-ai' );
		}
		if ( $uniformity_triggered ) {
			$suggestions[] = __( 'Vary sentence length: mix short punchy sentences with longer explanatory ones.', 'super-fast-blog-ai' );
		}
		if ( $passive_triggered ) {
			$suggestions[] = __( 'Rewrite passive sentences in active voice (e.g. "We found" not "It was found").', 'super-fast-blog-ai' );
		}
		if ( $transition_triggered ) {
			$suggestions[] = __( 'Reduce formal connectors like "furthermore" and "consequently" — use natural transitions instead.', 'super-fast-blog-ai' );
		}

		return [
			'likelihood'      => $likelihood,
			'label'           => $label,
			'signals'         => $signals,
			'flagged_phrases' => $flagged_phrases,
			'suggestions'     => $suggestions,
		];
	}

	// -------------------------------------------------------------------------
	// Individual SEO checks.
	// -------------------------------------------------------------------------

	private function check_keyword_in_title( array $parsed, string $keyword, array $meta ): array {
		$max = self::WEIGHTS['keyword_in_title'];
		$id  = 'keyword_in_title';

		if ( '' === $keyword ) {
			return $this->skip_check( $id, __( 'Keyword in title', 'super-fast-blog-ai' ), $max, __( 'No focus keyword set.', 'super-fast-blog-ai' ) );
		}

		$meta_title = mb_strtolower( (string) ( $meta['meta_title'] ?? '' ) );
		$h1_text    = mb_strtolower( implode( ' ', $parsed['h1s'] ) );
		$combined   = $meta_title . ' ' . $h1_text;
		$found      = ( false !== strpos( $combined, $keyword ) );

		if ( $found ) {
			$at_start = ( 0 === strpos( trim( $meta_title ), $keyword ) )
				|| ( 0 === strpos( trim( $h1_text ), $keyword ) );

			return $this->pass_check(
				$id,
				__( 'Keyword in title', 'super-fast-blog-ai' ),
				$max,
				$at_start ? $max : (int) round( $max * 0.8 ),
				$combined,
				$at_start
					? __( 'Keyword appears at the start of the title — great for SEO.', 'super-fast-blog-ai' )
					: __( 'Keyword found in title.', 'super-fast-blog-ai' )
			);
		}

		return $this->fail_check(
			$id,
			__( 'Keyword in title', 'super-fast-blog-ai' ),
			$max,
			$combined ?: '(no title or H1 found)',
			__( 'Add the focus keyword to the page title or H1 heading.', 'super-fast-blog-ai' )
		);
	}

	private function check_keyword_in_intro( array $parsed, string $keyword ): array {
		$max = self::WEIGHTS['keyword_in_intro'];
		$id  = 'keyword_in_intro';

		if ( '' === $keyword ) {
			return $this->skip_check( $id, __( 'Keyword in intro', 'super-fast-blog-ai' ), $max, __( 'No focus keyword set.', 'super-fast-blog-ai' ) );
		}

		$intro_words = array_slice( $parsed['words'], 0, 100 );
		$intro_text  = mb_strtolower( implode( ' ', $intro_words ) );
		$found       = ( false !== strpos( $intro_text, $keyword ) );

		if ( $found ) {
			return $this->pass_check(
				$id,
				__( 'Keyword in intro', 'super-fast-blog-ai' ),
				$max,
				$max,
				count( $intro_words ) . ' words checked',
				__( 'Keyword found in the opening paragraph.', 'super-fast-blog-ai' )
			);
		}

		return $this->fail_check(
			$id,
			__( 'Keyword in intro', 'super-fast-blog-ai' ),
			$max,
			'(not found in first 100 words)',
			__( 'Use the focus keyword within the first 100 words to signal the topic early.', 'super-fast-blog-ai' )
		);
	}

	private function check_keyword_density( array $parsed, string $keyword ): array {
		$max = self::WEIGHTS['keyword_density'];
		$id  = 'keyword_density';

		if ( '' === $keyword || 0 === $parsed['word_count'] ) {
			return $this->skip_check( $id, __( 'Keyword density', 'super-fast-blog-ai' ), $max, __( 'No focus keyword or content.', 'super-fast-blog-ai' ) );
		}

		$plain_lc  = mb_strtolower( $parsed['plain_text'] );
		$kw_count  = substr_count( $plain_lc, $keyword );
		$density   = ( $kw_count / $parsed['word_count'] ) * 100;
		$value_str = round( $density, 2 ) . '%';

		if ( $density > self::DENSITY_OVER ) {
			return $this->fail_check(
				$id,
				__( 'Keyword density', 'super-fast-blog-ai' ),
				$max,
				$value_str,
				/* translators: %s or %d is a count/score/number */ sprintf( __( 'Keyword density is %s — this may be seen as keyword stuffing. Target 0.5–2.5%%.', 'super-fast-blog-ai' ), $value_str )
			);
		}

		if ( $density > self::DENSITY_MAX ) {
			return $this->warn_check(
				$id,
				__( 'Keyword density', 'super-fast-blog-ai' ),
				$max,
				(int) round( $max * 0.4 ),
				$value_str,
				/* translators: %s or %d is a count/score/number */ sprintf( __( 'Keyword density is %s — slightly high. Aim for 0.5–2.5%%.', 'super-fast-blog-ai' ), $value_str )
			);
		}

		if ( $density >= self::DENSITY_MIN ) {
			$points = $density <= self::DENSITY_IDEAL ? $max : (int) round( $max * 0.85 );
			return $this->pass_check(
				$id,
				__( 'Keyword density', 'super-fast-blog-ai' ),
				$max,
				$points,
				$value_str,
				/* translators: %s or %d is a count/score/number */ sprintf( __( 'Keyword density is %s — within the ideal range.', 'super-fast-blog-ai' ), $value_str )
			);
		}

		return $this->warn_check(
			$id,
			__( 'Keyword density', 'super-fast-blog-ai' ),
			$max,
			(int) round( $max * 0.3 ),
			$value_str,
			/* translators: %s or %d is a count/score/number */ sprintf( __( 'Keyword density is %s — too low. Use the keyword more naturally throughout the content.', 'super-fast-blog-ai' ), $value_str )
		);
	}

	private function check_headings_structure( array $parsed ): array {
		$max      = self::WEIGHTS['headings_structure'];
		$id       = 'headings_structure';
		$h1_count = count( $parsed['h1s'] );
		$h2_count = count( $parsed['h2s'] );
		$h3_count = count( $parsed['h3s'] );
		$value    = "H1:{$h1_count}, H2:{$h2_count}, H3:{$h3_count}";

		if ( $h1_count > 1 ) {
			return $this->fail_check( $id, __( 'Heading structure', 'super-fast-blog-ai' ), $max, $value, __( 'Found multiple H1 headings. A page should have exactly one H1.', 'super-fast-blog-ai' ) );
		}

		if ( $h2_count < 2 ) {
			return $this->fail_check( $id, __( 'Heading structure', 'super-fast-blog-ai' ), $max, $value, __( 'Add at least 2 H2 subheadings to structure the content for readers and search engines.', 'super-fast-blog-ai' ) );
		}

		if ( 0 === $h1_count ) {
			return $this->warn_check( $id, __( 'Heading structure', 'super-fast-blog-ai' ), $max, (int) round( $max * 0.75 ), $value, __( 'No H1 found in content body. Ensure the post title acts as H1.', 'super-fast-blog-ai' ) );
		}

		return $this->pass_check( $id, __( 'Heading structure', 'super-fast-blog-ai' ), $max, $max, $value, __( 'Good heading hierarchy — one H1 and multiple H2 subheadings.', 'super-fast-blog-ai' ) );
	}

	private function check_paragraph_length( array $parsed ): array {
		$max   = self::WEIGHTS['paragraph_length'];
		$id    = 'paragraph_length';
		$paras = $parsed['paragraphs'];

		if ( empty( $paras ) ) {
			return $this->skip_check( $id, __( 'Paragraph length', 'super-fast-blog-ai' ), $max, __( 'No paragraphs detected.', 'super-fast-blog-ai' ) );
		}

		$lengths  = array_map( function( $p ) { return str_word_count( $p ); }, $paras );
		$avg      = (int) round( array_sum( $lengths ) / count( $lengths ) );
		$max_para = max( $lengths );
		$value    = "avg:{$avg} words, max:{$max_para} words";

		if ( $max_para > self::PARA_HARD_MAX ) {
			/* translators: %s or %d is a count/score/number */
			return $this->fail_check( $id, __( 'Paragraph length', 'super-fast-blog-ai' ), $max, $value, sprintf( __( 'One paragraph has %d words — break it into smaller chunks for readability.', 'super-fast-blog-ai' ), $max_para ) );
		}

		if ( $avg > self::PARA_AVG_MAX ) {
			/* translators: %s or %d is a count/score/number */
			return $this->warn_check( $id, __( 'Paragraph length', 'super-fast-blog-ai' ), $max, (int) round( $max * 0.5 ), $value, sprintf( __( 'Average paragraph is %d words. Try to keep paragraphs under 150 words for readability.', 'super-fast-blog-ai' ), $avg ) );
		}

		return $this->pass_check( $id, __( 'Paragraph length', 'super-fast-blog-ai' ), $max, $max, $value, __( 'Paragraph lengths are reader-friendly.', 'super-fast-blog-ai' ) );
	}

	private function check_internal_links( array $parsed, string $site_url ): array {
		$max   = self::WEIGHTS['internal_links'];
		$id    = 'internal_links';
		$count = $this->count_internal_links( $parsed['raw_html'], $site_url );
		$words = $parsed['word_count'];
		$needed = max( 1, (int) ceil( $words / self::LINK_DENSITY ) );
		$value  = "{$count} internal link(s)";

		if ( 0 === $count ) {
			return $this->fail_check( $id, __( 'Internal links', 'super-fast-blog-ai' ), $max, $value, __( 'Add at least one internal link to help readers discover related content.', 'super-fast-blog-ai' ) );
		}

		if ( $count < $needed ) {
			/* translators: %s or %d is a count/score/number */
			return $this->warn_check( $id, __( 'Internal links', 'super-fast-blog-ai' ), $max, (int) round( $max * ( $count / $needed ) ), $value, sprintf( __( 'Found %1$d internal link(s) — consider adding %2$d for this length.', 'super-fast-blog-ai' ), $count, $needed ) );
		}

		/* translators: %s or %d is a count/score/number */
		return $this->pass_check( $id, __( 'Internal links', 'super-fast-blog-ai' ), $max, $max, $value, sprintf( _n( '%d internal link — good.', '%d internal links — good.', $count, 'super-fast-blog-ai' ), $count ) );
	}

	private function check_content_length( array $parsed ): array {
		$max   = self::WEIGHTS['content_length'];
		$id    = 'content_length';
		$words = $parsed['word_count'];
		$value = "{$words} words";

		if ( $words < self::LENGTH_MIN ) {
			/* translators: %s or %d is a count/score/number */
			return $this->fail_check( $id, __( 'Content length', 'super-fast-blog-ai' ), $max, $value, sprintf( __( 'Content is %d words — too short. Aim for at least 600 words for most topics.', 'super-fast-blog-ai' ), $words ) );
		}

		if ( $words < self::LENGTH_GOOD ) {
			/* translators: %s or %d is a count/score/number */
			return $this->warn_check( $id, __( 'Content length', 'super-fast-blog-ai' ), $max, (int) round( $max * 0.5 ), $value, sprintf( __( 'Content is %d words. Adding more depth (target 600+ words) improves ranking potential.', 'super-fast-blog-ai' ), $words ) );
		}

		if ( $words < self::LENGTH_GREAT ) {
			/* translators: %s or %d is a count/score/number */
			return $this->pass_check( $id, __( 'Content length', 'super-fast-blog-ai' ), $max, (int) round( $max * 0.8 ), $value, sprintf( __( 'Content is %d words — solid length. 1000+ words gives an edge on competitive topics.', 'super-fast-blog-ai' ), $words ) );
		}

		/* translators: %s or %d is a count/score/number */
		return $this->pass_check( $id, __( 'Content length', 'super-fast-blog-ai' ), $max, $max, $value, sprintf( __( 'Content is %d words — excellent depth.', 'super-fast-blog-ai' ), $words ) );
	}

	private function check_meta_description( array $meta, string $keyword ): array {
		$max  = self::WEIGHTS['meta_description'];
		$id   = 'meta_description';
		$desc = trim( (string) ( $meta['meta_description'] ?? '' ) );
		$len  = mb_strlen( $desc );

		if ( '' === $desc ) {
			return $this->fail_check( $id, __( 'Meta description', 'super-fast-blog-ai' ), $max, '(missing)', __( 'Write a meta description (50–160 characters) to improve click-through rates.', 'super-fast-blog-ai' ) );
		}

		$has_kw = '' !== $keyword && ( false !== strpos( mb_strtolower( $desc ), $keyword ) );
		$value  = "{$len} chars" . ( $has_kw ? ', keyword ✓' : ', keyword ✗' );

		if ( $len < 50 || $len > 160 ) {
			$too = $len < 50 ? 'too short' : 'too long';
			/* translators: %s or %d is a count/score/number */
			return $this->warn_check( $id, __( 'Meta description', 'super-fast-blog-ai' ), $max, (int) round( $max * 0.4 ), $value, sprintf( __( 'Meta description is %2$d characters (%1$s). Ideal length is 50–160 characters.', 'super-fast-blog-ai' ), $too, $len ) );
		}

		$points = $has_kw ? $max : (int) round( $max * 0.7 );
		$msg    = $has_kw
			? __( 'Meta description length is good and contains the keyword.', 'super-fast-blog-ai' )
			: __( 'Meta description length is good. Consider including the focus keyword.', 'super-fast-blog-ai' );

		return $this->pass_check( $id, __( 'Meta description', 'super-fast-blog-ai' ), $max, $points, $value, $msg );
	}

	private function check_image_alt_text( array $parsed ): array {
		$max     = self::WEIGHTS['image_alt_text'];
		$id      = 'image_alt_text';
		$total   = $parsed['img_count'];
		$missing = $parsed['img_missing_alt'];
		$value   = "{$total} image(s), {$missing} missing alt";

		if ( 0 === $total ) {
			return $this->skip_check( $id, __( 'Image alt text', 'super-fast-blog-ai' ), $max, __( 'No images found in content.', 'super-fast-blog-ai' ) );
		}

		if ( 0 === $missing ) {
			return $this->pass_check( $id, __( 'Image alt text', 'super-fast-blog-ai' ), $max, $max, $value, __( 'All images have alt text.', 'super-fast-blog-ai' ) );
		}

		$ratio  = $missing / $total;
		$points = (int) round( $max * ( 1 - $ratio ) );

		/* translators: %s or %d is a count/score/number */
		return $this->warn_check( $id, __( 'Image alt text', 'super-fast-blog-ai' ), $max, $points, $value, sprintf( _n( '%d image is missing alt text.', '%d images are missing alt text.', $missing, 'super-fast-blog-ai' ), $missing ) );
	}

	// -------------------------------------------------------------------------
	// Content parser.
	// -------------------------------------------------------------------------

	/**
	 * Parse raw HTML content into reusable analysis components.
	 *
	 * @param string $content
	 * @return array
	 */
	private function parse_content( string $content ): array {
		$raw_html   = $content;
		$plain_text = $this->strip_to_plain( $content );
		$words      = $this->tokenize_words( $plain_text );

		$h1s = $this->extract_tag_text( $content, 'h1' );
		$h2s = $this->extract_tag_text( $content, 'h2' );
		$h3s = $this->extract_tag_text( $content, 'h3' );

		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $p_matches );
		$paragraphs = array_filter(
			array_map( function( $p ) { return trim( wp_strip_all_tags( $p ) ); }, $p_matches[1] ?? [] ),
			function( $p ) { return '' !== $p; }
		);

		preg_match_all( '/<img[^>]+>/i', $content, $img_matches );
		$img_count       = count( $img_matches[0] );
		$img_missing_alt = 0;
		foreach ( $img_matches[0] as $img_tag ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img_tag ) ) {
				$img_missing_alt++;
			}
		}

		return [
			'raw_html'        => $raw_html,
			'plain_text'      => $plain_text,
			'words'           => $words,
			'word_count'      => count( $words ),
			'paragraphs'      => array_values( $paragraphs ),
			'h1s'             => $h1s,
			'h2s'             => $h2s,
			'h3s'             => $h3s,
			'img_count'       => $img_count,
			'img_missing_alt' => $img_missing_alt,
		];
	}

	// -------------------------------------------------------------------------
	// Check result factories.
	// -------------------------------------------------------------------------

	private function pass_check( string $id, string $label, int $max, int $points, $value, string $message ): array {
		return [ 'id' => $id, 'label' => $label, 'status' => 'pass', 'points_earned' => min( $points, $max ), 'points_max' => $max, 'value' => $value, 'message' => $message ];
	}

	private function warn_check( string $id, string $label, int $max, int $points, $value, string $message ): array {
		return [ 'id' => $id, 'label' => $label, 'status' => 'warn', 'points_earned' => max( 0, min( $points, $max ) ), 'points_max' => $max, 'value' => $value, 'message' => $message ];
	}

	private function fail_check( string $id, string $label, int $max, mixed $value, string $message ): array {
		return [ 'id' => $id, 'label' => $label, 'status' => 'fail', 'points_earned' => 0, 'points_max' => $max, 'value' => $value, 'message' => $message ];
	}

	private function skip_check( string $id, string $label, int $max, string $message ): array {
		return [ 'id' => $id, 'label' => $label, 'status' => 'skip', 'points_earned' => 0, 'points_max' => 0, 'value' => null, 'message' => $message ];
	}

	// -------------------------------------------------------------------------
	// Suggestion builder.
	// -------------------------------------------------------------------------

	/**
	 * Derive an ordered list of actionable suggestions from check results.
	 *
	 * @param array $checks
	 * @return string[]
	 */
	private function build_suggestions( array $checks ): array {
		$fails = [];
		$warns = [];

		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) {
				$fails[] = $check['message'];
			} elseif ( 'warn' === $check['status'] ) {
				$warns[] = $check['message'];
			}
		}

		return array_slice( array_merge( $fails, $warns ), 0, 5 );
	}

	// -------------------------------------------------------------------------
	// Text utilities.
	// -------------------------------------------------------------------------

	private function strip_to_plain( string $html ): string {
		$spaced = preg_replace( '/<\/(p|li|h[1-6]|blockquote|div|td|th)>/i', ' ', $html );
		$plain  = wp_strip_all_tags( $spaced );
		$plain  = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $plain ) );
	}

	/**
	 * @return string[]
	 */
	private function tokenize_words( string $text ): array {
		$text = mb_strtolower( $text );
		preg_match_all( "/\b[a-z']+\b/u", $text, $matches );
		return $matches[0];
	}

	/**
	 * @return string[]
	 */
	private function tokenize_sentences( string $text ): array {
		$sentences = preg_split( '/(?<=[.!?])\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		return array_filter( $sentences, fn( $s ) => str_word_count( $s ) > 0 );
	}

	/**
	 * @return string[]
	 */
	private function extract_tag_text( string $html, string $tag ): array {
		preg_match_all( '/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $html, $matches );
		return array_map( 'wp_strip_all_tags', $matches[1] ?? [] );
	}

	private function count_internal_links( string $html, string $site_url ): int {
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches );
		$count    = 0;
		$site_url = rtrim( $site_url, '/' );

		foreach ( $matches[1] as $href ) {
			$href = trim( $href );
			if ( str_starts_with( $href, $site_url ) ) {
				$count++;
			} elseif ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) ) {
				$count++;
			}
		}

		return $count;
	}

	// -------------------------------------------------------------------------
	// Statistical helpers.
	// -------------------------------------------------------------------------

	/**
	 * @param int[] $values
	 */
	private function coefficient_of_variation( array $values ): float {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0.0;
		}

		$mean = array_sum( $values ) / $n;
		if ( 0.0 === $mean ) {
			return 0.0;
		}

		$variance = array_sum( array_map( fn( $v ) => ( $v - $mean ) ** 2, $values ) ) / $n;
		return sqrt( $variance ) / $mean;
	}

	private function is_passive_voice( string $sentence ): bool {
		$sentence_lc = mb_strtolower( $sentence );
		$be_verbs    = 'is|are|was|were|be|been|being|has been|have been|had been|will be';

		return (bool) preg_match(
			'/\b(' . $be_verbs . ')\b\s+(\w+ed|built|done|found|given|gone|known|made|said|seen|shown|thought|used|won|written)\b/',
			$sentence_lc
		);
	}

	// -------------------------------------------------------------------------
	// Grade helper.
	// -------------------------------------------------------------------------

	/**
	 * @return array{grade: string, label: string}
	 */
	private function grade( int $score ): array {
		foreach ( self::GRADES as $threshold => $info ) {
			if ( $score >= $threshold ) {
				return $info;
			}
		}
		return [ 'grade' => 'F', 'label' => 'Very poor' ];
	}

	// -------------------------------------------------------------------------
	// Utility.
	// -------------------------------------------------------------------------

	/**
	 * Read the focus keyword for a post from Yoast or RankMath, if available.
	 */
	private function get_post_focus_keyword( int $post_id ): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		}
		// Check SFBA's own target keyword meta.
		return (string) get_post_meta( $post_id, '_sfba_target_keyword', true );
	}
}
