<?php $f = $args['fields'] ?? array(); ?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title logos__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<ul class="logos">
	<?php foreach ( $f['logos'] ?? array() as $logo ) : ?>
		<?php
		if ( empty( $logo['image'] ) ) {
			continue;
		}
		$img = wp_get_attachment_image(
			(int) $logo['image'],
			'medium',
			false,
			array(
				'alt'     => $logo['name'] ?? '',
				'loading' => 'lazy',
			)
		);
		?>
		<li>
			<?php if ( ! empty( $logo['url'] ) ) : ?>
				<a href="<?php echo esc_url( $logo['url'] ); ?>" rel="noopener"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<?php else : ?>
				<?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
