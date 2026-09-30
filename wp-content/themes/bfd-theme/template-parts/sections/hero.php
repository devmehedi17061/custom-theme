<?php
$f     = $args['fields'] ?? array();
$first = 0 === ( $args['index'] ?? 0 );
$tag   = $first ? 'h1' : 'h2';
?>
<div class="hero">
	<div class="hero__content">
		<?php if ( ! empty( $f['heading'] ) ) : ?>
			<<?php echo $tag; ?> class="hero__title"><?php echo esc_html( $f['heading'] ); ?></<?php echo $tag; ?>>
		<?php endif; ?>
		<?php if ( ! empty( $f['subheading'] ) ) : ?>
			<p class="hero__sub"><?php echo esc_html( $f['subheading'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $f['button_text'] ) && ! empty( $f['button_url'] ) ) : ?>
			<a class="btn" href="<?php echo esc_url( $f['button_url'] ); ?>"><?php echo esc_html( $f['button_text'] ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $f['image'] ) ) : ?>
		<div class="hero__media">
			<?php
			echo wp_get_attachment_image(
				(int) $f['image'],
				'bfd-hero',
				false,
				$first ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' )
			);
			?>
		</div>
	<?php endif; ?>
</div>
