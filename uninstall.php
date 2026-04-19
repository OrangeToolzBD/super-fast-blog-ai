<?php
/**
 * Uninstall handler for Super Fast Blog AI.
 *
 * WordPress calls this file when the user deletes the plugin from the
 * Plugins screen. It runs before WordPress removes the plugin directory,
 * so we can clean up all database tables, options, and transients here.
 *
 * IMPORTANT:
 *  - This file is executed in a clean WP context — the plugin's main file
 *    has NOT been loaded. Do NOT rely on plugin constants or autoloader.
 *  - Only runs for "delete plugin" — NOT on deactivation.
 *  - Guard with WP_UNINSTALL_PLUGIN to prevent direct access.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Remove the vendor/ directory first.
//
// On Windows, deeply-nested Guzzle paths inside vendor/ can exceed the
// MAX_PATH limit (260 chars), causing WP_Filesystem::delete() to silently
// fail and return the "Could not fully remove the plugin" error.
//
// Pre-deleting vendor/ here (using PHP's own filesystem functions which
// handle long paths better via the \\?\ prefix trick) removes those
// problematic paths before WordPress tries to delete the plugin directory.
// ─────────────────────────────────────────────────────────────────────────────

$vendor_dir = __DIR__ . DIRECTORY_SEPARATOR . 'vendor';

if ( is_dir( $vendor_dir ) ) {
	sfba_uninstall_rmdir_recursive( $vendor_dir );
}

/**
 * Recursively delete a directory and all its contents.
 *
 * Uses the \\?\-prefixed absolute path on Windows to bypass the MAX_PATH
 * 260-character limit that can cause rmdir/unlink to fail on deeply-nested
 * vendor paths.
 *
 * @param string $dir Absolute path to the directory to remove.
 */
function sfba_uninstall_rmdir_recursive( string $dir ): void {
	// On Windows, prefix the path to unlock long-path support.
	if ( PHP_OS_FAMILY === 'Windows' && ! str_starts_with( $dir, '\\\\?\\' ) ) {
		$long_dir = '\\\\?\\' . str_replace( '/', '\\', realpath( $dir ) ?: $dir );
	} else {
		$long_dir = $dir;
	}

	if ( ! is_dir( $long_dir ) ) {
		return;
	}

	$items = @scandir( $long_dir );
	if ( ! $items ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$path = $long_dir . DIRECTORY_SEPARATOR . $item;

		if ( is_dir( $path ) && ! is_link( $path ) ) {
			sfba_uninstall_rmdir_recursive( $path );
		} else {
			// Make writable in case the file is read-only (common in vendor/).
			@chmod( $path, 0755 );
			@unlink( $path );
		}
	}

	@chmod( $long_dir, 0755 );
	@rmdir( $long_dir );
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Drop plugin database tables.
//
// Only runs if the "delete data on uninstall" option is set.
// Default: false — we never silently destroy user data.
// ─────────────────────────────────────────────────────────────────────────────

global $wpdb;

$settings = get_option( 'sfba_settings', [] );
$delete_data = ! empty( $settings['general']['delete_data_on_uninstall'] );

if ( $delete_data ) {
	$tables = [
		// New v2 tables (sfba_ prefix)
		"{$wpdb->prefix}sfba_brand_voice",
		"{$wpdb->prefix}sfba_generations",
		"{$wpdb->prefix}sfba_content_calendar",
		"{$wpdb->prefix}sfba_performance",
		"{$wpdb->prefix}sfba_routing_rules",
		"{$wpdb->prefix}sfba_internal_links",

		// Legacy v1 tables (slf_ prefix)
		"{$wpdb->prefix}slf_schedule_post_title_log",
		"{$wpdb->prefix}slf_generated_title",
	];

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	// ── Remove all plugin options ──────────────────────────────────────────
	delete_option( 'sfba_settings' );
	delete_option( 'sfba_db_version' );

	// Legacy v1 options
	delete_option( 'otslf_ai_blog_settings' );
	delete_option( 'otslf_ai_blog_db_version' );

	// ── Remove all plugin transients ───────────────────────────────────────
	$transients = [
		'sfba_openai_models',
		'sfba_anthropic_models',
		'sfba_google_models',
		'sfba_openrouter_models',
		'sfba_deepseek_models',
		'sfba_mistral_models',
		'sfba_ollama_models',
		'sfba_cost_summary',
		'sfba_budget_alert',
	];

	foreach ( $transients as $key ) {
		delete_transient( $key );
	}

	// Also sweep for any sfba_ transients stored with timeout records.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_sfba_%'
		    OR option_name LIKE '_transient_timeout_sfba_%'"
	);
}
