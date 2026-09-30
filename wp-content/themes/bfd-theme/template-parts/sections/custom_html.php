<?php
// Stored HTML was sanitized on save: unfiltered only for users with the unfiltered_html capability.
$f = $args['fields'] ?? array();
echo $f['html'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
