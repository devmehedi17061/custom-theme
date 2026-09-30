<?php
namespace BFD\Core\Sections;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Section builder meta box for pages (Phase 4).
 */
final class Sections_Module implements Module {

	private const NONCE_ACTION = 'bfd_sections_save';
	private const NONCE_FIELD  = 'bfd_sections_nonce';
	private const MAX_JSON     = 500000;
	private const PREVIEW_NONCE = 'bfd_sections_preview';

	public function slug(): string {
		return 'sections';
	}

	/** Post types that get the builder. */
	public static function post_types(): array {
		return (array) apply_filters( 'bfd_sections_post_types', array( 'page' ) );
	}

	public function register(): void {
		( new Custom_Sections() )->register();

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_bfd_preview_sections', array( $this, 'ajax_preview' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'bfd-sections',
			__( 'Page Sections', 'bfd-core' ),
			array( $this, 'render_meta_box' ),
			self::post_types(),
			'normal',
			'high'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$value = wp_json_encode( Section_Registry::get_sections( $post->ID ) );
		?>
		<input type="hidden" id="bfd-sections-input" name="bfd_sections" value="<?php echo esc_attr( $value ); ?>">
		<div id="bfd-section-builder"></div>
		<noscript><p><?php esc_html_e( 'JavaScript is required for the section builder.', 'bfd-core' ); ?></p></noscript>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_editor(); // TinyMCE for rich text fields.
		wp_enqueue_style( 'bfd-section-builder', BFD_CORE_URL . 'assets/css/section-builder.css', array(), BFD_CORE_VERSION );
		wp_enqueue_script( 'bfd-section-builder', BFD_CORE_URL . 'assets/js/section-builder.js', array(), BFD_CORE_VERSION, true );
		wp_add_inline_script(
			'bfd-section-builder',
			'window.bfdSectionDefs = ' . wp_json_encode(
				Section_Registry::for_js() + array(
					'ajax' => array(
						'url'    => admin_url( 'admin-ajax.php' ),
						'nonce'  => wp_create_nonce( self::PREVIEW_NONCE ),
						'postId' => (int) get_the_ID(),
					),
				)
			) . ';',
			'before'
		);
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
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

		$json = isset( $_POST['bfd_sections'] ) ? (string) wp_unslash( $_POST['bfd_sections'] ) : '';
		if ( '' === $json || strlen( $json ) > self::MAX_JSON ) {
			return;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return; // Malformed input: keep the stored sections.
		}

		$clean = Section_Registry::sanitize_sections( $data );
		update_post_meta( $post_id, Section_Registry::META_KEY, wp_slash( wp_json_encode( $clean ) ) );
	}

	/**
	 * Render unsaved sections as a full HTML page with the theme's own styles, for the builder's iframe preview.
	 */
	public function ajax_preview(): void {
		check_ajax_referer( self::PREVIEW_NONCE, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$allowed = $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_pages' );
		if ( ! $allowed ) {
			wp_die( esc_html__( 'Not allowed.', 'bfd-core' ), '', array( 'response' => 403 ) );
		}

		$json = isset( $_POST['sections'] ) ? (string) wp_unslash( $_POST['sections'] ) : '';
		$data = strlen( $json ) <= self::MAX_JSON ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) ) {
			wp_die( esc_html__( 'Invalid sections data.', 'bfd-core' ), '', array( 'response' => 400 ) );
		}
		$sections = Section_Registry::sanitize_sections( $data );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<base target="_blank">
	<?php wp_head(); ?>
	<style>html{margin-top:0!important}body{min-height:0}</style>
</head>
<body <?php body_class( 'bfd-preview' ); ?>>
		<?php
		if ( ! Renderer::render_list( $sections ) ) {
			echo '<p style="padding:2rem;font-family:sans-serif;color:#666">' . esc_html__( 'Nothing to preview yet.', 'bfd-core' ) . '</p>';
		}
		wp_footer();
		?>
</body>
</html>
		<?php
		wp_die();
	}
}
