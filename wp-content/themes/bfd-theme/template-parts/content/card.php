<article <?php post_class( 'card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'bfd-card', array( 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="card__body">
		<h2 class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="card__meta"><?php bfd_posted_on(); ?></div>
		<?php if ( $args['excerpt'] ?? true ) : ?>
			<div class="card__excerpt"><?php the_excerpt(); ?></div>
		<?php endif; ?>
	</div>
</article>
