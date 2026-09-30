<?php
/**
 * BFD Theme bootstrap. Presentation only; functionality belongs in bfd-core.
 *
 * @package bfd-theme
 */

defined( 'ABSPATH' ) || exit;

define( 'BFD_THEME_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'BFD_THEME_DIR', get_template_directory() );
define( 'BFD_THEME_URI', get_template_directory_uri() );

require_once BFD_THEME_DIR . '/inc/theme-support.php';
require_once BFD_THEME_DIR . '/inc/menus.php';
require_once BFD_THEME_DIR . '/inc/enqueue.php';
require_once BFD_THEME_DIR . '/inc/cleanup.php';
require_once BFD_THEME_DIR . '/inc/template-tags.php';
require_once BFD_THEME_DIR . '/inc/customize.php';
