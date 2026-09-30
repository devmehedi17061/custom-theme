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
 * @param mixed $default
 * @return mixed
 */
function bfd_option( string $key, $default = '' ) {
	$opts = get_option( 'bfd_options', array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $default;
}

/** Output breadcrumb navigation (also feeds the BreadcrumbList schema). */
function bfd_breadcrumbs(): void {
	\BFD\Core\Seo\Breadcrumbs::render();
}

/** Render a form built in Forms > Add New. */
function bfd_render_form( int $form_id ): void {
	\BFD\Core\Forms\Form_Renderer::render( $form_id );
}
