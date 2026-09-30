<?php
/**
 * Plugin Name: BFD Core
 * Description: Core functionality for BFD sites: fields, section builder, forms, SEO, security, post types.
 * Version:     0.4.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * License:     GPL-2.0-or-later
 * Text Domain: bfd-core
 */

defined( 'ABSPATH' ) || exit;

define( 'BFD_CORE_VERSION', '0.4.0' );
define( 'BFD_CORE_FILE', __FILE__ );
define( 'BFD_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BFD_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BFD_CORE_DIR . 'includes/Autoloader.php';
BFD\Core\Autoloader::register( 'BFD\\Core\\', BFD_CORE_DIR . 'includes/' );

require_once BFD_CORE_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( BFD\Core\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( BFD\Core\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( BFD\Core\Plugin::instance(), 'boot' ) );
