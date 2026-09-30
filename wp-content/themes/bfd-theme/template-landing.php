<?php
/**
 * Template Name: Landing page (no header or footer)
 * Template Post Type: page
 */
get_header( 'landing' );
?>

<?php
while ( have_posts() ) :
	the_post();
	?>
	<?php if ( function_exists( 'bfd_has_sections' ) && bfd_has_sections() ) : ?>
		<?php bfd_render_sections(); ?>
	<?php else : ?>
		<article class="container section entry-content"><?php the_content(); ?></article>
	<?php endif; ?>
<?php endwhile; ?>

<?php get_footer( 'landing' ); ?>
