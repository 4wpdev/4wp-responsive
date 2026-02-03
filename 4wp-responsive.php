<?php
/**
 * Plugin Name: 4WP Responsive
 * Plugin URI: https://4wp.dev/plugin/4wp-responsive/
 * Description: Responsive spacing controls for core blocks with theme.json-first breakpoints and presets.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: 4WP.dev
 * Author URI: https://4wp.dev
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: 4wp-responsive
 *
 * @package ForWP\Responsive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Step 1: Define core plugin constants for consistent paths and URLs.
define( 'FORWP_RESPONSIVE_VERSION', '0.1.0' );
define( 'FORWP_RESPONSIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORWP_RESPONSIVE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Step 2: Load the main plugin bootstrap class.
require_once FORWP_RESPONSIVE_PLUGIN_DIR . 'includes/class-plugin.php';

// Step 3: Initialize the plugin after WordPress is loaded.
add_action( 'plugins_loaded', [ 'ForWP\Responsive\Plugin', 'init' ] );

