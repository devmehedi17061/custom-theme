<?php get_header(); ?>

<?php if ( ! is_front_page() ) { bfd_theme_breadcrumbs(); } ?>

<?php while ( have_posts() ) : the_post(); ?>
	<?php if ( function_exists( 'bfd_has_sections' ) && bfd_has_sections() ) : ?>
		<?php bfd_render_sections(); ?>
	<?php else : ?>
		<article <?php post_class( 'container section' ); ?>>
			<h1 class="page-title"><?php the_title(); ?></h1>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
	<?php endif; ?>
<?php endwhile; ?>

<?php get_footer(); ?>
