<?php get_header(); ?>

<?php bfd_theme_breadcrumbs(); ?>

<div class="container section">
	<header class="archive-header">
		<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
		<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
	</header>

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
