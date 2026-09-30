<?php $f = $args['fields'] ?? array(); ?>
<div class="cta">
	<?php if ( ! empty( $f['heading'] ) ) : ?>
		<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
	<?php endif; ?>
	<?php if ( ! empty( $f['text'] ) ) : ?>
		<p><?php echo esc_html( $f['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $f['button_text'] ) && ! empty( $f['button_url'] ) ) : ?>
		<a class="btn" href="<?php echo esc_url( $f['button_url'] ); ?>"><?php echo esc_html( $f['button_text'] ); ?></a>
	<?php endif; ?>
</div>
