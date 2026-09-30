<?php $f = $args['fields'] ?? array(); ?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<dl class="stats">
	<?php foreach ( $f['items'] ?? array() as $item ) : ?>
		<div class="stat">
			<dt class="stat__label"><?php echo esc_html( $item['label'] ?? '' ); ?></dt>
			<dd class="stat__number"><?php echo esc_html( $item['number'] ?? '' ); ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
