<?php
$f    = $args['fields'] ?? array();
$cols = in_array( $f['columns'] ?? '4', array( '2', '3', '4' ), true ) ? $f['columns'] : '4';
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<?php if ( ! empty( $f['intro'] ) ) : ?>
	<p class="section__intro"><?php echo esc_html( $f['intro'] ); ?></p>
<?php endif; ?>

<div class="bfd-grid bfd-grid--<?php echo esc_attr( $cols ); ?>">
	<?php foreach ( $f['members'] ?? array() as $m ) : ?>
		<div class="member">
			<?php if ( ! empty( $m['image'] ) ) : ?>
				<?php echo wp_get_attachment_image( (int) $m['image'], 'bfd-card', false, array( 'class' => 'member__photo', 'loading' => 'lazy' ) ); ?>
			<?php endif; ?>
			<h3 class="member__name"><?php echo esc_html( $m['name'] ?? '' ); ?></h3>
			<?php if ( ! empty( $m['role'] ) ) : ?><p class="member__role"><?php echo esc_html( $m['role'] ); ?></p><?php endif; ?>
			<?php if ( ! empty( $m['bio'] ) ) : ?><p><?php echo esc_html( $m['bio'] ); ?></p><?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
