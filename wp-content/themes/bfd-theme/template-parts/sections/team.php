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
	<?php foreach ( $f['members'] ?? array() as $member ) : ?>
		<div class="member">
			<?php if ( ! empty( $member['image'] ) ) : ?>
				<?php
				echo wp_get_attachment_image(
					(int) $member['image'],
					'bfd-card',
					false,
					array(
						'class'   => 'member__photo',
						'loading' => 'lazy',
					)
				);
				?>
			<?php endif; ?>
			<h3 class="member__name"><?php echo esc_html( $member['name'] ?? '' ); ?></h3>
			<?php
			if ( ! empty( $member['role'] ) ) :
				?>
				<p class="member__role"><?php echo esc_html( $member['role'] ); ?></p><?php endif; ?>
			<?php
			if ( ! empty( $member['bio'] ) ) :
				?>
				<p><?php echo esc_html( $member['bio'] ); ?></p><?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
