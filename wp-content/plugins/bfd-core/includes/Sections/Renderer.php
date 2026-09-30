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

			$classes = array(
				'bfd-section',
				'bfd-section--' . $section['type'],
				'bfd-bg--' . ( $settings['background'] ?? 'none' ),
				'bfd-align--' . ( $settings['align'] ?? 'left' ),
				'bfd-pad--' . ( $settings['padding'] ?? 'normal' ),
			);
			$hide = $settings['hide'] ?? 'none';
			if ( in_array( $hide, array( 'mobile', 'desktop' ), true ) ) {
				$classes[] = 'bfd-hide-' . $hide;
			}
			$style = '';
			if ( ! empty( $settings['background_image'] ) ) {
				$url = wp_get_attachment_image_url( (int) $settings['background_image'], 'full' );
				if ( $url ) {
					$classes[] = 'has-bg-image';
					$style     = 'background-image:url(' . esc_url( $url ) . ')';
					if ( ! empty( $settings['overlay'] ) ) {
						$classes[] = 'has-overlay';
					}
				}
			}
			foreach ( preg_split( '/\s+/', (string) ( $settings['css_class'] ?? '' ) ) as $extra ) {
				if ( '' !== sanitize_html_class( $extra ) ) {
					$classes[] = sanitize_html_class( $extra );
				}
			}
			$id = ! empty( $settings['anchor'] ) ? sanitize_title( $settings['anchor'] ) : 'section-' . ( (int) $i + 1 );

			printf(
				'<section id="%1$s" class="%2$s"%3$s>%4$s',
				esc_attr( $id ),
				esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ),
				$style ? ' style="' . esc_attr( $style ) . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				$container ? '<div class="container">' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
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
