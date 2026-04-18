<?php
/**
 * Plugin deactivation handler.
 *
 * IMPORTANT: Deactivation is NOT uninstallation.
 *  - Do NOT drop database tables here.
 *  - Do NOT delete user options here.
 *  - Data removal belongs in uninstall.php.
 *
 * Safe deactivation tasks:
 *  - Flush rewrite rules (removes custom REST endpoints).
 *  - Clear all scheduled cron events registered by this plugin.
 *  - Clear transients that will be stale after deactivation.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Deactivator {

	/**
	 * Entry point called by register_deactivation_hook().
	 *
	 * @param bool $network_wide True when deactivating network-wide on multisite.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			self::deactivate_network();
		} else {
			self::deactivate_single_site();
		}
	}

	// -------------------------------------------------------------------------
	// Deactivation entry points.
	// -------------------------------------------------------------------------

	private static function deactivate_single_site(): void {
		self::clear_scheduled_events();
		self::clear_transients();
		flush_rewrite_rules();
	}

	private static function deactivate_network(): void {
		$blog_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			self::deactivate_single_site();
			restore_current_blog();
		}
	}

	// -------------------------------------------------------------------------
	// Cleanup helpers.
	// -------------------------------------------------------------------------

	/**
	 * Remove all WP-Cron events scheduled by this plugin.
	 *
	 * Includes both v1 SFBA cron hooks and new v2 hooks.
	 */
	private static function clear_scheduled_events(): void {
		$cron_hooks = [
			// ── SFBA v1 cron hooks (retained) ──────────────────────────────────
			'otslf_publish_scheduled_posts',       // same-day delayed posting
			'otslf_publish_posts_event',            // later-date posting
			'otslf_publish_scheduled_posts_daily',  // recurring posting

			// ── SFBA v2 new cron hooks ──────────────────────────────────────────
			'sfba_sync_search_console',    // GSC performance sync — weekly
			'sfba_refresh_content_ideas',  // calendar idea refresh — weekly
			'sfba_prune_generation_log',   // cost log pruning — monthly
		];

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Delete plugin transients that would be stale after deactivation.
	 *
	 * Only removes transients whose data is provider/API dependent.
	 * User content caches expire naturally or on uninstall.
	 */
	private static function clear_transients(): void {
		// Provider model lists (fetched from remote APIs — may change between versions).
		$transients = [
			'sfba_openai_models',
			'sfba_anthropic_models',
			'sfba_google_models',
			'sfba_openrouter_models',
			'sfba_deepseek_models',
			'sfba_mistral_models',
			'sfba_ollama_models',
		];

		foreach ( $transients as $key ) {
			delete_transient( $key );
		}
	}
}
