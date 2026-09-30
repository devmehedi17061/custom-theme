<?php
$f     = $args['fields'] ?? array();
$count = max( 1, min( 24, (int) ( $f['count'] ?? 3 ) ?: 3 ) );
$cols  = in_array( $f['columns'] ?? '3', array( '2', '3', '4' ), true ) ? $f['columns'] : '3';

$q = new WP_Query(
	array(
		'post_type'      => 'testimonial',
		'posts_per_page' => $count,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	)
);
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>

<?php if ( $q->have_posts() ) : ?>
	<div class="bfd-grid bfd-grid--<?php echo esc_attr( $cols ); ?>">
		<?php while ( $q->have_posts() ) : $q->the_post(); ?>
			<figure class="testimonial">
				<blockquote><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', get_the_ID() ) ) ); ?></blockquote>
				<figcaption>
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'thumbnail', array( 'class' => 'testimonial__photo', 'loading' => 'lazy' ) ); ?>
					<?php endif; ?>
					<span>
						<strong><?php the_title(); ?></strong>
						<?php $role = get_post_meta( get_the_ID(), '_bfd_role', true ); ?>
						<?php if ( $role ) : ?><em><?php echo esc_html( $role ); ?></em><?php endif; ?>
					</span>
				</figcaption>
			</figure>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>
<?php elseif ( current_user_can( 'edit_pages' ) ) : ?>
	<p class="section__intro"><em><?php esc_html_e( 'Add entries under Testimonials to show them here.', 'bfd-theme' ); ?></em></p>
<?php endif; ?>
