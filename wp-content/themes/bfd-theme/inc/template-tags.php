<?php
defined( 'ABSPATH' ) || exit;

/**
 * Render the site logo, or the site name as fallback.
 */
function bfd_site_branding() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf(
		'<a class="site-title" href="%s" rel="home">%s</a>',
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) )
	);
}

/**
 * Post meta line: date and author.
 */
function bfd_posted_on() {
	printf(
		'<time class="entry-date" datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
}

/**
 * Pagination for archive loops.
 */
function bfd_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => __( '&larr; Previous', 'bfd-theme' ),
			'next_text' => __( 'Next &rarr;', 'bfd-theme' ),
		)
	);
}

/**
 * Breadcrumbs (provided by bfd-core).
 */
function bfd_theme_breadcrumbs() {
	if ( function_exists( 'bfd_breadcrumbs' ) ) {
		echo '<div class="container breadcrumbs-wrap">';
		bfd_breadcrumbs();
		echo '</div>';
	}
}

/**
 * Related posts: same terms (first public taxonomy of the post type), newest first.
 */
function bfd_related_posts( $post_id = 0, $limit = 3 ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Query( array( 'post__in' => array( 0 ) ) );
	}

	$tax_query = array();
	foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
		if ( ! $tax->public || 'post_format' === $tax->name ) {
			continue;
		}
		$terms = wp_get_object_terms( $post->ID, $tax->name, array( 'fields' => 'ids' ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$tax_query[] = array( 'taxonomy' => $tax->name, 'field' => 'term_id', 'terms' => $terms );
		}
		break;
	}
	if ( ! $tax_query ) {
		return new WP_Query( array( 'post__in' => array( 0 ) ) );
	}

	return new WP_Query(
		array(
			'post_type'              => $post->post_type,
			'post__not_in'           => array( $post->ID ),
			'posts_per_page'         => (int) $limit,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'tax_query'              => $tax_query,
		)
	);
}

/**
 * Author box for standard posts.
 */
function bfd_author_box() {
	$id = get_the_author_meta( 'ID' );
	if ( ! $id ) {
		return;
	}
	?>
	<aside class="author-box">
		<?php echo get_avatar( $id, 64, '', '', array( 'loading' => 'lazy' ) ); ?>
		<div>
			<strong><?php echo esc_html( get_the_author() ); ?></strong>
			<?php if ( get_the_author_meta( 'description' ) ) : ?>
				<p><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
			<?php endif; ?>
		</div>
	</aside>
	<?php
}
