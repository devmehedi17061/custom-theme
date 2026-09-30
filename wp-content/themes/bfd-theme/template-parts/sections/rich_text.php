<?php $f = $args['fields'] ?? array(); ?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<div class="entry-content"><?php echo wp_kses_post( $f['content'] ?? '' ); ?></div>
