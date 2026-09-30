<?php
namespace BFD\Core\Fields;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Field types and meta boxes (Phase 3).
 */
final class Fields_Module implements Module {

	public function slug(): string {
		return 'fields';
	}

	public function register(): void {
		// Hooks are added in later phases.
	}
}
