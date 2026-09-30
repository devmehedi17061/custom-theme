<?php get_header(); ?>

<div class="container section">
	<h1 class="page-title">
		<?php
		/* translators: %s: search query */
		printf( esc_html__( 'Search results for: %s', 'bfd-theme' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
		?>
	</h1>

	<?php if ( have_posts() ) : ?>
		<div class="card-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content/card' );
			endwhile;
			?>
		</div>
		<?php bfd_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content/none' ); ?>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
