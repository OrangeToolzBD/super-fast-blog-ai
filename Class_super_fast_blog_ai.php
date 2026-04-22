<?php
/**
 * Plugin Name: Super Fast Blog AI – AI Content Generator, Repurposer & SEO Writer
 * Plugin URI:  https://waatechdigital.com/super-fast-ai-blog-revolutionizing-content-creation-in-wordpress/
 * Description: AI-powered blog content generator with 7 provider support, brand voice learning, content calendar, internal linking, cost tracking, and advanced model routing. Bring Your Own Key — no subscriptions.
 * Version:     2.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Tested up to: 6.9
 * Author:      waatechdigital
 * Author URI:  https://waatechdigital.com
 * License:     GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: super-fast-blog-ai
 * Domain Path: /languages
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants — v2.0
// ─────────────────────────────────────────────────────────────────────────────

define( 'SFBA_VERSION',        '2.0.0' );
define( 'SFBA_DB_VERSION',     '2.0.0' );
define( 'SFBA_PLUGIN_FILE',    __FILE__ );
define( 'SFBA_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SFBA_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'SFBA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SFBA_INCLUDES_DIR',   SFBA_PLUGIN_DIR . 'includes/' );
define( 'SFBA_PROVIDERS_DIR',  SFBA_INCLUDES_DIR . 'providers/' );
define( 'SFBA_ASSETS_URL',     SFBA_PLUGIN_URL . 'assets/' );
define( 'SFBA_OPTION_KEY',     'sfba_settings' );

// ─────────────────────────────────────────────────────────────────────────────
// Legacy constants (v1) — kept for backward compatibility.
// Existing code that reads OTSLF_* constants will still work.
// ─────────────────────────────────────────────────────────────────────────────

define( 'OTSLF_AI_BLOG_VERSION',    SFBA_VERSION );
define( 'OTSLF_AI_BLOG_FILE',       __FILE__ );
define( 'OTSLF_AI_BLOG_PATH',       SFBA_PLUGIN_DIR );
define( 'OTSLF_AI_BLOG_PLUGIN_DIR', SFBA_PLUGIN_DIR );
define( 'OTSLF_AI_BLOG_URL',        SFBA_PLUGIN_URL );
define( 'OTSLF_AI_BLOG_ASSETS',     SFBA_PLUGIN_DIR . 'assets' );

// ─────────────────────────────────────────────────────────────────────────────
// PSR-4-style autoloader for SFBA_ classes.
//
// Naming conventions:
//   SFBA_Provider_Interface  → includes/providers/interface-sfba-provider.php
//   SFBA_Provider_Abstract   → includes/providers/class-sfba-provider-abstract.php
//   SFBA_Provider_OpenAI     → includes/providers/class-sfba-openai.php
//   SFBA_Core                → includes/class-sfba-core.php
//   SFBA_Brand_Voice         → includes/class-sfba-brand-voice.php
//   SFBA_Legacy_Bridge       → includes/integrations/class-sfba-legacy-bridge.php
// ─────────────────────────────────────────────────────────────────────────────

spl_autoload_register( function ( string $class_name ): void {
	// Only handle SFBA_ prefixed classes.
	if ( 0 !== strpos( $class_name, 'SFBA_' ) ) {
		return;
	}

	// Convert class name → filename: SFBA_Foo_Bar → sfba-foo-bar.
	$slug = strtolower( str_replace( '_', '-', $class_name ) );

	if ( 'SFBA_Provider_Interface' === $class_name ) {
		// Interface lives in providers/ with interface- prefix.
		$file = SFBA_PROVIDERS_DIR . 'interface-sfba-provider.php';

	} elseif ( 0 === strpos( $class_name, 'SFBA_Provider_' ) ) {
		// SFBA_Provider_OpenAI    → providers/class-sfba-openai.php
		// SFBA_Provider_Abstract  → providers/class-sfba-provider-abstract.php
		$suffix = strtolower( str_replace( '_', '-', substr( $class_name, strlen( 'SFBA_Provider_' ) ) ) );
		if ( 'abstract' === $suffix ) {
			$file = SFBA_PROVIDERS_DIR . 'class-sfba-provider-abstract.php';
		} else {
			$file = SFBA_PROVIDERS_DIR . 'class-sfba-' . $suffix . '.php';
		}

	} elseif ( 'SFBA_Legacy_Bridge' === $class_name ) {
		// Legacy bridge lives in integrations/ subdirectory.
		$file = SFBA_INCLUDES_DIR . 'integrations/class-sfba-legacy-bridge.php';

	} else {
		// SFBA_Core → includes/class-sfba-core.php
		$file = SFBA_INCLUDES_DIR . 'class-' . $slug . '.php';
	}

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// ─────────────────────────────────────────────────────────────────────────────
// Legacy Composer autoloader (v1 Guzzle / OpenAI API classes).
// Kept during transition while old src/ classes are still used.
// ─────────────────────────────────────────────────────────────────────────────

$sfba_legacy_autoload = SFBA_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $sfba_legacy_autoload ) ) {
	require_once $sfba_legacy_autoload;
}

// ─────────────────────────────────────────────────────────────────────────────
// Activation hook.
// ─────────────────────────────────────────────────────────────────────────────

register_activation_hook( __FILE__, static function (): void {
	require_once SFBA_INCLUDES_DIR . 'class-sfba-activator.php';
	SFBA_Activator::activate();
} );

// ─────────────────────────────────────────────────────────────────────────────
// Deactivation hook.
// ─────────────────────────────────────────────────────────────────────────────

register_deactivation_hook( __FILE__, static function (): void {
	require_once SFBA_INCLUDES_DIR . 'class-sfba-deactivator.php';
	SFBA_Deactivator::deactivate();
} );

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap — fires after all plugins are loaded.
//
// Using plugins_loaded ensures other plugins (WooCommerce, Yoast, etc.)
// are available when our modules initialize their integrations.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', static function (): void {
	SFBA_Core::get_instance()->run();
} );
