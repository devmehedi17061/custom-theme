<?php
namespace BFD\Core\Sections;

defined( 'ABSPATH' ) || exit;

/**
 * "Custom Sections" post type: define fields + HTML template + CSS in the admin.
 * Published ones appear in the page section builder as `cs_{post_id}`.
 */
final class Custom_Sections {

	public const POST_TYPE = 'bfd_section';
	public const PREFIX    = 'cs_';

	private const META_FIELDS    = '_bfd_cs_fields';
	private const META_TEMPLATE  = '_bfd_cs_template';
	private const META_CSS       = '_bfd_cs_css';
	private const META_CONTAINER = '_bfd_cs_container';

	private const NONCE_ACTION = 'bfd_cs_save';
	private const NONCE_FIELD  = 'bfd_cs_nonce';

	private const TYPES = array(
		'text',
		'textarea',
		'richtext',
		'url',
		'image',
		'select',
		'checkbox',
		'color',
		'number',
		'repeater',
	);

	private const DEFAULT_TEMPLATE = "<div class=\"my-section\">\n\t<h2>{{heading}}</h2>\n\t<p>{{text}}</p>\n</div>";

	/** @var array<int,bool> */
	private static array $css_printed = array();

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** Capability map that limits a post type to administrators. */
	public static function admin_caps(): array {
		$caps = array();
		foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ) as $cap ) {
			$caps[ $cap ] = 'manage_options';
		}
		return $caps;
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Custom Sections', 'bfd-core' ),
					'singular_name' => __( 'Custom Section', 'bfd-core' ),
					'add_new_item'  => __( 'Add New Custom Section', 'bfd-core' ),
					'edit_item'     => __( 'Edit Custom Section', 'bfd-core' ),
					'menu_name'     => __( 'Custom Sections', 'bfd-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-layout',
				'menu_position'   => 25,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				// Sections can contain raw HTML/CSS: administrators only.
				'capabilities'    => self::admin_caps(),
			)
		);
	}

	/* ---------- Registry integration ---------- */

	/**
	 * Definitions for all published custom sections, keyed by section type.
	 *
	 * @return array<string, array>
	 */
	public static function definitions(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[ self::PREFIX . $post->ID ] = array(
				'label'     => $post->post_title ?: sprintf( '#%d', $post->ID ),
				'fields'    => self::to_schema( self::stored_fields( $post->ID ) ),
				'custom_id' => $post->ID,
				'container' => '0' !== (string) get_post_meta( $post->ID, self::META_CONTAINER, true ),
			);
		}
		return $out;
	}

	/** Render a custom section's HTML (template + scoped CSS). */
	public static function render( array $def, array $fields ): void {
		$id  = (int) $def['custom_id'];
		$tpl = (string) get_post_meta( $id, self::META_TEMPLATE, true );
		$css = (string) get_post_meta( $id, self::META_CSS, true );

		if ( '' !== trim( $css ) && empty( self::$css_printed[ $id ] ) ) {
			self::$css_printed[ $id ] = true;
			// Native CSS nesting scopes the author's rules to this section.
			printf( '<style>.bfd-cs-%d{%s}</style>', $id, $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized on save.
		}

		printf( '<div class="bfd-cs bfd-cs-%d">', absint( $id ) );
		echo Template_Engine::render( $tpl, $fields, $def['fields'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template is admin-authored; values escaped by the engine.
		echo '</div>';
	}

	/* ---------- Field definitions ---------- */

	private static function stored_fields( int $post_id ): array {
		$json = get_post_meta( $post_id, self::META_FIELDS, true );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	/** Stored definitions (options as text) to the schema the Sanitizer/Engine use. */
	private static function to_schema( array $stored ): array {
		$schema = array();
		foreach ( $stored as $f ) {
			$def = array(
				'key'   => $f['key'],
				'type'  => $f['type'],
				'label' => $f['label'],
			);
			if ( 'select' === $f['type'] ) {
				$def['options'] = self::parse_options( (string) ( $f['options'] ?? '' ) );
			}
			if ( 'repeater' === $f['type'] ) {
				$def['sub_fields'] = self::to_schema( $f['sub_fields'] ?? array() );
			}
			$schema[] = $def;
		}
		return $schema;
	}

	/** One option per line: "value|Label" or just "Label". */
	private static function parse_options( string $text ): array {
		$opts = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$value = $parts[0];
			$label = $parts[1] ?? $parts[0];
			if ( '' !== $value ) {
				$opts[ $value ] = $label;
			}
		}
		return $opts;
	}

	private static function sanitize_defs( $raw, bool $allow_repeater = true, int $max = 30 ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out  = array();
		$seen = array();

		foreach ( array_slice( array_values( $raw ), 0, $max ) as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type = isset( $f['type'] ) && is_string( $f['type'] ) ? $f['type'] : 'text';
			if ( ! in_array( $type, self::TYPES, true ) || ( 'repeater' === $type && ! $allow_repeater ) ) {
				$type = 'text';
			}

			$raw_label = $f['label'] ?? '';
			$raw_key   = $f['key'] ?? '';
			$label     = sanitize_text_field( is_scalar( $raw_label ) ? (string) $raw_label : '' );
			$key       = self::clean_key( is_scalar( $raw_key ) ? (string) $raw_key : '' );
			if ( '' === $key ) {
				$key = self::clean_key( $label );
			}
			if ( '' === $key ) {
				$key = 'field';
			}
			$base = $key;
			for ( $n = 2; isset( $seen[ $key ] ); $n++ ) {
				$key = $base . '_' . $n;
			}
			$seen[ $key ] = true;

			$def = array(
				'key'   => $key,
				'label' => '' !== $label ? $label : $key,
				'type'  => $type,
			);
			if ( 'select' === $type ) {
				$raw_opts       = $f['options'] ?? '';
				$def['options'] = sanitize_textarea_field( is_scalar( $raw_opts ) ? (string) $raw_opts : '' );
			}
			if ( 'repeater' === $type ) {
				$def['sub_fields'] = self::sanitize_defs( $f['sub_fields'] ?? array(), false, 15 );
			}
			$out[] = $def;
		}
		return $out;
	}

	private static function clean_key( string $s ): string {
		$s = strtolower( preg_replace( '/[^a-z0-9]+/i', '_', $s ) );
		$s = trim( $s, '_' );
		return preg_match( '/^[a-z]/', $s ) ? substr( $s, 0, 40 ) : ( '' === $s ? '' : 'f_' . substr( $s, 0, 37 ) );
	}

	/* ---------- Admin UI ---------- */

	public function add_meta_boxes(): void {
		add_meta_box( 'bfd-cs-fields', __( 'Fields', 'bfd-core' ), array( $this, 'box_fields' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'bfd-cs-template', __( 'HTML Template', 'bfd-core' ), array( $this, 'box_template' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'bfd-cs-css', __( 'CSS', 'bfd-core' ), array( $this, 'box_css' ), self::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'bfd-cs-settings', __( 'Settings', 'bfd-core' ), array( $this, 'box_settings' ), self::POST_TYPE, 'side', 'default' );
	}

	public function box_fields( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p class="description"><?php esc_html_e( 'Define the fields editors fill in when they add this section to a page.', 'bfd-core' ); ?></p>
		<input type="hidden" id="bfd-cs-fields-input" name="bfd_cs_fields" value="<?php echo esc_attr( wp_json_encode( self::stored_fields( $post->ID ) ) ); ?>">
		<div id="bfd-field-definer"></div>
		<?php
	}

	public function box_template( \WP_Post $post ): void {
		$tpl = get_post_meta( $post->ID, self::META_TEMPLATE, true );
		if ( ! is_string( $tpl ) || '' === $tpl ) {
			$tpl = self::DEFAULT_TEMPLATE;
		}
		?>
		<textarea name="bfd_cs_template" class="widefat code" rows="14" spellcheck="false"><?php echo esc_textarea( $tpl ); ?></textarea>
		<details class="bfd-help">
			<summary><?php esc_html_e( 'Placeholder syntax', 'bfd-core' ); ?></summary>
			<ul>
				<li><code>{{key}}</code> <?php esc_html_e( 'value, escaped (image gives its URL, url gives a safe link)', 'bfd-core' ); ?></li>
				<li><code>{{key:img}}</code> / <code>{{key:img:large}}</code> <?php esc_html_e( 'image as an &lt;img&gt; tag', 'bfd-core' ); ?></li>
				<li><code>{{#key}} … {{/key}}</code> <?php esc_html_e( 'loop over a repeater, or show only if the field is not empty', 'bfd-core' ); ?></li>
				<li><code>{{^key}} … {{/key}}</code> <?php esc_html_e( 'show only if the field is empty', 'bfd-core' ); ?></li>
				<li><code>{{_index}}</code> <?php esc_html_e( 'row number inside a loop', 'bfd-core' ); ?></li>
			</ul>
			<?php
			$fields = self::stored_fields( $post->ID );
			if ( $fields ) :
				?>
				<p><strong><?php esc_html_e( 'Your fields:', 'bfd-core' ); ?></strong></p>
				<ul>
					<?php foreach ( $fields as $f ) : ?>
						<li>
							<?php if ( 'repeater' === $f['type'] ) : ?>
								<code>{{#<?php echo esc_html( $f['key'] ); ?>}} … {{/<?php echo esc_html( $f['key'] ); ?>}}</code>
								<?php echo esc_html( '(' . implode( ', ', array_map( static fn( $s ) => '{{' . $s['key'] . '}}', $f['sub_fields'] ?? array() ) ) . ')' ); ?>
							<?php else : ?>
								<code>{{<?php echo esc_html( $f['key'] ); ?>}}</code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</details>
		<?php
	}

	public function box_css( \WP_Post $post ): void {
		?>
		<textarea name="bfd_cs_css" class="widefat code" rows="10" spellcheck="false"><?php echo esc_textarea( (string) get_post_meta( $post->ID, self::META_CSS, true ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Automatically scoped to this section using CSS nesting. Write normal selectors like .my-section h2 { … }; use & for the section wrapper itself.', 'bfd-core' ); ?></p>
		<?php
	}

	public function box_settings( \WP_Post $post ): void {
		$on = '0' !== (string) get_post_meta( $post->ID, self::META_CONTAINER, true );
		?>
		<label>
			<input type="checkbox" name="bfd_cs_container" value="1" <?php checked( $on ); ?>>
			<?php esc_html_e( 'Wrap in the site container (uncheck for full-width HTML)', 'bfd-core' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Only published custom sections appear in the page builder.', 'bfd-core' ); ?></p>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'bfd-section-builder', BFD_CORE_URL . 'assets/css/section-builder.css', array(), BFD_CORE_VERSION );
		wp_enqueue_script( 'bfd-field-definer', BFD_CORE_URL . 'assets/js/field-definer.js', array(), BFD_CORE_VERSION, true );
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Fields: malformed JSON keeps the stored definition.
		$json = isset( $_POST['bfd_cs_fields'] ) ? (string) wp_unslash( $_POST['bfd_cs_fields'] ) : '';
		$data = strlen( $json ) <= 200000 ? json_decode( $json, true ) : null;
		if ( is_array( $data ) ) {
			update_post_meta( $post_id, self::META_FIELDS, wp_slash( wp_json_encode( self::sanitize_defs( $data ) ) ) );
		}

		// Template: raw HTML only for users with unfiltered_html.
		$tpl = isset( $_POST['bfd_cs_template'] ) ? (string) wp_unslash( $_POST['bfd_cs_template'] ) : '';
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$tpl = wp_kses_post( $tpl );
		}
		update_post_meta( $post_id, self::META_TEMPLATE, wp_slash( $tpl ) );

		// CSS: must not be able to close the <style> tag.
		$css = isset( $_POST['bfd_cs_css'] ) ? (string) wp_unslash( $_POST['bfd_cs_css'] ) : '';
		$css = wp_strip_all_tags( $css ); // No HTML tags, so it cannot close the <style> element.
		update_post_meta( $post_id, self::META_CSS, wp_slash( $css ) );

		update_post_meta( $post_id, self::META_CONTAINER, isset( $_POST['bfd_cs_container'] ) ? '1' : '0' );
	}
}
