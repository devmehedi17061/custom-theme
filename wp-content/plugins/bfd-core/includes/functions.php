<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render the page's sections. Returns false when the post has none.
 * Section markup lives in the active theme: template-parts/sections/{type}.php
 */
function bfd_render_sections( int $post_id = 0 ): bool {
	return \BFD\Core\Sections\Renderer::render( $post_id );
}

function bfd_has_sections( int $post_id = 0 ): bool {
	return (bool) \BFD\Core\Sections\Section_Registry::get_sections( $post_id ?: (int) get_the_ID() );
}

/**
 * Site-wide option from Settings > BFD Options.
 *
 * @param mixed $fallback
 * @return mixed
 */
function bfd_option( string $key, $fallback = '' ) {
	$opts = get_option( 'bfd_options', array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $fallback;
}

/** Output breadcrumb navigation (also feeds the BreadcrumbList schema). */
function bfd_breadcrumbs(): void {
	\BFD\Core\Seo\Breadcrumbs::render();
}

/** Render a form built in Forms > Add New. */
function bfd_render_form( int $form_id ): void {
	\BFD\Core\Forms\Form_Renderer::render( $form_id );
}

/** Font stacks selectable in BFD Options > Design (system fonts only: no external requests). */
function bfd_font_stacks(): array {
	return array(
		'system'        => array( 'System UI (default)', 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif' ),
		'humanist'      => array( 'Humanist sans', 'Seravek, "Gill Sans Nova", Ubuntu, Calibri, "DejaVu Sans", "Source Sans Pro", sans-serif' ),
		'geometric'     => array( 'Geometric sans', 'Avenir, Montserrat, Corbel, "URW Gothic", "Source Sans Pro", sans-serif' ),
		'serif'         => array( 'Serif', 'Charter, "Bitstream Charter", "Sitka Text", Cambria, serif' ),
		'classic-serif' => array( 'Classic serif', '"Iowan Old Style", "Palatino Linotype", "URW Palladio L", P052, serif' ),
		'mono'          => array( 'Monospace', 'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, monospace' ),
	);
}
