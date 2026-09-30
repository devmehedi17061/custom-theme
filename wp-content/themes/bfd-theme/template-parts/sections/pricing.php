<?php $f = $args['fields'] ?? array(); $plans = $f['plans'] ?? array(); ?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<?php if ( ! empty( $f['intro'] ) ) : ?>
	<p class="section__intro"><?php echo esc_html( $f['intro'] ); ?></p>
<?php endif; ?>

<div class="bfd-grid bfd-grid--<?php echo (int) min( 4, max( 2, count( $plans ) ) ); ?> pricing">
	<?php foreach ( $plans as $plan ) : ?>
		<div class="plan<?php echo ! empty( $plan['highlight'] ) ? ' plan--highlight' : ''; ?>">
			<h3 class="plan__name"><?php echo esc_html( $plan['name'] ?? '' ); ?></h3>
			<p class="plan__price">
				<span class="plan__amount"><?php echo esc_html( $plan['price'] ?? '' ); ?></span>
				<span class="plan__period"><?php echo esc_html( $plan['period'] ?? '' ); ?></span>
			</p>
			<?php $features = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $plan['features'] ?? '' ) ) ) ); ?>
			<?php if ( $features ) : ?>
				<ul class="plan__features">
					<?php foreach ( $features as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $plan['button_text'] ) && ! empty( $plan['button_url'] ) ) : ?>
				<a class="btn" href="<?php echo esc_url( $plan['button_url'] ); ?>"><?php echo esc_html( $plan['button_text'] ); ?></a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
