<?php
/**
 * Plugin activation handler.
 *
 * Creates / upgrades all database tables (legacy + new), writes default
 * options, and schedules background cron events.
 *
 * Uses dbDelta() for safe, idempotent schema management — safe to run on
 * every activation without data loss.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Activator {

	/**
	 * Entry point called by register_activation_hook().
	 *
	 * @param bool $network_wide True when activating network-wide on multisite.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			self::activate_network();
		} else {
			self::activate_single_site();
		}
	}

	// -------------------------------------------------------------------------
	// Activation entry points.
	// -------------------------------------------------------------------------

	private static function activate_single_site(): void {
		self::create_tables();
		self::set_default_options();
		self::schedule_cron_events();
		flush_rewrite_rules();
	}

	private static function activate_network(): void {
		$blog_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			self::activate_single_site();
			restore_current_blog();
		}
	}

	// -------------------------------------------------------------------------
	// Database schema.
	// -------------------------------------------------------------------------

	/**
	 * Create or upgrade all plugin tables.
	 *
	 * ┌─────────────────────────────────────────┐
	 * │  LEGACY (retained from v1)              │
	 * │  wp_slf_schedule_post_title_log         │
	 * │  wp_slf_generated_title                 │
	 * │                                         │
	 * │  NEW (from OAW, prefix: sfba_)          │
	 * │  wp_sfba_brand_voice                    │
	 * │  wp_sfba_generations   ← cost tracker   │
	 * │  wp_sfba_content_calendar               │
	 * │  wp_sfba_performance                    │
	 * │  wp_sfba_routing_rules                  │
	 * │  wp_sfba_internal_links                 │
	 * └─────────────────────────────────────────┘
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$statements = [];

		// ── LEGACY TABLE 1: schedule post title log ───────────────────────────
		// Retained from v1 — keeps all existing user data intact.
		$statements[] = "CREATE TABLE {$wpdb->prefix}slf_schedule_post_title_log (
			id        mediumint(9) NOT NULL AUTO_INCREMENT,
			title     text NOT NULL,
			status    varchar(1000) NOT NULL,
			postid    int(100) NOT NULL,
			charaters int(100) NOT NULL,
			modelused varchar(100) NOT NULL,
			indicat   varchar(100) NOT NULL,
			log_time  datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		// ── LEGACY TABLE 2: generated titles ─────────────────────────────────
		// Retained from v1 — keeps all existing generated title records.
		$statements[] = "CREATE TABLE {$wpdb->prefix}slf_generated_title (
			id             mediumint(9) NOT NULL AUTO_INCREMENT,
			promt_title    text NOT NULL,
			generate_title text NOT NULL,
			protid         int(100) NOT NULL,
			ctime          datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		// ── NEW TABLE 1: brand voice profile ─────────────────────────────────
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_brand_voice (
			id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id             BIGINT UNSIGNED NOT NULL DEFAULT 1,
			vocabulary_profile  LONGTEXT NOT NULL,
			avg_sentence_length SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			tone_keywords       LONGTEXT NOT NULL,
			style_markers       LONGTEXT NOT NULL,
			sample_posts        LONGTEXT NOT NULL,
			created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY site_id (site_id)
		) $charset_collate;";

		// ── NEW TABLE 2: generations — central AI cost/audit log ──────────────
		// Every AI operation across ALL features inserts a row here.
		// The `feature` column is MANDATORY on every insert.
		// Valid feature values:
		//   title_generation | blog_generation | seo_analysis |
		//   repurposing | internal_linking | brand_voice |
		//   calendar | image_generation
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_generations (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_id            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			provider           VARCHAR(64)   NOT NULL DEFAULT '',
			model              VARCHAR(128)  NOT NULL DEFAULT '',
			feature            VARCHAR(64)   NOT NULL DEFAULT '',
			prompt_tokens      INT UNSIGNED  NOT NULL DEFAULT 0,
			completion_tokens  INT UNSIGNED  NOT NULL DEFAULT 0,
			cost_usd           DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
			content_type       VARCHAR(64)   NOT NULL DEFAULT 'blog',
			generation_time_ms INT UNSIGNED  NOT NULL DEFAULT 0,
			created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY user_id (user_id),
			KEY provider (provider),
			KEY feature (feature),
			KEY created_at (created_at)
		) $charset_collate;";

		// ── NEW TABLE 3: content calendar ────────────────────────────────────
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_content_calendar (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title              VARCHAR(500)  NOT NULL DEFAULT '',
			target_keyword     VARCHAR(255)  NOT NULL DEFAULT '',
			keyword_volume     INT UNSIGNED  NOT NULL DEFAULT 0,
			keyword_difficulty TINYINT UNSIGNED NOT NULL DEFAULT 0,
			suggested_date     DATE NULL DEFAULT NULL,
			status             VARCHAR(32)   NOT NULL DEFAULT 'idea',
			post_id            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			source             VARCHAR(32)   NOT NULL DEFAULT 'manual',
			created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY suggested_date (suggested_date)
		) $charset_collate;";

		// ── NEW TABLE 4: performance snapshots (Google Search Console) ────────
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_performance (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id      BIGINT UNSIGNED NOT NULL,
			week_start   DATE           NOT NULL,
			impressions  INT UNSIGNED   NOT NULL DEFAULT 0,
			clicks       INT UNSIGNED   NOT NULL DEFAULT 0,
			avg_position DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
			ctr          DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
			source       VARCHAR(64)    NOT NULL DEFAULT 'search_console',
			created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY post_week (post_id, week_start),
			KEY post_id (post_id)
		) $charset_collate;";

		// ── NEW TABLE 5: model routing rules ──────────────────────────────────
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_routing_rules (
			id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			content_type VARCHAR(64)      NOT NULL DEFAULT 'blog',
			provider     VARCHAR(64)      NOT NULL DEFAULT '',
			model        VARCHAR(128)     NOT NULL DEFAULT '',
			max_tokens   INT UNSIGNED     NOT NULL DEFAULT 4096,
			temperature  DECIMAL(3,2)     NOT NULL DEFAULT 0.70,
			priority     TINYINT UNSIGNED NOT NULL DEFAULT 10,
			is_active    TINYINT(1)       NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY content_type (content_type),
			KEY is_active (is_active)
		) $charset_collate;";

		// ── NEW TABLE 6: internal links (keyword index + accepted links) ───────
		// Index rows  : target_post_id = 0, status = 'indexed' (one per post)
		// Link records: target_post_id > 0, status = 'accepted'
		$statements[] = "CREATE TABLE {$wpdb->prefix}sfba_internal_links (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			target_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			anchor_text    VARCHAR(500)    NOT NULL DEFAULT '',
			target_url     VARCHAR(2048)   NOT NULL DEFAULT '',
			context        LONGTEXT        NOT NULL DEFAULT '',
			status         VARCHAR(32)     NOT NULL DEFAULT 'indexed',
			created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY source_post_id (source_post_id),
			KEY target_post_id (target_post_id),
			KEY status (status)
		) $charset_collate;";

		foreach ( $statements as $sql ) {
			dbDelta( $sql );
		}

		// Store current DB schema version for future incremental migrations.
		update_option( 'sfba_db_version', SFBA_DB_VERSION );
	}

	// -------------------------------------------------------------------------
	// Cron scheduling.
	// -------------------------------------------------------------------------

	/**
	 * Schedule background cron events if not already scheduled.
	 *
	 * SFBA v1 cron hooks are preserved; OAW cron hooks are added.
	 */
	private static function schedule_cron_events(): void {
		// Register custom interval used by the log-pruning job.
		add_filter( 'cron_schedules', static function ( array $schedules ): array {
			$schedules['sfba_monthly'] = [
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => 'Once Monthly (Super Fast Blog AI)',
			];
			return $schedules;
		} );

		// ── SFBA v1 cron events (retained) ────────────────────────────────────
		// Same-day delayed posting.
		if ( ! wp_next_scheduled( 'otslf_publish_scheduled_posts' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'otslf_publish_scheduled_posts' );
		}
		// Later-date posting.
		if ( ! wp_next_scheduled( 'otslf_publish_posts_event' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'otslf_publish_posts_event' );
		}
		// Recurring posting.
		if ( ! wp_next_scheduled( 'otslf_publish_scheduled_posts_daily' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'otslf_publish_scheduled_posts_daily' );
		}

		// ── New OAW-sourced cron events ────────────────────────────────────────
		// GSC sync — weekly, staggered so jobs don't pile up.
		if ( ! wp_next_scheduled( 'sfba_sync_search_console' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'sfba_sync_search_console' );
		}
		// Content calendar idea refresh — weekly.
		if ( ! wp_next_scheduled( 'sfba_refresh_content_ideas' ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'weekly', 'sfba_refresh_content_ideas' );
		}
		// Generation log pruning — monthly.
		if ( ! wp_next_scheduled( 'sfba_prune_generation_log' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'sfba_monthly', 'sfba_prune_generation_log' );
		}
	}

	// -------------------------------------------------------------------------
	// Default options.
	// -------------------------------------------------------------------------

	/**
	 * Write default plugin options if they do not yet exist.
	 *
	 * add_option() is a no-op when the key already exists — safe on re-activation.
	 */
	private static function set_default_options(): void {
		$defaults = [
			'version'          => SFBA_VERSION,
			'providers'        => [],
			'default_provider' => 'openai',
			'default_model'    => 'gpt-4o',
			'routing_enabled'  => false,
			'debug_mode'       => false,
			'brand_voice'      => [
				'enabled'    => false,
				'last_run'   => null,
				'post_count' => 30,
			],
			'content' => [
				'language'      => 'English',
				'writing_style' => 'informative',
				'tone'          => 'formal',
				'word_count'    => 500,
				'subheadings'   => true,
				'heading_tag'   => 'h2',
				'heading_count' => 3,
				'faq'           => false,
				'toc'           => false,
				'pros_cons'     => false,
			],
			'seo' => [
				'keywords'  => true,
				'meta_desc' => true,
			],
			'images' => [
				'source'        => 'dalle3',
				'pixabay_key'   => '',
				'unsplash_key'  => '',
			],
			'publishing' => [
				'categories'   => [],
				'email_notify' => false,
			],
			'schedule' => [
				'type'          => 'sameday',
				'same_day_type' => 'immediately',
				'hours_delay'   => 0,
				'minutes_delay' => 0,
				'later_days'    => 1,
				'later_time'    => '09:00',
				'recur_days'    => 7,
				'recur_time'    => '09:00',
			],
			'search_console' => [
				'property'     => '',
				'access_token' => '',
			],
		];

		add_option( SFBA_OPTION_KEY, $defaults, '', 'no' );
	}
}
