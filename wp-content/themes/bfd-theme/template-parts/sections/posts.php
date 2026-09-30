<?php
$f            = $args['fields'] ?? array();
$types        = get_post_types( array( 'public' => true ) );
$listing_type = isset( $f['post_type'] ) && isset( $types[ $f['post_type'] ] ) ? $f['post_type'] : 'post';
$count        = max( 1, min( 24, (int) ( $f['count'] ?? 3 ) ?: 3 ) );
$cols         = in_array( $f['columns'] ?? '3', array( '2', '3', '4' ), true ) ? $f['columns'] : '3';

$q = new WP_Query(
	array(
		'post_type'           => $listing_type,
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<?php if ( ! empty( $f['intro'] ) ) : ?>
	<p class="section__intro"><?php echo esc_html( $f['intro'] ); ?></p>
<?php endif; ?>

<?php if ( $q->have_posts() ) : ?>
	<div class="bfd-grid bfd-grid--<?php echo esc_attr( $cols ); ?>">
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			get_template_part( 'template-parts/content/card', null, array( 'excerpt' => ! empty( $f['show_excerpt'] ) ) );
		endwhile;
		wp_reset_postdata();
		?>
	</div>
<?php elseif ( current_user_can( 'edit_pages' ) ) : ?>
	<p class="section__intro"><em><?php esc_html_e( 'No published items of this type yet.', 'bfd-theme' ); ?></em></p>
<?php endif; ?>

<?php if ( ! empty( $f['button_text'] ) && ! empty( $f['button_url'] ) ) : ?>
	<p class="section__more"><a class="btn" href="<?php echo esc_url( $f['button_url'] ); ?>"><?php echo esc_html( $f['button_text'] ); ?></a></p>
<?php endif; ?>
