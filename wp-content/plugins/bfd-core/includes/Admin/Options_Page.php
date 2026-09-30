<?php
namespace BFD\Core\Admin;

use BFD\Core\Fields\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Settings > BFD Options: header/footer, contact, social and SEO defaults.
 * Read values with bfd_option( 'key' ).
 */
final class Options_Page {

	public const OPTION = 'bfd_options';
	private const SLUG  = 'bfd-options';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** @return array<string, array{title:string, fields:array}> */
	public static function groups(): array {
		return array(
			'header_footer' => array(
				'title'  => __( 'Header & Footer', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'header_cta_text', 'type' => 'text', 'label' => __( 'Header button text', 'bfd-core' ) ),
					array( 'key' => 'header_cta_url', 'type' => 'url', 'label' => __( 'Header button URL', 'bfd-core' ) ),
					array( 'key' => 'footer_text', 'type' => 'textarea', 'label' => __( 'Footer text', 'bfd-core' ) ),
				),
			),
			'contact'       => array(
				'title'  => __( 'Contact', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'phone', 'type' => 'tel', 'label' => __( 'Phone', 'bfd-core' ) ),
					array( 'key' => 'email', 'type' => 'email', 'label' => __( 'Email', 'bfd-core' ) ),
					array( 'key' => 'address', 'type' => 'textarea', 'label' => __( 'Address', 'bfd-core' ) ),
				),
			),
			'social'        => array(
				'title'  => __( 'Social links', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'facebook', 'type' => 'url', 'label' => 'Facebook' ),
					array( 'key' => 'x', 'type' => 'url', 'label' => 'X / Twitter' ),
					array( 'key' => 'instagram', 'type' => 'url', 'label' => 'Instagram' ),
					array( 'key' => 'linkedin', 'type' => 'url', 'label' => 'LinkedIn' ),
					array( 'key' => 'youtube', 'type' => 'url', 'label' => 'YouTube' ),
				),
			),
			'seo'           => array(
				'title'  => __( 'SEO defaults', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'org_name', 'type' => 'text', 'label' => __( 'Organization name (schema)', 'bfd-core' ) ),
					array( 'key' => 'default_og_image', 'type' => 'image', 'label' => __( 'Default social share image', 'bfd-core' ) ),
					array( 'key' => 'twitter_handle', 'type' => 'text', 'label' => __( 'Twitter/X handle (e.g. @brand)', 'bfd-core' ) ),
				),
			),
		);
	}

	private static function all_fields(): array {
		$out = array();
		foreach ( self::groups() as $g ) {
			$out = array_merge( $out, $g['fields'] );
		}
		return $out;
	}

	public function add_page(): void {
		add_options_page( __( 'BFD Options', 'bfd-core' ), __( 'BFD Options', 'bfd-core' ), 'manage_options', self::SLUG, array( $this, 'render' ) );
	}

	public function register_setting(): void {
		register_setting(
			'bfd_options_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $raw ): array {
		return Sanitizer::fields( self::all_fields(), is_array( $raw ) ? $raw : array() );
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'bfd-options', BFD_CORE_URL . 'assets/js/options.js', array(), BFD_CORE_VERSION, true );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$values = get_option( self::OPTION, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BFD Options', 'bfd-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'bfd_options_group' ); ?>
				<?php foreach ( self::groups() as $group ) : ?>
					<h2><?php echo esc_html( $group['title'] ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( $group['fields'] as $f ) : ?>
							<tr>
								<th scope="row"><label for="bfd-opt-<?php echo esc_attr( $f['key'] ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
								<td><?php $this->field( $f, $values[ $f['key'] ] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function field( array $f, $value ): void {
		$name = self::OPTION . '[' . $f['key'] . ']';
		$id   = 'bfd-opt-' . $f['key'];

		if ( 'textarea' === $f['type'] ) {
			printf( '<textarea id="%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
			return;
		}
		if ( 'image' === $f['type'] ) {
			$src = $value ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
			?>
			<div class="bfd-media-field">
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				<img class="bfd-media-preview" src="<?php echo esc_url( (string) $src ); ?>" alt="" style="max-width:120px;display:<?php echo $src ? 'block' : 'none'; ?>;margin-bottom:8px">
				<button type="button" class="button bfd-media-pick"><?php esc_html_e( 'Select image', 'bfd-core' ); ?></button>
				<button type="button" class="button bfd-media-clear"><?php esc_html_e( 'Remove', 'bfd-core' ); ?></button>
			</div>
			<?php
			return;
		}
		$type = in_array( $f['type'], array( 'url', 'email', 'tel' ), true ) ? $f['type'] : 'text';
		printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
	}
}
