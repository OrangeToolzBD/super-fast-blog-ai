<?php
/**
 * Legacy Bridge — backward compatibility for Super Fast Blog AI v1.
 *
 * Responsibilities:
 *   1. On first v2.0 activation, migrate all 29 existing `otslf_*` WordPress
 *      options into the new unified `sfba_settings` option array.
 *   2. Keep the old `otslf_*` options readable (never deleted) so any external
 *      code still referencing them continues to work.
 *   3. Expose the legacy DB tables (wp_slf_*) to the new system via helper methods.
 *   4. Provide backward-compatible wrappers for the old localized JS object
 *      `ajax_ob` so existing JS still functions during the transition.
 *
 * This class runs before every other module (registered first in SFBA_Core::init).
 * It is safe to call repeatedly — migration is guarded by a version flag.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Legacy_Bridge {

	/**
	 * Option key that marks a successful v2.0 migration.
	 *
	 * @var string
	 */
	private const MIGRATED_FLAG = 'sfba_v2_migrated';

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
		// Run migration as early as possible — on plugins_loaded priority 5.
		$loader->add_action( 'plugins_loaded', $this, 'maybe_migrate', 5 );
	}

	// -------------------------------------------------------------------------
	// Migration.
	// -------------------------------------------------------------------------

	/**
	 * Run the v1 → v2 migration if it hasn't been done yet.
	 *
	 * Guards against running twice via the MIGRATED_FLAG option.
	 * Safe to call on every page load — no-op after first run.
	 */
	public function maybe_migrate(): void {
		// Already migrated.
		if ( get_option( self::MIGRATED_FLAG ) === SFBA_VERSION ) {
			return;
		}

		// Only migrate if v1 data actually exists.
		if ( ! get_option( 'otslf_api_key' ) && ! get_option( 'otslf_model' ) ) {
			// No v1 data found — mark as done and return.
			update_option( self::MIGRATED_FLAG, SFBA_VERSION );
			return;
		}

		$this->run_migration();

		update_option( self::MIGRATED_FLAG, SFBA_VERSION );
	}

	/**
	 * Perform the full v1 → v2 option migration.
	 *
	 * Reads all 29 `otslf_*` options and merges them into `sfba_settings`.
	 * Uses array_replace_recursive so any keys already set by the activator
	 * defaults are preserved and only old values fill in the gaps.
	 */
	private function run_migration(): void {
		// Load current sfba_settings (may already have activator defaults).
		$current = get_option( SFBA_OPTION_KEY, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$migrated = $this->build_migrated_settings();

		// Merge: migrated values take precedence over empty defaults,
		// but don't overwrite any values the user has already set in v2.
		$merged = $this->deep_merge( $migrated, $current );

		update_option( SFBA_OPTION_KEY, $merged );

		// Log migration for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SFBA] v1 → v2 migration completed. sfba_settings updated.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * Read all 29 otslf_* options and build the equivalent sfba_settings array.
	 *
	 * @return array
	 */
	private function build_migrated_settings(): array {
		// ── AI Provider ───────────────────────────────────────────────────────
		$old_api_key  = get_option( 'otslf_api_key', '' );
		$old_model    = get_option( 'otslf_model', 'gpt-4o' );
		$old_provider = get_option( 'otslf_Provider', 'openai' );

		// Normalise provider slug to known values.
		$provider_slug = $this->normalise_provider( (string) $old_provider );

		// ── Image source ──────────────────────────────────────────────────────
		$old_image_src   = get_option( 'otslf_featured_image', 'dalle3' );
		$image_src_map   = [ 'pixabay' => 'pixabay', 'unsplash' => 'unsplash', 'dalle3' => 'dalle3', 'dall-e-3' => 'dalle3' ];
		$image_src       = $image_src_map[ $old_image_src ] ?? 'dalle3';

		// ── Schedule ──────────────────────────────────────────────────────────
		$old_schedule = get_option( 'otslf_schedule', 'sameday' );
		$schedule_map = [ 'sameday' => 'sameday', 'later' => 'later', 'recurring' => 'recurring' ];
		$schedule_type = $schedule_map[ $old_schedule ] ?? 'sameday';

		$old_same = get_option( 'otslf_same_schedule', 'immediately' );
		$same_day_map = [ 'immediately' => 'immediately', 'later_same_day' => 'later_same_day' ];
		$same_day_type = $same_day_map[ $old_same ] ?? 'immediately';

		// ── Taxonomy (categories) ─────────────────────────────────────────────
		$old_taxonomy = get_option( 'otslf_ot_taxonomy', [] );
		$categories   = is_array( $old_taxonomy ) ? array_map( 'absint', $old_taxonomy ) : [];

		return [
			'version'          => SFBA_VERSION,
			'providers'        => [
				$provider_slug => [
					'api_key' => $old_api_key ? $this->encrypt_legacy_key( (string) $old_api_key ) : '',
				],
			],
			'default_provider' => $provider_slug,
			'default_model'    => sanitize_text_field( (string) $old_model ),
			'routing_enabled'  => false,
			'debug_mode'       => false,
			'brand_voice'      => [
				'enabled'    => false,
				'last_run'   => null,
				'post_count' => 30,
			],
			'content' => [
				'language'      => sanitize_text_field( (string) get_option( 'otslf_language_select', 'English' ) ),
				'writing_style' => sanitize_text_field( (string) get_option( 'otslf_written_select', 'informative' ) ),
				'tone'          => sanitize_text_field( (string) get_option( 'otslf_language_tone', 'formal' ) ),
				'word_count'    => absint( get_option( 'otslf_word_count', 500 ) ),
				'subheadings'   => (bool) get_option( 'otslf_sub_heading', false ),
				'heading_tag'   => sanitize_text_field( (string) get_option( 'otslf_htaging', 'h2' ) ),
				'heading_count' => absint( get_option( 'otslf_number_h', 3 ) ),
				'faq'           => (bool) get_option( 'otslf_list_faq', false ),
				'toc'           => (bool) get_option( 'otslf_table_con', false ),
				'pros_cons'     => (bool) get_option( 'otslf_poscon', false ),
			],
			'seo' => [
				'keywords'  => (bool) get_option( 'otslf_seokeyword', true ),
				'meta_desc' => (bool) get_option( 'otslf_meta_des', true ),
			],
			'images' => [
				'source'        => $image_src,
				'pixabay_key'   => sanitize_text_field( (string) get_option( 'otslf_image_generate_api_key', '' ) ),
				'unsplash_key'  => sanitize_text_field( (string) get_option( 'otslf_unsplash_generate_api_key', '' ) ),
			],
			'publishing' => [
				'categories'   => $categories,
				'email_notify' => (bool) get_option( 'otslf_email_notification', false ),
			],
			'schedule' => [
				'type'          => $schedule_type,
				'same_day_type' => $same_day_type,
				'hours_delay'   => absint( get_option( 'otslf_hoursInput', 0 ) ),
				'minutes_delay' => absint( get_option( 'otslf_minutesInput', 0 ) ),
				'later_days'    => absint( get_option( 'otslf_laterdate', 1 ) ),
				'later_time'    => sanitize_text_field( (string) get_option( 'otslf_oclock', '09:00' ) ),
				'recur_days'    => absint( get_option( 'otslf_recurdate', 7 ) ),
				'recur_time'    => sanitize_text_field( (string) get_option( 'otslf_rcuroclock', '09:00' ) ),
			],
			'search_console' => [
				'property'     => '',
				'access_token' => '',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Legacy DB table accessors.
	// -------------------------------------------------------------------------

	/**
	 * Return the legacy schedule_post_title_log table name.
	 *
	 * @global wpdb $wpdb
	 * @return string
	 */
	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'slf_schedule_post_title_log';
	}

	/**
	 * Return the legacy generated_title table name.
	 *
	 * @global wpdb $wpdb
	 * @return string
	 */
	public static function titles_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'slf_generated_title';
	}

	/**
	 * Return the new generations (cost tracker) table name.
	 *
	 * @global wpdb $wpdb
	 * @return string
	 */
	public static function generations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'sfba_generations';
	}

	// -------------------------------------------------------------------------
	// Backward-compatible option readers.
	// -------------------------------------------------------------------------

	/**
	 * Read a v1 `otslf_*` option, falling back to the equivalent v2 setting.
	 *
	 * Allows old code to call get_option('otslf_model') and still get the
	 * correct value even after migration to sfba_settings.
	 *
	 * @param string $otslf_key e.g. 'otslf_model'
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get_legacy_option( string $otslf_key, mixed $default = false ): mixed {
		// Try the original option first (still in the DB, never deleted).
		$value = get_option( $otslf_key, null );
		if ( null !== $value ) {
			return $value;
		}

		// Fall back to the equivalent sfba_settings key.
		$map = $this->legacy_option_map();
		if ( isset( $map[ $otslf_key ] ) ) {
			return $this->core->settings->get( $map[ $otslf_key ], $default );
		}

		return $default;
	}

	/**
	 * Maps every otslf_* option key to its dot-notation sfba_settings path.
	 *
	 * @return array<string, string>
	 */
	private function legacy_option_map(): array {
		return [
			'otslf_api_key'                   => 'providers.openai.api_key',
			'otslf_model'                     => 'default_model',
			'otslf_Provider'                  => 'default_provider',
			'otslf_language_select'           => 'content.language',
			'otslf_written_select'            => 'content.writing_style',
			'otslf_language_tone'             => 'content.tone',
			'otslf_word_count'                => 'content.word_count',
			'otslf_sub_heading'               => 'content.subheadings',
			'otslf_htaging'                   => 'content.heading_tag',
			'otslf_number_h'                  => 'content.heading_count',
			'otslf_list_faq'                  => 'content.faq',
			'otslf_table_con'                 => 'content.toc',
			'otslf_poscon'                    => 'content.pros_cons',
			'otslf_seokeyword'                => 'seo.keywords',
			'otslf_meta_des'                  => 'seo.meta_desc',
			'otslf_featured_image'            => 'images.source',
			'otslf_image_generate_api_key'    => 'images.pixabay_key',
			'otslf_unsplash_generate_api_key' => 'images.unsplash_key',
			'otslf_ot_taxonomy'               => 'publishing.categories',
			'otslf_email_notification'        => 'publishing.email_notify',
			'otslf_schedule'                  => 'schedule.type',
			'otslf_same_schedule'             => 'schedule.same_day_type',
			'otslf_hoursInput'                => 'schedule.hours_delay',
			'otslf_minutesInput'              => 'schedule.minutes_delay',
			'otslf_laterdate'                 => 'schedule.later_days',
			'otslf_oclock'                    => 'schedule.later_time',
			'otslf_recurdate'                 => 'schedule.recur_days',
			'otslf_rcuroclock'                => 'schedule.recur_time',
		];
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	/**
	 * Normalise a v1 provider string to a v2 slug.
	 *
	 * @param string $raw
	 * @return string
	 */
	private function normalise_provider( string $raw ): string {
		$map = [
			'openai'    => 'openai',
			'Open AI'   => 'openai',
			'ChatGPT'   => 'openai',
			'anthropic' => 'anthropic',
			'Claude'    => 'anthropic',
			'google'    => 'google',
			'Gemini'    => 'google',
		];
		return $map[ $raw ] ?? 'openai';
	}

	/**
	 * Encrypt a legacy plain-text API key using the v2 AES-256-CBC scheme.
	 *
	 * This re-encrypts the key with the new scheme so it's stored securely.
	 * The encryption logic mirrors SFBA_Settings::encrypt_api_key().
	 *
	 * @param string $plain_key
	 * @return string Encrypted + base64-encoded, or base64 fallback.
	 */
	private function encrypt_legacy_key( string $plain_key ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$secret = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY . 'oaw_api_key', true );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain_key, 'AES-256-CBC', $secret, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return base64_encode( $plain_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Deep merge two arrays — $base values fill in gaps in $priority.
	 * $priority wins whenever a key exists in both.
	 *
	 * @param array $base     Migrated values (fill-in layer).
	 * @param array $priority Existing values (kept as-is).
	 * @return array
	 */
	private function deep_merge( array $base, array $priority ): array {
		$merged = $base;
		foreach ( $priority as $key => $value ) {
			if ( isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) && is_array( $value ) ) {
				$merged[ $key ] = $this->deep_merge( $merged[ $key ], $value );
			} elseif ( '' !== $value && null !== $value && [] !== $value ) {
				// Priority value is non-empty — use it.
				$merged[ $key ] = $value;
			}
		}
		return $merged;
	}
}
