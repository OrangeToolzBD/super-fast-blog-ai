<?php
/**
 * Gutenberg & Classic Editor Integration — MODULE 18.
 *
 * Enqueues block editor and TinyMCE assets, registers custom blocks, and
 * passes plugin configuration to the JavaScript layer via a localized global.
 *
 * JS global shape (SFBAEditor / SFBAClassic):
 * {
 *   apiBase:       string   // full REST URL
 *   nonce:         string   // wp_rest nonce
 *   pluginUrl:     string
 *   version:       string
 *   postId:        int
 *   postType:      string
 *   currentUserId: int
 *   capabilities:  { canGenerate: bool, canManageKeys: bool }
 *   features:      { brandVoice: bool, routing: bool, streaming: bool }
 *   providers:     { hasAny: bool, default: string }
 *   endpoints:     { outline, section, article, productDesc, meta, rewrite, stream, brandVoice, settings }
 *   i18n:          { [key]: string }
 * }
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Editor_Integration {

	/**
	 * Block namespace for all registered blocks.
	 */
	private const BLOCK_NAMESPACE = 'super-fast-blog-ai';

	/**
	 * Script handle for the Gutenberg editor bundle.
	 */
	private const EDITOR_HANDLE = 'sfba-editor';

	/**
	 * Script handle for the Classic Editor TinyMCE plugin.
	 */
	private const CLASSIC_HANDLE = 'sfba-editor-classic';

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
	 * Wire all editor-related hooks into the loader.
	 *
	 * @param SFBA_Loader $loader
	 */
	public function init( SFBA_Loader $loader ): void {

		// ── Gutenberg ──────────────────────────────────────────────────────────
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets' );
		$loader->add_action( 'init',                        $this, 'register_block_types' );
		$loader->add_filter( 'block_categories_all',        $this, 'register_block_category', 10, 2 );
		// Priority 20 runs after enqueue_block_editor_assets (priority 10).
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'filter_block_asset_data', 20 );

		// ── Classic Editor (TinyMCE) ───────────────────────────────────────────
		$loader->add_filter( 'mce_buttons',          $this, 'register_tinymce_button', 10, 2 );
		$loader->add_filter( 'mce_external_plugins', $this, 'register_tinymce_plugin' );
		$loader->add_action( 'admin_head',            $this, 'classic_editor_print_config' );

		// ── REST (streaming stub) ──────────────────────────────────────────────
		$this->core->rest_api->register_route( '/generate/stream', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_stream_generate' ],
				'permission_callback' => [ $this->core->rest_api, 'require_editor' ],
				'args'                => [
					'topic'        => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
					'content_type' => [ 'type' => 'string',  'required' => false, 'default' => 'blog', 'sanitize_callback' => 'sanitize_key' ],
					'post_id'      => [ 'type' => 'integer', 'required' => false, 'default' => 0 ],
				],
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Gutenberg — asset loading.
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the block editor JS bundle and stylesheet.
	 */
	public function enqueue_block_editor_assets(): void {
		if ( ! file_exists( SFBA_PLUGIN_DIR . 'assets/js/editor.js' ) ) {
			return;
		}

		// ── JavaScript ────────────────────────────────────────────────────────
		wp_enqueue_script(
			self::EDITOR_HANDLE,
			SFBA_ASSETS_URL . 'js/editor.js',
			[
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-api-fetch',
				'wp-i18n',
				'wp-rich-text',
				'wp-block-editor',
				'wp-compose',
				'wp-hooks',
			],
			SFBA_VERSION,
			true
		);

		// ── Stylesheet ────────────────────────────────────────────────────────
		if ( file_exists( SFBA_PLUGIN_DIR . 'assets/css/editor.css' ) ) {
			wp_enqueue_style(
				self::EDITOR_HANDLE,
				SFBA_ASSETS_URL . 'css/editor.css',
				[ 'wp-components', 'wp-edit-post' ],
				SFBA_VERSION
			);
		}

		// ── Translations ──────────────────────────────────────────────────────
		wp_set_script_translations(
			self::EDITOR_HANDLE,
			'super-fast-blog-ai',
			SFBA_PLUGIN_DIR . 'languages'
		);

		// ── Core config ───────────────────────────────────────────────────────
		wp_localize_script(
			self::EDITOR_HANDLE,
			'SFBAEditor',
			$this->build_editor_config()
		);
	}

	/**
	 * Inject per-post dynamic data as a second localization pass.
	 *
	 * Hooked at priority 20, after enqueue_block_editor_assets (priority 10).
	 */
	public function filter_block_asset_data(): void {
		if ( ! wp_script_is( self::EDITOR_HANDLE, 'enqueued' ) ) {
			return;
		}

		$dynamic = $this->build_dynamic_post_data();
		$json    = wp_json_encode( $dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$script  = "if(window.SFBAEditor){Object.assign(window.SFBAEditor," . $json . ");}";

		wp_add_inline_script( self::EDITOR_HANDLE, $script, 'after' );
	}

	// -------------------------------------------------------------------------
	// Gutenberg — block registration.
	// -------------------------------------------------------------------------

	/**
	 * Register all custom Gutenberg blocks for this plugin.
	 */
	public function register_block_types(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$blocks = [
			'write-with-ai',
			'ai-image',
		];

		foreach ( $blocks as $block_name ) {
			$block_dir = SFBA_PLUGIN_DIR . 'src/blocks/' . $block_name;

			if ( ! file_exists( $block_dir . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$block_dir,
				[
					'editor_script' => self::EDITOR_HANDLE,
					'editor_style'  => self::EDITOR_HANDLE,
				]
			);
		}
	}

	/**
	 * Add a "Super Fast Blog AI" category to the Gutenberg block inserter.
	 *
	 * @param array                   $categories Existing block categories.
	 * @param WP_Block_Editor_Context $context    Current editor context.
	 * @return array Modified categories.
	 */
	public function register_block_category( array $categories, WP_Block_Editor_Context $context ): array {
		if ( ! $context->post instanceof WP_Post ) {
			return $categories;
		}

		return array_merge(
			[
				[
					'slug'  => 'super-fast-blog-ai',
					'title' => __( 'Super Fast Blog AI', 'super-fast-blog-ai' ),
					'icon'  => 'edit-large',
				],
			],
			$categories
		);
	}

	// -------------------------------------------------------------------------
	// Classic Editor (TinyMCE).
	// -------------------------------------------------------------------------

	/**
	 * Add the SFBA button to TinyMCE toolbar row 1.
	 *
	 * @param array  $buttons   Existing button IDs.
	 * @param string $editor_id TinyMCE editor instance ID.
	 * @return array Modified button list.
	 */
	public function register_tinymce_button( array $buttons, string $editor_id ): array {
		if ( 'content' !== $editor_id ) {
			return $buttons;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $buttons;
		}

		array_splice( $buttons, 1, 0, 'sfba_ai' );

		return $buttons;
	}

	/**
	 * Register the TinyMCE external plugin file.
	 *
	 * @param array $plugins Existing external plugin map.
	 * @return array Modified plugin map.
	 */
	public function register_tinymce_plugin( array $plugins ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $plugins;
		}

		if ( ! file_exists( SFBA_PLUGIN_DIR . 'assets/js/editor-classic.js' ) ) {
			return $plugins;
		}

		$plugins['sfba_ai'] = SFBA_ASSETS_URL . 'js/editor-classic.js?ver=' . SFBA_VERSION;

		return $plugins;
	}

	/**
	 * Print the SFBAClassic config object into wp-admin <head>.
	 */
	public function classic_editor_print_config(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, [ 'post', 'page' ], true ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$config = wp_json_encode(
			array_merge(
				$this->build_editor_config(),
				$this->build_dynamic_post_data()
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "<script>window.SFBAClassic=" . $config . ";</script>\n";
	}

	// -------------------------------------------------------------------------
	// REST — streaming stub.
	// -------------------------------------------------------------------------

	/**
	 * POST /generate/stream
	 *
	 * Placeholder for Server-Sent Events (SSE) streaming generation.
	 * Returns a non-streaming JSON response for now.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rest_stream_generate( WP_REST_Request $request ): WP_REST_Response {
		$topic        = (string) $request->get_param( 'topic' );
		$content_type = (string) $request->get_param( 'content_type' );
		$post_id      = (int)    $request->get_param( 'post_id' );

		// Resolve provider and generate non-streaming response.
		$route    = $this->core->providers->resolve_route( $content_type );
		$provider = $this->core->providers->get_provider( $route['provider'] ?? '' );

		if ( is_wp_error( $provider ) || null === $provider ) {
			return SFBA_Rest_Api::success( [
				'outline'   => [],
				'streaming' => false,
				'mock'      => true,
				'message'   => __( 'Configure an AI provider in Super Fast Blog AI → Settings.', 'super-fast-blog-ai' ),
			] );
		}

		$prompt = "Create a structured outline for a blog post about: {$topic}\n\nReturn a JSON array of section headings.";
		$gen    = $provider->generate( $prompt, [
			'model'       => $route['model'] ?? '',
			'max_tokens'  => 800,
			'temperature' => 0.7,
		] );

		if ( is_wp_error( $gen ) ) {
			return SFBA_Rest_Api::error( $gen->get_error_code(), $gen->get_error_message() );
		}

		$this->core->cost_tracker->log( [
			'post_id'  => $post_id,
			'provider' => $route['provider'] ?? '',
			'model'    => $route['model'] ?? '',
			'feature'  => 'editor_stream',
			'prompt_tokens'     => $gen['prompt_tokens']     ?? 0,
			'completion_tokens' => $gen['completion_tokens'] ?? 0,
			'cost_usd'          => $gen['cost_usd']          ?? 0.0,
		] );

		return SFBA_Rest_Api::success( [
			'outline'   => $gen['text'] ?? '',
			'streaming' => false,
		] );
	}

	// -------------------------------------------------------------------------
	// Config builders.
	// -------------------------------------------------------------------------

	/**
	 * Build the static editor config passed to JS via wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	private function build_editor_config(): array {
		$api_base = rest_url( SFBA_Rest_Api::NAMESPACE );

		return [
			'apiBase'   => $api_base,
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => SFBA_PLUGIN_URL,
			'version'   => SFBA_VERSION,

			'currentUserId' => get_current_user_id(),
			'capabilities'  => [
				'canGenerate'   => current_user_can( 'edit_posts' ),
				'canManageKeys' => current_user_can( 'manage_options' ),
			],

			'features' => [
				'brandVoice' => (bool) $this->core->settings->get( 'brand_voice.enabled', false ),
				'routing'    => (bool) $this->core->settings->get( 'routing_enabled', false ),
				'streaming'  => false,
			],

			'providers' => [
				'hasAny'  => $this->has_any_provider(),
				'default' => (string) $this->core->settings->get( 'default_provider', '' ),
			],

			'endpoints' => [
				'outline'     => $api_base . '/generate/outline',
				'section'     => $api_base . '/generate/section',
				'article'     => $api_base . '/generate/article',
				'productDesc' => $api_base . '/generate/product-description',
				'meta'        => $api_base . '/generate/meta',
				'rewrite'     => $api_base . '/generate/rewrite',
				'stream'      => $api_base . '/generate/stream',
				'brandVoice'  => $api_base . '/brand-voice',
				'settings'    => $api_base . '/settings',
			],

			'i18n' => $this->editor_i18n_strings(),
		];
	}

	/**
	 * Build per-post dynamic data injected after the page is determined.
	 *
	 * @return array<string, mixed>
	 */
	private function build_dynamic_post_data(): array {
		$post_id   = (int) get_the_ID();
		$post      = $post_id ? get_post( $post_id ) : null;
		$post_type = $post instanceof WP_Post ? $post->post_type : 'post';

		$word_count = 0;
		if ( $post instanceof WP_Post && '' !== $post->post_content ) {
			$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		}

		$is_product = 'product' === $post_type && class_exists( 'WooCommerce' );

		return [
			'postId'     => $post_id,
			'postType'   => $post_type,
			'postStatus' => $post instanceof WP_Post ? $post->post_status : 'auto-draft',
			'wordCount'  => $word_count,
			'isProduct'  => $is_product,
			'existingMeta' => $this->read_existing_seo_meta( $post_id ),
		];
	}

	/**
	 * Read existing SEO meta from common SEO plugins.
	 *
	 * @param int $post_id
	 * @return array{meta_title: string, meta_description: string, focus_keyword: string}
	 */
	private function read_existing_seo_meta( int $post_id ): array {
		if ( $post_id < 1 ) {
			return [ 'meta_title' => '', 'meta_description' => '', 'focus_keyword' => '' ];
		}

		// Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) ) {
			return [
				'meta_title'       => (string) get_post_meta( $post_id, '_yoast_wpseo_title',    true ),
				'meta_description' => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
				'focus_keyword'    => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw',  true ),
			];
		}

		// RankMath.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return [
				'meta_title'       => (string) get_post_meta( $post_id, 'rank_math_title',         true ),
				'meta_description' => (string) get_post_meta( $post_id, 'rank_math_description',   true ),
				'focus_keyword'    => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			];
		}

		// SFBA target keyword (set by Content Calendar).
		$sfba_keyword = (string) get_post_meta( $post_id, '_sfba_target_keyword', true );

		return [
			'meta_title'       => '',
			'meta_description' => '',
			'focus_keyword'    => $sfba_keyword,
		];
	}

	/**
	 * Check whether at least one provider has an API key configured.
	 *
	 * @return bool
	 */
	private function has_any_provider(): bool {
		foreach ( $this->core->providers->get_registered_slugs() as $slug ) {
			if ( '' !== $this->core->settings->get_api_key( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return all translatable UI strings used by the editor JS.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function editor_i18n_strings(): array {
		return [

			// ── Shared / generic ──────────────────────────────────────────────
			'common' => [
				'pluginName'   => __( 'Super Fast Blog AI', 'super-fast-blog-ai' ),
				'generating'   => __( 'Generating…',        'super-fast-blog-ai' ),
				'saving'       => __( 'Saving…',            'super-fast-blog-ai' ),
				'insert'       => __( 'Insert',             'super-fast-blog-ai' ),
				'replace'      => __( 'Replace',            'super-fast-blog-ai' ),
				'cancel'       => __( 'Cancel',             'super-fast-blog-ai' ),
				'retry'        => __( 'Retry',              'super-fast-blog-ai' ),
				'copy'         => __( 'Copy',               'super-fast-blog-ai' ),
				'copied'       => __( 'Copied!',            'super-fast-blog-ai' ),
				'error'        => __( 'Something went wrong. Please try again.', 'super-fast-blog-ai' ),
				'noProvider'   => __( 'Configure an AI provider in Super Fast Blog AI → Settings to start generating content.', 'super-fast-blog-ai' ),
				'goToSettings' => __( 'Open Settings',      'super-fast-blog-ai' ),
			],

			// ── Sidebar panel ─────────────────────────────────────────────────
			'sidebar' => [
				'panelTitle'         => __( 'Super Fast Blog AI',             'super-fast-blog-ai' ),
				'menuItemLabel'      => __( 'Super Fast Blog AI',             'super-fast-blog-ai' ),
				'tabGenerate'        => __( 'Generate',                       'super-fast-blog-ai' ),
				'tabMeta'            => __( 'SEO Meta',                       'super-fast-blog-ai' ),
				'tabBrandVoice'      => __( 'Brand Voice',                    'super-fast-blog-ai' ),
				'topicLabel'         => __( 'Topic / Title',                  'super-fast-blog-ai' ),
				'topicPlaceholder'   => __( 'e.g. Benefits of cold brew coffee', 'super-fast-blog-ai' ),
				'keywordLabel'       => __( 'Focus Keyword',                  'super-fast-blog-ai' ),
				'keywordPlaceholder' => __( 'e.g. cold brew',                'super-fast-blog-ai' ),
				'lengthLabel'        => __( 'Target word count',              'super-fast-blog-ai' ),
				'intentLabel'        => __( 'Search intent',                  'super-fast-blog-ai' ),
				'intentOptions'      => [
					'informational' => __( 'Informational', 'super-fast-blog-ai' ),
					'commercial'    => __( 'Commercial',    'super-fast-blog-ai' ),
					'transactional' => __( 'Transactional', 'super-fast-blog-ai' ),
					'navigational'  => __( 'Navigational',  'super-fast-blog-ai' ),
				],
				'generateOutline' => __( 'Generate Outline',     'super-fast-blog-ai' ),
				'generateArticle' => __( 'Write Full Article',   'super-fast-blog-ai' ),
				'outlineTitle'    => __( 'Article Outline',      'super-fast-blog-ai' ),
				'outlineHint'     => __( 'Review the outline, then generate each section or the full article.', 'super-fast-blog-ai' ),
				'generateSection' => __( 'Write Section',        'super-fast-blog-ai' ),
				'insertSection'   => __( 'Insert into editor',   'super-fast-blog-ai' ),
				/* translators: %s is the model name or provider */
				'wordCount'       => __( '%d words',             'super-fast-blog-ai' ),
				'metaGenerate'    => __( 'Generate Meta',        'super-fast-blog-ai' ),
				'metaTitle'       => __( 'Meta Title',           'super-fast-blog-ai' ),
				'metaDescription' => __( 'Meta Description',     'super-fast-blog-ai' ),
				'metaApply'       => __( 'Apply to Yoast / RankMath', 'super-fast-blog-ai' ),
				'brandVoiceOn'    => __( 'Brand voice active',   'super-fast-blog-ai' ),
				'brandVoiceOff'   => __( 'Brand voice disabled', 'super-fast-blog-ai' ),
				'brandVoiceHint'  => __( 'Analyze your existing posts to set a brand voice in Settings.', 'super-fast-blog-ai' ),
			],

			// ── Toolbar format buttons ─────────────────────────────────────────
			'toolbar' => [
				'rewriteLabel'           => __( 'Rewrite with AI',  'super-fast-blog-ai' ),
				'rewriteTitle'           => __( 'AI Rewrite',       'super-fast-blog-ai' ),
				'expandLabel'            => __( 'Expand',           'super-fast-blog-ai' ),
				'expandTitle'            => __( 'AI Expand',        'super-fast-blog-ai' ),
				'shortenLabel'           => __( 'Shorten',          'super-fast-blog-ai' ),
				'shortenTitle'           => __( 'AI Shorten',       'super-fast-blog-ai' ),
				'translateLabel'         => __( 'Translate',        'super-fast-blog-ai' ),
				'translateTitle'         => __( 'AI Translate',     'super-fast-blog-ai' ),
				'toneLabel'              => __( 'Change Tone',      'super-fast-blog-ai' ),
				'toneTitle'              => __( 'AI Tone',          'super-fast-blog-ai' ),
				'instructionPlaceholder' => __( 'Describe the rewrite (e.g. "make it funnier")', 'super-fast-blog-ai' ),
				'applyRewrite'           => __( 'Apply',            'super-fast-blog-ai' ),
			],

			// ── Write with AI block ────────────────────────────────────────────
			'block' => [
				'title'             => __( 'Write with AI',          'super-fast-blog-ai' ),
				'description'       => __( 'Generate content using AI directly inside the editor.', 'super-fast-blog-ai' ),
				'promptLabel'       => __( 'What should I write?',   'super-fast-blog-ai' ),
				'promptPlaceholder' => __( 'Describe the content you want to generate…', 'super-fast-blog-ai' ),
				'typeLabel'         => __( 'Content type',           'super-fast-blog-ai' ),
				'typeOptions'       => [
					'blog'    => __( 'Blog section',        'super-fast-blog-ai' ),
					'product' => __( 'Product description', 'super-fast-blog-ai' ),
					'meta'    => __( 'SEO meta',            'super-fast-blog-ai' ),
				],
				'generateBtn'   => __( 'Generate',         'super-fast-blog-ai' ),
				'insertBtn'     => __( 'Insert Content',   'super-fast-blog-ai' ),
				'regenerateBtn' => __( 'Regenerate',       'super-fast-blog-ai' ),
				'previewLabel'  => __( 'Preview',          'super-fast-blog-ai' ),
				'mockNotice'    => __( 'Mock response — connect an AI provider for real content.', 'super-fast-blog-ai' ),
			],

			// ── Classic editor modal ───────────────────────────────────────────
			'classic' => [
				'buttonTooltip' => __( 'Generate with Super Fast Blog AI', 'super-fast-blog-ai' ),
				'modalTitle'    => __( 'Super Fast Blog AI',               'super-fast-blog-ai' ),
				'topicLabel'    => __( 'Topic',                            'super-fast-blog-ai' ),
				'generateBtn'   => __( 'Generate',                         'super-fast-blog-ai' ),
				'insertBtn'     => __( 'Insert into Editor',               'super-fast-blog-ai' ),
				'closeBtn'      => __( 'Close',                            'super-fast-blog-ai' ),
			],
		];
	}
}
