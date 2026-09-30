<?php $f = $args['fields'] ?? array(); ?>
<div class="form-section">
	<?php if ( ! empty( $f['heading'] ) ) : ?>
		<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
	<?php endif; ?>
	<?php if ( ! empty( $f['intro'] ) ) : ?>
		<p class="section__intro"><?php echo esc_html( $f['intro'] ); ?></p>
	<?php endif; ?>
	<?php
	if ( ! empty( $f['form_id'] ) && function_exists( 'bfd_render_form' ) ) {
		bfd_render_form( (int) $f['form_id'] );
	}
	?>
</div>
