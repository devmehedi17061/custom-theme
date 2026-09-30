<?php
$f    = $args['fields'] ?? array();
$cols = in_array( $f['columns'] ?? '3', array( '2', '3', '4' ), true ) ? $f['columns'] : '3';
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<div class="bfd-grid bfd-grid--<?php echo esc_attr( $cols ); ?> gallery">
	<?php foreach ( $f['images'] ?? array() as $img ) : ?>
		<?php
		if ( empty( $img['image'] ) ) {
			continue; }
		?>
		<figure>
			<?php echo wp_get_attachment_image( (int) $img['image'], 'bfd-card', false, array( 'loading' => 'lazy' ) ); ?>
			<?php
			if ( ! empty( $img['caption'] ) ) :
				?>
				<figcaption><?php echo esc_html( $img['caption'] ); ?></figcaption><?php endif; ?>
		</figure>
	<?php endforeach; ?>
</div>
