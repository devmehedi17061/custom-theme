<?php get_header(); ?>

<?php bfd_theme_breadcrumbs(); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<?php if ( function_exists( 'bfd_has_sections' ) && bfd_has_sections() ) : ?>
		<?php bfd_render_sections(); ?>
	<?php else : ?>
		<article <?php post_class( 'container section single' ); ?>>
			<header class="entry-header">
				<h1 class="page-title"><?php the_title(); ?></h1>
				<?php if ( 'post' === get_post_type() ) : ?>
					<div class="entry-meta"><?php bfd_posted_on(); ?></div>
				<?php endif; ?>
			</header>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="entry-thumb"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
			<footer class="entry-footer">
				<?php if ( 'post' === get_post_type() ) : ?>
					<?php the_category( ', ' ); ?>
					<?php the_tags( '<div class="tags">', ', ', '</div>' ); ?>
				<?php endif; ?>
			</footer>
			<?php if ( 'post' === get_post_type() ) : ?>
				<?php bfd_author_box(); ?>
			<?php endif; ?>
		</article>
	<?php endif; ?>

	<?php $related = bfd_related_posts( get_the_ID() ); ?>
	<?php if ( $related->have_posts() ) : ?>
		<section class="container section related">
			<h2 class="section__title"><?php esc_html_e( 'Related', 'bfd-theme' ); ?></h2>
			<div class="card-grid">
				<?php
				while ( $related->have_posts() ) :
					$related->the_post();
					get_template_part( 'template-parts/content/card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>
<?php endwhile; ?>

<?php get_footer(); ?>
