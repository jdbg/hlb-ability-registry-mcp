<?php
/**
 * Plugin Name:       HLB Ability Registry for MCP
 * Plugin URI:        https://github.com/jdbg/hlb-ability-registry-mcp
 * Description:       Exposes a curated, admin-controlled set of WordPress Abilities to the MCP Adapter so third-party tools and AI agents can interact with the site over MCP. Multisite-ready with network defaults and per-subsite overrides. Source: https://github.com/jdbg/hlb-ability-registry-mcp
 * Version:           1.6.3
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Jordan Hlebarov
 * Author URI:        https://jdbg.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hlb-ability-registry-mcp
 *
 * @package HLB\MCP
 */

defined( 'ABSPATH' ) || exit;

define( 'HLB_MCP_VERSION', '1.6.3' );
define( 'HLB_MCP_FILE', __FILE__ );
define( 'HLB_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'HLB_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'HLB_MCP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4-ish autoloader for the HLB\MCP namespace.
 *
 * HLB\MCP\Registry            -> inc/class-registry.php
 * HLB\MCP\Handlers\Content    -> inc/handlers/class-content.php
 *
 * @param string $class_name Fully-qualified class name being loaded.
 * @return void
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'HLB\\MCP\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$base     = array_pop( $parts );
		$file     = 'class-' . strtolower( str_replace( '_', '-', $base ) ) . '.php';
		$sub      = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$path     = HLB_MCP_DIR . 'inc/' . $sub . $file;

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

// Lifecycle hooks must be registered at top level.
register_activation_hook( __FILE__, [ '\HLB\MCP\Plugin', 'on_activation' ] );

// Boot.
\HLB\MCP\Plugin::instance();
