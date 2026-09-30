<?php
$f       = $args['fields'] ?? array();
$columns = in_array( $f['columns'] ?? '3', array( '2', '3', '4' ), true ) ? $f['columns'] : '3';
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<?php if ( ! empty( $f['intro'] ) ) : ?>
	<p class="section__intro"><?php echo esc_html( $f['intro'] ); ?></p>
<?php endif; ?>

<?php if ( ! empty( $f['items'] ) ) : ?>
	<div class="feature-grid feature-grid--<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $f['items'] as $item ) : ?>
			<div class="feature">
				<?php if ( ! empty( $item['image'] ) ) : ?>
					<?php echo wp_get_attachment_image( (int) $item['image'], 'bfd-card', false, array( 'loading' => 'lazy' ) ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $item['title'] ) ) : ?>
					<h3 class="feature__title">
						<?php if ( ! empty( $item['url'] ) ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['title'] ); ?>
						<?php endif; ?>
					</h3>
				<?php endif; ?>
				<?php if ( ! empty( $item['text'] ) ) : ?>
					<p><?php echo esc_html( $item['text'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
