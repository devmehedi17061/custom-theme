<?php
namespace BFD\Core\Sections;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	public static function render( int $post_id = 0 ): bool {
		$post_id = $post_id ?: (int) get_the_ID();
		return self::render_list( Section_Registry::get_sections( $post_id ) );
	}

	/** Render an already-sanitized list of sections. */
	public static function render_list( array $sections ): bool {
		if ( ! $sections ) {
			return false;
		}

		foreach ( $sections as $i => $section ) {
			$def = isset( $section['type'] ) ? Section_Registry::get( $section['type'] ) : null;
			if ( ! $def ) {
				continue;
			}
			$container = $def['container'] ?? true;
			$settings = $section['settings'] ?? array();

			printf(
				'<section id="section-%1$d" class="bfd-section bfd-section--%2$s bfd-bg--%3$s bfd-align--%4$s">%5$s',
				(int) $i + 1,
				esc_attr( $section['type'] ),
				esc_attr( $settings['background'] ?? 'none' ),
				esc_attr( $settings['align'] ?? 'left' ),
				$container ? '<div class="container">' : ''
			);
			if ( isset( $def['custom_id'] ) ) {
				Custom_Sections::render( $def, $section['fields'] ?? array() );
			} else {
				get_template_part(
					'template-parts/sections/' . $section['type'],
					null,
					array(
						'fields'   => $section['fields'] ?? array(),
						'settings' => $settings,
						'index'    => $i,
					)
				);
			}
			echo $container ? '</div></section>' : '</section>';
		}
		return true;
	}
}
