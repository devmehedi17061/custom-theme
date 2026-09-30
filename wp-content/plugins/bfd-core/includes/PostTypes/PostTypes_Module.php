<?php
namespace BFD\Core\PostTypes;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Custom post types and taxonomies. Override the list with the `bfd_post_types` and
 * `bfd_taxonomies` filters (return fewer entries to disable one).
 */
final class PostTypes_Module implements Module {

	public function slug(): string {
		return 'post-types';
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_types' ) );
		add_filter( 'bfd_sections_post_types', array( $this, 'enable_section_builder' ) );
	}

	private function post_types(): array {
		return (array) apply_filters(
			'bfd_post_types',
			array(
				'service'     => array(
					'singular' => __( 'Service', 'bfd-core' ),
					'plural'   => __( 'Services', 'bfd-core' ),
					'slug'     => 'services',
					'icon'     => 'dashicons-hammer',
					'archive'  => true,
					'public'   => true,
					'builder'  => true,
				),
				'project'     => array(
					'singular' => __( 'Project', 'bfd-core' ),
					'plural'   => __( 'Projects', 'bfd-core' ),
					'slug'     => 'projects',
					'icon'     => 'dashicons-portfolio',
					'archive'  => true,
					'public'   => true,
					'builder'  => true,
				),
				// Shown through sections/shortcodes, no pages of their own.
				'testimonial' => array(
					'singular' => __( 'Testimonial', 'bfd-core' ),
					'plural'   => __( 'Testimonials', 'bfd-core' ),
					'slug'     => 'testimonials',
					'icon'     => 'dashicons-format-quote',
					'archive'  => false,
					'public'   => false,
					'builder'  => false,
				),
			)
		);
	}

	private function taxonomies(): array {
		return (array) apply_filters(
			'bfd_taxonomies',
			array(
				'service_category' => array(
					'singular' => __( 'Service Category', 'bfd-core' ),
					'plural'   => __( 'Service Categories', 'bfd-core' ),
					'slug'     => 'service-category',
					'types'    => array( 'service' ),
				),
				'project_category' => array(
					'singular' => __( 'Project Category', 'bfd-core' ),
					'plural'   => __( 'Project Categories', 'bfd-core' ),
					'slug'     => 'project-category',
					'types'    => array( 'project' ),
				),
			)
		);
	}

	public function register_types(): void {
		foreach ( $this->post_types() as $type => $c ) {
			register_post_type(
				$type,
				array(
					'labels'              => array(
						'name'          => $c['plural'],
						'singular_name' => $c['singular'],
						'add_new_item'  => sprintf( /* translators: %s: singular name */ __( 'Add New %s', 'bfd-core' ), $c['singular'] ),
						'edit_item'     => sprintf( /* translators: %s: singular name */ __( 'Edit %s', 'bfd-core' ), $c['singular'] ),
					),
					'public'              => (bool) $c['public'],
					'publicly_queryable'  => (bool) $c['public'],
					'exclude_from_search' => ! $c['public'],
					'show_ui'             => true,
					'show_in_rest'        => true,
					'has_archive'         => (bool) $c['archive'],
					'rewrite'             => $c['public'] ? array( 'slug' => $c['slug'] ) : false,
					'menu_icon'           => $c['icon'],
					'menu_position'       => 21,
					'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				)
			);
		}

		foreach ( $this->taxonomies() as $tax => $c ) {
			register_taxonomy(
				$tax,
				$c['types'],
				array(
					'labels'            => array(
						'name'          => $c['plural'],
						'singular_name' => $c['singular'],
					),
					'hierarchical'      => true,
					'public'            => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => $c['slug'] ),
				)
			);
		}
	}

	/** Post types flagged `builder` also get the page section builder. */
	public function enable_section_builder( array $types ): array {
		foreach ( $this->post_types() as $type => $c ) {
			if ( ! empty( $c['builder'] ) ) {
				$types[] = $type;
			}
		}
		return array_values( array_unique( $types ) );
	}
}
