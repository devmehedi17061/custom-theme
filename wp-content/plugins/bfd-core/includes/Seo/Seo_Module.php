<?php
namespace BFD\Core\Seo;

use BFD\Core\Contracts\Module;
use BFD\Core\Sections\Section_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Per-post SEO fields and head output: title, description, canonical, robots,
 * Open Graph, Twitter Card, JSON-LD schema; sitemap tweaks; WebP output.
 */
final class Seo_Module implements Module {

	private const NONCE_ACTION = 'bfd_seo_save';
	private const NONCE_FIELD  = 'bfd_seo_nonce';

	public function slug(): string {
		return 'seo';
	}

	public function register(): void {
		( new Redirects() )->register();

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 20 );
		add_filter( 'get_canonical_url', array( $this, 'canonical_url' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'wp_head', array( $this, 'output_head' ), 2 );

		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_exclude_noindex' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'sitemap_remove_users' ), 10, 2 );

		// Generated image sizes as WebP when the server supports it.
		add_filter( 'image_editor_output_format', array( $this, 'webp_output' ) );
	}

	/* ---------- Data ---------- */

	public static function seo_post_types(): array {
		return array_values( get_post_types( array( 'public' => true ) ) ) ;
	}

	private static function meta( int $post_id, string $key ): string {
		$v = get_post_meta( $post_id, '_bfd_seo_' . $key, true );
		return is_string( $v ) ? $v : '';
	}

	public function document_title( $title ) {
		if ( is_singular() ) {
			$custom = self::meta( (int) get_queried_object_id(), 'title' );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		return $title;
	}

	public function canonical_url( $url, $post ) {
		$custom = self::meta( (int) $post->ID, 'canonical' );
		return '' !== $custom ? $custom : $url;
	}

	public function robots( array $robots ): array {
		if ( is_404() ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}
		if ( is_singular() && '1' === self::meta( (int) get_queried_object_id(), 'noindex' ) ) {
			unset( $robots['index'], $robots['max-image-preview'] );
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function description( ?\WP_Post $post = null ): string {
		if ( $post ) {
			$custom = self::meta( $post->ID, 'description' );
			if ( '' !== $custom ) {
				return $custom;
			}
			if ( '' !== trim( $post->post_excerpt ) ) {
				return self::trim_text( $post->post_excerpt );
			}
			$sections_text = self::sections_text( Section_Registry::get_sections( $post->ID ) );
			if ( '' !== $sections_text ) {
				return self::trim_text( $sections_text );
			}
			if ( '' !== trim( $post->post_content ) ) {
				return self::trim_text( $post->post_content );
			}
		}
		if ( is_archive() && '' !== trim( wp_strip_all_tags( get_the_archive_description() ) ) ) {
			return self::trim_text( get_the_archive_description() );
		}
		return self::trim_text( get_bloginfo( 'description' ) );
	}

	private static function trim_text( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
		return wp_html_excerpt( $text, 160, '…' );
	}

	/** Visible plain text from section fields (headings first, in order). */
	private static function sections_text( array $sections ): string {
		$parts = array();
		$walk  = static function ( $value ) use ( &$walk, &$parts ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					$walk( $v );
				}
			} elseif ( is_string( $value ) && strlen( $value ) > 3 && ! preg_match( '#^https?://#', $value ) ) {
				$parts[] = $value;
			}
		};
		foreach ( $sections as $s ) {
			if ( 'custom_html' === ( $s['type'] ?? '' ) ) {
				continue;
			}
			$walk( $s['fields'] ?? array() );
			if ( count( $parts ) >= 4 ) {
				break;
			}
		}
		return implode( '. ', array_slice( $parts, 0, 4 ) );
	}

	/** @return int Attachment ID or 0. */
	private static function image_id( ?\WP_Post $post ): int {
		if ( $post ) {
			$id = absint( self::meta( $post->ID, 'og_image' ) );
			if ( $id ) {
				return $id;
			}
			$thumb = (int) get_post_thumbnail_id( $post );
			if ( $thumb ) {
				return $thumb;
			}
			foreach ( Section_Registry::get_sections( $post->ID ) as $s ) {
				if ( ! empty( $s['fields']['image'] ) ) {
					return (int) $s['fields']['image'];
				}
			}
		}
		return absint( bfd_option( 'default_og_image', 0 ) );
	}

	/* ---------- Head output ---------- */

	public function output_head(): void {
		if ( is_admin() || is_feed() || is_robots() ) {
			return;
		}

		$post = is_singular() ? get_queried_object() : null;
		$post = $post instanceof \WP_Post ? $post : null;

		$title = wp_get_document_title();
		$desc  = self::description( $post );
		$url   = $this->current_url( $post );
		$img   = self::image_id( $post );
		$img_u = $img ? wp_get_attachment_image_src( $img, 'large' ) : null;
		$type  = ( $post && 'post' === $post->post_type ) ? 'article' : 'website';

		if ( '' !== $desc ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $desc ) );
		}

		$og = array(
			'og:locale'      => get_locale(),
			'og:type'        => $type,
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => $url,
			'og:site_name'   => get_bloginfo( 'name' ),
		);
		if ( $img_u ) {
			$og['og:image']        = $img_u[0];
			$og['og:image:width']  = (string) $img_u[1];
			$og['og:image:height'] = (string) $img_u[2];
		}
		foreach ( $og as $prop => $val ) {
			if ( '' !== (string) $val ) {
				printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $prop ), esc_attr( $val ) );
			}
		}

		$handle = bfd_option( 'twitter_handle' );
		printf( "<meta name=\"twitter:card\" content=\"%s\">\n", $img_u ? 'summary_large_image' : 'summary' );
		printf( "<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr( $title ) );
		if ( '' !== $desc ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr( $desc ) );
		}
		if ( $img_u ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\">\n", esc_url( $img_u[0] ) );
		}
		if ( $handle ) {
			printf( "<meta name=\"twitter:site\" content=\"@%s\">\n", esc_attr( ltrim( $handle, '@' ) ) );
		}

		if ( ! $post ) {
			// Core prints canonical only for singular views.
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
		}

		$this->output_verification_and_analytics();

		$graph = Schema::graph( $post, $title, $desc, $url, $img_u ? $img_u[0] : '' );
		if ( $graph ) {
			printf(
				"<script type=\"application/ld+json\">%s</script>\n",
				wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
			);
		}
	}

	/** Search-console verification tags (front page only) and optional Google Analytics 4. */
	private function output_verification_and_analytics(): void {
		if ( is_front_page() ) {
			$gsc  = (string) bfd_option( 'gsc_verification' );
			$bing = (string) bfd_option( 'bing_verification' );
			if ( preg_match( '/^[A-Za-z0-9_\-]{10,100}$/', $gsc ) ) {
				printf( "<meta name=\"google-site-verification\" content=\"%s\">\n", esc_attr( $gsc ) );
			}
			if ( preg_match( '/^[A-Za-z0-9_\-]{10,100}$/', $bing ) ) {
				printf( "<meta name=\"msvalidate.01\" content=\"%s\">\n", esc_attr( $bing ) );
			}
		}

		$ga = (string) bfd_option( 'ga_id' );
		// Skip for editors so their own visits don't skew the numbers.
		$track = ! ( is_user_logged_in() && current_user_can( 'edit_posts' ) );
		if ( $track && (bool) apply_filters( 'bfd_analytics_enabled', true ) && preg_match( '/^G-[A-Z0-9]{4,12}$/', $ga ) ) {
			printf( "<script async src=\"https://www.googletagmanager.com/gtag/js?id=%s\"></script>\n", esc_attr( $ga ) );
			printf(
				"<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','%s');</script>\n",
				esc_js( $ga )
			);
		}
	}

	private function current_url( ?\WP_Post $post ): string {
		if ( $post ) {
			return (string) ( wp_get_canonical_url( $post ) ?: get_permalink( $post ) );
		}
		global $wp;
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		$url   = home_url( user_trailingslashit( $wp->request ?? '' ) );
		if ( is_search() || is_404() ) {
			return home_url( '/' );
		}
		return $paged > 1 ? get_pagenum_link( $paged ) : $url;
	}

	/* ---------- Sitemap / performance ---------- */

	public function sitemap_exclude_noindex( array $args ): array {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_bfd_seo_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_bfd_seo_noindex', 'value' => '1', 'compare' => '!=' ),
		);
		return $args;
	}

	public function sitemap_remove_users( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	public function webp_output( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}

	/* ---------- Admin meta box ---------- */

	public function add_meta_box(): void {
		add_meta_box( 'bfd-seo', __( 'SEO', 'bfd-core' ), array( $this, 'render_meta_box' ), self::seo_post_types(), 'normal', 'default' );
	}

	public function admin_assets( string $hook ): void {
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'bfd-options', BFD_CORE_URL . 'assets/js/options.js', array(), BFD_CORE_VERSION, true );
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$title = self::meta( $post->ID, 'title' );
		$desc  = self::meta( $post->ID, 'description' );
		$img   = absint( self::meta( $post->ID, 'og_image' ) );
		$src   = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
		?>
		<p>
			<label for="bfd_seo_title"><strong><?php esc_html_e( 'SEO title', 'bfd-core' ); ?></strong></label>
			<input type="text" class="widefat" id="bfd_seo_title" name="bfd_seo_title" value="<?php echo esc_attr( $title ); ?>" maxlength="120">
			<span class="description"><?php esc_html_e( 'Leave empty to use the page title. Aim for under 60 characters.', 'bfd-core' ); ?> <span data-count-for="bfd_seo_title"><?php echo (int) strlen( $title ); ?></span></span>
		</p>
		<p>
			<label for="bfd_seo_description"><strong><?php esc_html_e( 'Meta description', 'bfd-core' ); ?></strong></label>
			<textarea class="widefat" id="bfd_seo_description" name="bfd_seo_description" rows="3" maxlength="320"><?php echo esc_textarea( $desc ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Leave empty to generate from the excerpt or first section. Aim for 120-160 characters.', 'bfd-core' ); ?></span>
		</p>
		<p>
			<label for="bfd_seo_canonical"><strong><?php esc_html_e( 'Canonical URL', 'bfd-core' ); ?></strong></label>
			<input type="url" class="widefat" id="bfd_seo_canonical" name="bfd_seo_canonical" value="<?php echo esc_attr( self::meta( $post->ID, 'canonical' ) ); ?>" placeholder="<?php esc_attr_e( 'Default: the permalink', 'bfd-core' ); ?>">
		</p>
		<div class="bfd-media-field">
			<strong><?php esc_html_e( 'Social share image', 'bfd-core' ); ?></strong><br>
			<input type="hidden" name="bfd_seo_og_image" value="<?php echo esc_attr( (string) ( $img ?: '' ) ); ?>">
			<img class="bfd-media-preview" src="<?php echo esc_url( (string) $src ); ?>" alt="" style="max-width:120px;display:<?php echo $src ? 'block' : 'none'; ?>;margin:8px 0">
			<button type="button" class="button bfd-media-pick"><?php esc_html_e( 'Select image', 'bfd-core' ); ?></button>
			<button type="button" class="button bfd-media-clear"><?php esc_html_e( 'Remove', 'bfd-core' ); ?></button>
		</div>
		<p>
			<label><input type="checkbox" name="bfd_seo_noindex" value="1" <?php checked( '1' === self::meta( $post->ID, 'noindex' ) ); ?>>
				<?php esc_html_e( 'Hide from search engines (noindex) and the sitemap', 'bfd-core' ); ?></label>
		</p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::seo_post_types(), true ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in     = static fn( string $k ): string => isset( $_POST[ $k ] ) ? (string) wp_unslash( $_POST[ $k ] ) : '';
		$image  = absint( $in( 'bfd_seo_og_image' ) );
		$values = array(
			'title'       => sanitize_text_field( $in( 'bfd_seo_title' ) ),
			'description' => sanitize_textarea_field( $in( 'bfd_seo_description' ) ),
			'canonical'   => esc_url_raw( $in( 'bfd_seo_canonical' ) ),
			'og_image'    => ( $image && 'attachment' === get_post_type( $image ) ) ? (string) $image : '',
			'noindex'     => '1' === $in( 'bfd_seo_noindex' ) ? '1' : '',
		);
		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, '_bfd_seo_' . $key );
			} else {
				update_post_meta( $post_id, '_bfd_seo_' . $key, wp_slash( $value ) );
			}
		}
	}
}
