<?php
namespace BFD\Core\Forms;

use BFD\Core\Contracts\Module;
use BFD\Core\Sections\Custom_Sections;

defined( 'ABSPATH' ) || exit;

/**
 * Form builder: Forms post type (fields + settings), shortcode, submit handlers, submissions.
 */
final class Forms_Module implements Module {

	private const NONCE_ACTION = 'bfd_form_save';
	private const NONCE_FIELD  = 'bfd_form_admin_nonce';

	public function slug(): string {
		return 'forms';
	}

	public function register(): void {
		( new Submissions() )->register();

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . Form_Definition::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Form_Definition::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		add_shortcode( 'bfd_form', array( $this, 'shortcode' ) );

		// No-JS path (redirect) and AJAX path share the same handler.
		add_action( 'admin_post_nopriv_bfd_form_submit', array( $this, 'handle_post' ) );
		add_action( 'admin_post_bfd_form_submit', array( $this, 'handle_post' ) );
		add_action( 'wp_ajax_nopriv_bfd_form_submit', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_bfd_form_submit', array( $this, 'handle_ajax' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			Form_Definition::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Forms', 'bfd-core' ),
					'singular_name' => __( 'Form', 'bfd-core' ),
					'add_new_item'  => __( 'Add New Form', 'bfd-core' ),
					'edit_item'     => __( 'Edit Form', 'bfd-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-feedback',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => Custom_Sections::admin_caps(),
			)
		);
	}

	/* ---------- Submission endpoints ---------- */

	private function run(): array {
		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in Submission_Handler.
		$form_id = isset( $post['form_id'] ) ? absint( $post['form_id'] ) : 0;
		return array( $form_id, Submission_Handler::handle( $form_id, $post, $_FILES ) );
	}

	public function handle_ajax(): void {
		list( , $r ) = $this->run();
		if ( $r['ok'] ) {
			wp_send_json_success( array( 'message' => $r['message'] ) );
		}
		wp_send_json_error( array( 'message' => $r['message'], 'errors' => $r['errors'], 'code' => $r['code'] ), 'invalid' === $r['code'] ? 422 : 400 );
	}

	public function handle_post(): void {
		list( $form_id, $r ) = $this->run();
		$back = remove_query_arg( array( 'bfd_form', 'bfd_form_status', 'bfd_code' ), wp_get_referer() ?: home_url( '/' ) );
		$back = add_query_arg(
			array(
				'bfd_form'        => $form_id,
				'bfd_form_status' => $r['ok'] ? 'ok' : 'error',
				'bfd_code'        => $r['ok'] ? '' : $r['code'],
			),
			$back
		);
		wp_safe_redirect( $back . '#bfd-form-' . $form_id );
		exit;
	}

	public function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bfd_form' );
		ob_start();
		Form_Renderer::render( absint( $atts['id'] ) );
		return (string) ob_get_clean();
	}

	/* ---------- Admin ---------- */

	public function add_meta_boxes(): void {
		add_meta_box( 'bfd-form-fields', __( 'Fields', 'bfd-core' ), array( $this, 'box_fields' ), Form_Definition::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'bfd-form-settings', __( 'Settings', 'bfd-core' ), array( $this, 'box_settings' ), Form_Definition::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'bfd-form-usage', __( 'Use this form', 'bfd-core' ), array( $this, 'box_usage' ), Form_Definition::POST_TYPE, 'side', 'default' );
	}

	public function admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Form_Definition::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'bfd-section-builder', BFD_CORE_URL . 'assets/css/section-builder.css', array(), BFD_CORE_VERSION );
		wp_enqueue_script( 'bfd-field-definer', BFD_CORE_URL . 'assets/js/field-definer.js', array(), BFD_CORE_VERSION, true );
	}

	public function box_fields( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$fields = json_decode( (string) get_post_meta( $post->ID, Form_Definition::META_FIELDS, true ), true );
		$types  = array(
			array( 'text', __( 'Text', 'bfd-core' ) ),
			array( 'email', __( 'Email', 'bfd-core' ) ),
			array( 'tel', __( 'Phone', 'bfd-core' ) ),
			array( 'textarea', __( 'Textarea', 'bfd-core' ) ),
			array( 'select', __( 'Select', 'bfd-core' ) ),
			array( 'checkbox', __( 'Checkbox', 'bfd-core' ) ),
			array( 'number', __( 'Number', 'bfd-core' ) ),
			array( 'file', __( 'File upload', 'bfd-core' ) ),
		);
		?>
		<input type="hidden" id="bfd-cs-fields-input" name="bfd_form_fields" value="<?php echo esc_attr( wp_json_encode( is_array( $fields ) ? $fields : array() ) ); ?>">
		<div id="bfd-field-definer" data-types="<?php echo esc_attr( wp_json_encode( $types ) ); ?>" data-repeater="0" data-ph="0" data-required="1"></div>
		<p class="description"><?php esc_html_e( 'File uploads accept PDF, JPG, PNG, WebP, DOC and DOCX up to 5 MB.', 'bfd-core' ); ?></p>
		<?php
	}

	public function box_settings( \WP_Post $post ): void {
		$conf = array_merge( Form_Definition::default_settings(), (array) json_decode( (string) get_post_meta( $post->ID, Form_Definition::META_CONF, true ), true ) );
		?>
		<table class="form-table" role="presentation">
			<tr><th><label for="bfd_f_recipients"><?php esc_html_e( 'Send to (comma separated)', 'bfd-core' ); ?></label></th>
				<td><input type="text" class="large-text" id="bfd_f_recipients" name="bfd_form_settings[recipients]" value="<?php echo esc_attr( $conf['recipients'] ); ?>"></td></tr>
			<tr><th><label for="bfd_f_subject"><?php esc_html_e( 'Email subject', 'bfd-core' ); ?></label></th>
				<td><input type="text" class="large-text" id="bfd_f_subject" name="bfd_form_settings[subject]" value="<?php echo esc_attr( $conf['subject'] ); ?>"></td></tr>
			<tr><th><label for="bfd_f_success"><?php esc_html_e( 'Success message', 'bfd-core' ); ?></label></th>
				<td><input type="text" class="large-text" id="bfd_f_success" name="bfd_form_settings[success_message]" value="<?php echo esc_attr( $conf['success_message'] ); ?>"></td></tr>
			<tr><th><label for="bfd_f_submit"><?php esc_html_e( 'Button label', 'bfd-core' ); ?></label></th>
				<td><input type="text" id="bfd_f_submit" name="bfd_form_settings[submit_label]" value="<?php echo esc_attr( $conf['submit_label'] ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Save submissions', 'bfd-core' ); ?></th>
				<td><label><input type="checkbox" name="bfd_form_settings[store]" value="1" <?php checked( ! empty( $conf['store'] ) ); ?>> <?php esc_html_e( 'Keep a copy in Forms > Submissions', 'bfd-core' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Auto-reply', 'bfd-core' ); ?></th>
				<td><label><input type="checkbox" name="bfd_form_settings[autoreply]" value="1" <?php checked( ! empty( $conf['autoreply'] ) ); ?>> <?php esc_html_e( 'Send a confirmation to the visitor (needs an Email field)', 'bfd-core' ); ?></label>
				<textarea class="large-text" rows="3" name="bfd_form_settings[autoreply_message]"><?php echo esc_textarea( $conf['autoreply_message'] ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public function box_usage( \WP_Post $post ): void {
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Publish the form to use it.', 'bfd-core' ) . '</p>';
			return;
		}
		printf( '<p>%s</p><p><code>[bfd_form id="%d"]</code></p>', esc_html__( 'Shortcode, or pick this form in a page\'s "Form" section:', 'bfd-core' ), (int) $post->ID );
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$json = isset( $_POST['bfd_form_fields'] ) ? (string) wp_unslash( $_POST['bfd_form_fields'] ) : '';
		$data = strlen( $json ) <= 200000 ? json_decode( $json, true ) : null;
		if ( is_array( $data ) ) {
			update_post_meta( $post_id, Form_Definition::META_FIELDS, wp_slash( wp_json_encode( Form_Definition::sanitize_fields( $data ) ) ) );
		}

		$conf = isset( $_POST['bfd_form_settings'] ) && is_array( $_POST['bfd_form_settings'] ) ? wp_unslash( $_POST['bfd_form_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Form_Definition::sanitize_settings.
		update_post_meta( $post_id, Form_Definition::META_CONF, wp_slash( wp_json_encode( Form_Definition::sanitize_settings( $conf ) ) ) );
	}
}
