<?php $f = $args['fields'] ?? array(); ?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<div class="faq">
	<?php foreach ( $f['items'] ?? array() as $item ) : ?>
		<?php if ( empty( $item['question'] ) ) { continue; } ?>
		<details class="faq__item">
			<summary><?php echo esc_html( $item['question'] ); ?></summary>
			<p><?php echo nl2br( esc_html( $item['answer'] ?? '' ) ); ?></p>
		</details>
	<?php endforeach; ?>
</div>
