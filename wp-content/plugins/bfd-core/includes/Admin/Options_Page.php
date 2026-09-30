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
					array( 'key' => 'redirects', 'type' => 'textarea', 'label' => __( 'Redirects (one per line: /old-path /new-path [301|302])', 'bfd-core' ) ),
				),
			),
			'design'        => array(
				'title'  => __( 'Design', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'primary_color', 'type' => 'color', 'label' => __( 'Primary color', 'bfd-core' ) ),
					array( 'key' => 'primary_dark', 'type' => 'color', 'label' => __( 'Primary color (hover, dark)', 'bfd-core' ) ),
					array( 'key' => 'text_color', 'type' => 'color', 'label' => __( 'Text color', 'bfd-core' ) ),
					array( 'key' => 'bg_color', 'type' => 'color', 'label' => __( 'Page background', 'bfd-core' ) ),
					array( 'key' => 'surface_color', 'type' => 'color', 'label' => __( 'Light section background', 'bfd-core' ) ),
					array( 'key' => 'heading_font', 'type' => 'select', 'label' => __( 'Heading font', 'bfd-core' ), 'default' => 'system', 'options' => self::font_options() ),
					array( 'key' => 'body_font', 'type' => 'select', 'label' => __( 'Body font', 'bfd-core' ), 'default' => 'system', 'options' => self::font_options() ),
					array( 'key' => 'container_width', 'type' => 'number', 'label' => __( 'Content width in px (800-1800)', 'bfd-core' ) ),
					array( 'key' => 'sticky_header', 'type' => 'checkbox', 'label' => __( 'Keep the header visible when scrolling', 'bfd-core' ) ),
					array( 'key' => 'custom_css', 'type' => 'textarea', 'label' => __( 'Additional CSS', 'bfd-core' ) ),
				),
			),
			'integrations'  => array(
				'title'  => __( 'Integrations (optional)', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'ga_id', 'type' => 'text', 'label' => __( 'Google Analytics 4 ID (G-XXXXXXX)', 'bfd-core' ) ),
					array( 'key' => 'gsc_verification', 'type' => 'text', 'label' => __( 'Google Search Console verification code', 'bfd-core' ) ),
					array( 'key' => 'bing_verification', 'type' => 'text', 'label' => __( 'Bing Webmaster verification code', 'bfd-core' ) ),
					array( 'key' => 'turnstile_site_key', 'type' => 'text', 'label' => __( 'Cloudflare Turnstile site key (spam protection for forms)', 'bfd-core' ) ),
					array( 'key' => 'turnstile_secret', 'type' => 'text', 'label' => __( 'Cloudflare Turnstile secret key', 'bfd-core' ) ),
				),
			),
			'advanced'      => array(
				'title'  => __( 'Advanced', 'bfd-core' ),
				'fields' => array(
					array( 'key' => 'delete_on_uninstall', 'type' => 'checkbox', 'label' => __( 'Delete ALL plugin data (forms, submissions, custom sections, page sections, SEO fields, settings) when this plugin is deleted', 'bfd-core' ) ),
				),
			),
		);
	}

	private static function font_options(): array {
		$out = array();
		foreach ( bfd_font_stacks() as $key => $stack ) {
			$out[ $key ] = $stack[0];
		}
		return $out;
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
		$out = Sanitizer::fields( self::all_fields(), is_array( $raw ) ? $raw : array() );

		$out['ga_id']             = preg_match( '/^G-[A-Z0-9]{4,12}$/i', $out['ga_id'] ) ? strtoupper( $out['ga_id'] ) : '';
		$out['gsc_verification']  = preg_match( '/^[A-Za-z0-9_\-]{10,100}$/', $out['gsc_verification'] ) ? $out['gsc_verification'] : '';
		$out['bing_verification'] = preg_match( '/^[A-Za-z0-9_\-]{10,100}$/', $out['bing_verification'] ) ? $out['bing_verification'] : '';
		$out['turnstile_site_key'] = preg_match( '/^[A-Za-z0-9_\-]{5,80}$/', $out['turnstile_site_key'] ) ? $out['turnstile_site_key'] : '';
		$out['turnstile_secret']   = preg_match( '/^[A-Za-z0-9_\-]{5,120}$/', $out['turnstile_secret'] ) ? $out['turnstile_secret'] : '';

		$width = (int) $out['container_width'];
		$out['container_width'] = ( $width >= 800 && $width <= 1800 ) ? $width : '';

		// Admin-authored CSS: no HTML tags, so it can never close its <style> element.
		$out['custom_css'] = wp_strip_all_tags( (string) ( is_array( $raw ) ? ( $raw['custom_css'] ?? '' ) : '' ) );
		return $out;
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'bfd-options', BFD_CORE_URL . 'assets/js/options.js', array( 'wp-color-picker' ), BFD_CORE_VERSION, true );
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
			$rows = in_array( $f['key'], array( 'custom_css', 'redirects' ), true ) ? 8 : 3;
			printf( '<textarea id="%s" name="%s" rows="%d" class="large-text%s">%s</textarea>', esc_attr( $id ), esc_attr( $name ), $rows, 8 === $rows ? ' code' : '', esc_textarea( (string) $value ) );
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
		if ( 'color' === $f['type'] ) {
			printf( '<input type="text" class="bfd-color" id="%s" name="%s" value="%s" data-default-color="">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			return;
		}
		if ( 'select' === $f['type'] ) {
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( $f['options'] as $opt => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $opt ), selected( (string) ( $value ?: ( $f['default'] ?? '' ) ), (string) $opt, false ), esc_html( $label ) );
			}
			echo '</select>';
			return;
		}
		if ( 'checkbox' === $f['type'] ) {
			printf( '<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>', esc_attr( $id ), esc_attr( $name ), checked( ! empty( $value ), true, false ), esc_html( $f['label'] ) );
			return;
		}
		if ( 'number' === $f['type'] ) {
			printf( '<input type="number" class="small-text" id="%s" name="%s" value="%s" min="800" max="1800" step="10">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			return;
		}
		$type = in_array( $f['type'], array( 'url', 'email', 'tel' ), true ) ? $f['type'] : 'text';
		if ( 'turnstile_secret' === $f['key'] ) {
			$type = 'password';
		}
		printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
	}
}
