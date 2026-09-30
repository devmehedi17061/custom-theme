<?php
// Runs on plugin deletion. Data (posts, meta, submissions) is intentionally kept;
// add explicit cleanup here only for plugin-owned options/tables when they exist.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
