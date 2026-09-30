<?php
namespace BFD\Core\Sections;

defined( 'ABSPATH' ) || exit;

/**
 * Additional built-in sections. Markup lives in the theme: template-parts/sections/{type}.php
 */
final class Builtin_Sections {

	private static function text( string $key, string $label ): array {
		return array( 'key' => $key, 'type' => 'text', 'label' => $label );
	}

	private static function area( string $key, string $label ): array {
		return array( 'key' => $key, 'type' => 'textarea', 'label' => $label );
	}

	private static function columns( string $default = '3' ): array {
		return array(
			'key'     => 'columns',
			'type'    => 'select',
			'label'   => __( 'Columns', 'bfd-core' ),
			'default' => $default,
			'options' => array( '2' => '2', '3' => '3', '4' => '4' ),
		);
	}

	/** Public post types that make sense in a listing (not pages/attachments). */
	private static function listable_types(): array {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type => $obj ) {
			if ( ! in_array( $type, array( 'page', 'attachment' ), true ) ) {
				$out[ $type ] = $obj->labels->name;
			}
		}
		return $out;
	}

	public static function definitions(): array {
		$repeater = static function ( string $key, string $label, array $subs ): array {
			return array( 'key' => $key, 'type' => 'repeater', 'label' => $label, 'sub_fields' => $subs );
		};

		return array(
			'image_text'   => array(
				'label'  => __( 'Image + Text', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					array( 'key' => 'text', 'type' => 'richtext', 'label' => __( 'Text (HTML allowed)', 'bfd-core' ) ),
					array( 'key' => 'image', 'type' => 'image', 'label' => __( 'Image', 'bfd-core' ) ),
					array(
						'key'     => 'image_side',
						'type'    => 'select',
						'label'   => __( 'Image position', 'bfd-core' ),
						'options' => array( 'right' => __( 'Right', 'bfd-core' ), 'left' => __( 'Left', 'bfd-core' ) ),
					),
					self::text( 'button_text', __( 'Button text', 'bfd-core' ) ),
					array( 'key' => 'button_url', 'type' => 'url', 'label' => __( 'Button URL', 'bfd-core' ) ),
				),
			),
			'posts'        => array(
				'label'  => __( 'Posts / Services / Projects grid', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					self::area( 'intro', __( 'Intro', 'bfd-core' ) ),
					array(
						'key'     => 'post_type',
						'type'    => 'select',
						'label'   => __( 'Content type', 'bfd-core' ),
						'default' => 'post',
						'options' => self::listable_types(),
					),
					array( 'key' => 'count', 'type' => 'number', 'label' => __( 'How many (1-24)', 'bfd-core' ) ),
					self::columns(),
					array( 'key' => 'show_excerpt', 'type' => 'checkbox', 'label' => __( 'Show excerpt', 'bfd-core' ) ),
					self::text( 'button_text', __( '"View all" button text', 'bfd-core' ) ),
					array( 'key' => 'button_url', 'type' => 'url', 'label' => __( '"View all" URL', 'bfd-core' ) ),
				),
			),
			'testimonials' => array(
				'label'  => __( 'Testimonials (from Testimonials post type)', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					array( 'key' => 'count', 'type' => 'number', 'label' => __( 'How many (1-24)', 'bfd-core' ) ),
					self::columns(),
				),
			),
			'pricing'      => array(
				'label'  => __( 'Pricing', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					self::area( 'intro', __( 'Intro', 'bfd-core' ) ),
					$repeater(
						'plans',
						__( 'Plans', 'bfd-core' ),
						array(
							self::text( 'name', __( 'Plan name', 'bfd-core' ) ),
							self::text( 'price', __( 'Price (e.g. $29)', 'bfd-core' ) ),
							self::text( 'period', __( 'Period (e.g. /month)', 'bfd-core' ) ),
							self::area( 'features', __( 'Features (one per line)', 'bfd-core' ) ),
							self::text( 'button_text', __( 'Button text', 'bfd-core' ) ),
							array( 'key' => 'button_url', 'type' => 'url', 'label' => __( 'Button URL', 'bfd-core' ) ),
							array( 'key' => 'highlight', 'type' => 'checkbox', 'label' => __( 'Highlight this plan', 'bfd-core' ) ),
						)
					),
				),
			),
			'team'         => array(
				'label'  => __( 'Team', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					self::area( 'intro', __( 'Intro', 'bfd-core' ) ),
					self::columns( '4' ),
					$repeater(
						'members',
						__( 'Members', 'bfd-core' ),
						array(
							array( 'key' => 'image', 'type' => 'image', 'label' => __( 'Photo', 'bfd-core' ) ),
							self::text( 'name', __( 'Name', 'bfd-core' ) ),
							self::text( 'role', __( 'Role', 'bfd-core' ) ),
							self::area( 'bio', __( 'Short bio', 'bfd-core' ) ),
						)
					),
				),
			),
			'stats'        => array(
				'label'  => __( 'Stats / Counters', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					$repeater(
						'items',
						__( 'Stats', 'bfd-core' ),
						array(
							self::text( 'number', __( 'Number (e.g. 250+)', 'bfd-core' ) ),
							self::text( 'label', __( 'Label', 'bfd-core' ) ),
						)
					),
				),
			),
			'logos'        => array(
				'label'  => __( 'Logo strip', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					$repeater(
						'logos',
						__( 'Logos', 'bfd-core' ),
						array(
							array( 'key' => 'image', 'type' => 'image', 'label' => __( 'Logo', 'bfd-core' ) ),
							self::text( 'name', __( 'Name (used as alt text)', 'bfd-core' ) ),
							array( 'key' => 'url', 'type' => 'url', 'label' => __( 'Link URL', 'bfd-core' ) ),
						)
					),
				),
			),
			'gallery'      => array(
				'label'  => __( 'Image gallery', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					self::columns(),
					$repeater(
						'images',
						__( 'Images', 'bfd-core' ),
						array(
							array( 'key' => 'image', 'type' => 'image', 'label' => __( 'Image', 'bfd-core' ) ),
							self::text( 'caption', __( 'Caption', 'bfd-core' ) ),
						)
					),
				),
			),
			'video'        => array(
				'label'  => __( 'Video (YouTube, Vimeo or .mp4)', 'bfd-core' ),
				'fields' => array(
					self::text( 'heading', __( 'Heading', 'bfd-core' ) ),
					array( 'key' => 'url', 'type' => 'url', 'label' => __( 'Video URL', 'bfd-core' ) ),
					self::text( 'caption', __( 'Caption', 'bfd-core' ) ),
				),
			),
		);
	}
}
