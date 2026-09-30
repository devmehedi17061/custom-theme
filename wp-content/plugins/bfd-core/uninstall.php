<?php
// Runs when the plugin is deleted in wp-admin. Data is kept unless "Delete ALL plugin data"
// is ticked in Settings > BFD Options > Advanced (see includes/Uninstaller.php).
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/Uninstaller.php';
\BFD\Core\Uninstaller::run();
