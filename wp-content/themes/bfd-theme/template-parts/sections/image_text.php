<?php
$f    = $args['fields'] ?? array();
$side = ( $f['image_side'] ?? 'right' ) === 'left' ? 'left' : 'right';
?>
<div class="image-text image-text--<?php echo esc_attr( $side ); ?>">
	<div class="image-text__body">
		<?php if ( ! empty( $f['heading'] ) ) : ?>
			<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
		<?php endif; ?>
		<?php if ( ! empty( $f['text'] ) ) : ?>
			<div class="entry-content"><?php echo wp_kses_post( $f['text'] ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $f['button_text'] ) && ! empty( $f['button_url'] ) ) : ?>
			<a class="btn" href="<?php echo esc_url( $f['button_url'] ); ?>"><?php echo esc_html( $f['button_text'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $f['image'] ) ) : ?>
		<div class="image-text__media"><?php echo wp_get_attachment_image( (int) $f['image'], 'large', false, array( 'loading' => 'lazy' ) ); ?></div>
	<?php endif; ?>
</div>
