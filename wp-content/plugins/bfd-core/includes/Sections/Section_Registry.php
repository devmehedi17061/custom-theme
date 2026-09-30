<?php
namespace BFD\Core\Sections;

use BFD\Core\Fields\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class Section_Registry {

	public const META_KEY      = '_bfd_sections';
	private const MAX_SECTIONS = 100;

	private static ?array $definitions = null;

	/** Options shared by every section. */
	public static function settings_fields(): array {
		return array(
			array(
				'key'     => 'background',
				'type'    => 'select',
				'label'   => __( 'Background', 'bfd-core' ),
				'options' => array(
					'none'    => __( 'None', 'bfd-core' ),
					'light'   => __( 'Light', 'bfd-core' ),
					'dark'    => __( 'Dark', 'bfd-core' ),
					'primary' => __( 'Primary color', 'bfd-core' ),
				),
			),
			array(
				'key'   => 'background_image',
				'type'  => 'image',
				'label' => __( 'Background image', 'bfd-core' ),
			),
			array(
				'key'   => 'overlay',
				'type'  => 'checkbox',
				'label' => __( 'Dark overlay on background image', 'bfd-core' ),
			),
			array(
				'key'     => 'align',
				'type'    => 'select',
				'label'   => __( 'Text alignment', 'bfd-core' ),
				'options' => array(
					'left'   => __( 'Left', 'bfd-core' ),
					'center' => __( 'Center', 'bfd-core' ),
				),
			),
			array(
				'key'     => 'padding',
				'type'    => 'select',
				'label'   => __( 'Vertical spacing', 'bfd-core' ),
				'default' => 'normal',
				'options' => array(
					'normal' => __( 'Normal', 'bfd-core' ),
					'none'   => __( 'None', 'bfd-core' ),
					'small'  => __( 'Small', 'bfd-core' ),
					'large'  => __( 'Large', 'bfd-core' ),
				),
			),
			array(
				'key'     => 'hide',
				'type'    => 'select',
				'label'   => __( 'Visibility', 'bfd-core' ),
				'options' => array(
					'none'    => __( 'Show everywhere', 'bfd-core' ),
					'mobile'  => __( 'Hide on mobile', 'bfd-core' ),
					'desktop' => __( 'Hide on desktop', 'bfd-core' ),
				),
			),
			array(
				'key'   => 'anchor',
				'type'  => 'slug',
				'label' => __( 'Anchor ID (for menu links like #pricing)', 'bfd-core' ),
			),
			array(
				'key'   => 'css_class',
				'type'  => 'classes',
				'label' => __( 'Extra CSS classes', 'bfd-core' ),
			),
		);
	}

	/** @return array<string, array{label:string, fields:array}> */
	public static function all(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$defs = array(
			'hero'        => array(
				'label'  => __( 'Hero', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'heading',
						'type'  => 'text',
						'label' => __( 'Heading', 'bfd-core' ),
					),
					array(
						'key'   => 'subheading',
						'type'  => 'textarea',
						'label' => __( 'Subheading', 'bfd-core' ),
					),
					array(
						'key'   => 'image',
						'type'  => 'image',
						'label' => __( 'Image', 'bfd-core' ),
					),
					array(
						'key'   => 'button_text',
						'type'  => 'text',
						'label' => __( 'Button text', 'bfd-core' ),
					),
					array(
						'key'   => 'button_url',
						'type'  => 'url',
						'label' => __( 'Button URL', 'bfd-core' ),
					),
				),
			),
			'features'    => array(
				'label'  => __( 'Features / Cards', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'heading',
						'type'  => 'text',
						'label' => __( 'Heading', 'bfd-core' ),
					),
					array(
						'key'   => 'intro',
						'type'  => 'textarea',
						'label' => __( 'Intro', 'bfd-core' ),
					),
					array(
						'key'     => 'columns',
						'type'    => 'select',
						'label'   => __( 'Columns', 'bfd-core' ),
						'default' => '3',
						'options' => array(
							'2' => '2',
							'3' => '3',
							'4' => '4',
						),
					),
					array(
						'key'        => 'items',
						'type'       => 'repeater',
						'label'      => __( 'Cards', 'bfd-core' ),
						'sub_fields' => array(
							array(
								'key'   => 'image',
								'type'  => 'image',
								'label' => __( 'Image', 'bfd-core' ),
							),
							array(
								'key'   => 'title',
								'type'  => 'text',
								'label' => __( 'Title', 'bfd-core' ),
							),
							array(
								'key'   => 'text',
								'type'  => 'textarea',
								'label' => __( 'Text', 'bfd-core' ),
							),
							array(
								'key'   => 'url',
								'type'  => 'url',
								'label' => __( 'Link URL', 'bfd-core' ),
							),
						),
					),
				),
			),
			'cta'         => array(
				'label'  => __( 'Call to Action', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'heading',
						'type'  => 'text',
						'label' => __( 'Heading', 'bfd-core' ),
					),
					array(
						'key'   => 'text',
						'type'  => 'textarea',
						'label' => __( 'Text', 'bfd-core' ),
					),
					array(
						'key'   => 'button_text',
						'type'  => 'text',
						'label' => __( 'Button text', 'bfd-core' ),
					),
					array(
						'key'   => 'button_url',
						'type'  => 'url',
						'label' => __( 'Button URL', 'bfd-core' ),
					),
				),
			),
			'faq'         => array(
				'label'  => __( 'FAQ', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'heading',
						'type'  => 'text',
						'label' => __( 'Heading', 'bfd-core' ),
					),
					array(
						'key'        => 'items',
						'type'       => 'repeater',
						'label'      => __( 'Questions', 'bfd-core' ),
						'sub_fields' => array(
							array(
								'key'   => 'question',
								'type'  => 'text',
								'label' => __( 'Question', 'bfd-core' ),
							),
							array(
								'key'   => 'answer',
								'type'  => 'textarea',
								'label' => __( 'Answer', 'bfd-core' ),
							),
						),
					),
				),
			),
			'rich_text'   => array(
				'label'  => __( 'Rich Text', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'heading',
						'type'  => 'text',
						'label' => __( 'Heading', 'bfd-core' ),
					),
					array(
						'key'   => 'content',
						'type'  => 'richtext',
						'label' => __( 'Content (HTML allowed)', 'bfd-core' ),
					),
				),
			),
			'custom_html' => array(
				'label'  => __( 'Custom HTML', 'bfd-core' ),
				'fields' => array(
					array(
						'key'   => 'html',
						'type'  => 'html',
						'label' => __( 'HTML (unfiltered only for users with the unfiltered_html capability)', 'bfd-core' ),
					),
				),
			),
		);

		$defs += Builtin_Sections::definitions();

		$defs['form'] = array(
			'label'  => __( 'Form', 'bfd-core' ),
			'fields' => array(
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'bfd-core' ),
				),
				array(
					'key'   => 'intro',
					'type'  => 'textarea',
					'label' => __( 'Intro', 'bfd-core' ),
				),
				array(
					'key'     => 'form_id',
					'type'    => 'select',
					'label'   => __( 'Form (create under Forms)', 'bfd-core' ),
					'default' => '',
					'options' => array( '' => __( '— Select a form —', 'bfd-core' ) ) + array_map( 'strval', \BFD\Core\Forms\Form_Definition::published() ),
				),
			),
		);

		$defs += Custom_Sections::definitions();

		self::$definitions = (array) apply_filters( 'bfd_section_definitions', $defs );
		return self::$definitions;
	}

	public static function get( string $type ): ?array {
		return self::all()[ $type ] ?? null;
	}

	/** Definitions shaped for the admin JS (select options as ordered lists). */
	public static function for_js(): array {
		$out = array();
		foreach ( self::all() as $type => $def ) {
			$out[] = array(
				'type'   => $type,
				'label'  => $def['label'],
				'fields' => self::fields_for_js( $def['fields'] ),
			);
		}
		return array(
			'sections' => $out,
			'settings' => self::fields_for_js( self::settings_fields() ),
		);
	}

	private static function fields_for_js( array $fields ): array {
		foreach ( $fields as &$f ) {
			if ( isset( $f['options'] ) ) {
				$list = array();
				foreach ( $f['options'] as $value => $label ) {
					$list[] = array(
						'value' => (string) $value,
						'label' => (string) $label,
					);
				}
				$f['options'] = $list;
			}
			if ( isset( $f['sub_fields'] ) ) {
				$f['sub_fields'] = self::fields_for_js( $f['sub_fields'] );
			}
		}
		return $fields;
	}

	/** Validate and sanitize a decoded sections array against the registry. */
	public static function sanitize_sections( array $raw ): array {
		$clean = array();
		foreach ( array_slice( array_values( $raw ), 0, self::MAX_SECTIONS ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['type'] ) || ! is_string( $item['type'] ) ) {
				continue;
			}
			$def = self::get( $item['type'] );
			if ( ! $def ) {
				continue;
			}
			$clean[] = array(
				'type'     => $item['type'],
				'settings' => Sanitizer::fields( self::settings_fields(), $item['settings'] ?? array() ),
				'fields'   => Sanitizer::fields( $def['fields'], $item['fields'] ?? array() ),
			);
		}
		return $clean;
	}

	public static function get_sections( int $post_id ): array {
		$json = get_post_meta( $post_id, self::META_KEY, true );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : array();
	}
}
