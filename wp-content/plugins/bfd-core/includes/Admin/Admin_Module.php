<?php
namespace BFD\Core\Admin;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI tweaks: pages use the section builder instead of Gutenberg.
 */
final class Admin_Module implements Module {

	public function slug(): string {
		return 'admin';
	}

	/** Post types whose content editor is replaced by the section builder. */
	private function builder_types(): array {
		return (array) apply_filters( 'bfd_disable_editor_types', array( 'page' ) );
	}

	public function register(): void {
		( new Options_Page() )->register();

		add_filter( 'use_block_editor_for_post_type', array( $this, 'maybe_disable_block_editor' ), 10, 2 );
		add_action( 'init', array( $this, 'remove_editor_support' ), 20 );
	}

	public function maybe_disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		return in_array( $post_type, $this->builder_types(), true ) ? false : $use_block_editor;
	}

	public function remove_editor_support(): void {
		foreach ( $this->builder_types() as $type ) {
			remove_post_type_support( $type, 'editor' );
		}
	}
}
