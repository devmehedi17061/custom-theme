<?php get_header(); ?>

<div class="container section">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<h1 class="page-title"><?php single_post_title(); ?></h1>
	<?php else : ?>
		<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?></h1>
	<?php endif; ?>

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
