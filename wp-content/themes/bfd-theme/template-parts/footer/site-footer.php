<?php
$opt = function_exists( 'bfd_option' ) ? 'bfd_option' : static fn( $k, $d = '' ) => $d;

$socials = array(
	'facebook'  => 'Facebook',
	'x'         => 'X',
	'instagram' => 'Instagram',
	'linkedin'  => 'LinkedIn',
	'youtube'   => 'YouTube',
);
?>
<footer class="site-footer">
	<div class="container">
		<div class="site-footer__cols">
			<div class="site-footer__about">
				<strong class="site-footer__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
				<?php if ( $opt( 'footer_text' ) ) : ?>
					<p><?php echo nl2br( esc_html( $opt( 'footer_text' ) ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $opt( 'phone' ) || $opt( 'email' ) || $opt( 'address' ) ) : ?>
				<address class="site-footer__contact">
					<?php if ( $opt( 'phone' ) ) : ?>
						<div><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $opt( 'phone' ) ) ); ?>"><?php echo esc_html( $opt( 'phone' ) ); ?></a></div>
					<?php endif; ?>
					<?php if ( $opt( 'email' ) ) : ?>
						<div><a href="mailto:<?php echo esc_attr( antispambot( $opt( 'email' ) ) ); ?>"><?php echo esc_html( antispambot( $opt( 'email' ) ) ); ?></a></div>
					<?php endif; ?>
					<?php if ( $opt( 'address' ) ) : ?>
						<div><?php echo nl2br( esc_html( $opt( 'address' ) ) ); ?></div>
					<?php endif; ?>
				</address>
			<?php endif; ?>

			<nav class="footer-nav" aria-label="<?php esc_attr_e( 'Footer', 'bfd-theme' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'menu',
						'fallback_cb'    => false,
						'depth'          => 1,
					)
				);
				?>
			</nav>
		</div>

		<div class="site-footer__bottom">
			<p class="copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<ul class="social">
				<?php foreach ( $socials as $key => $label ) : ?>
					<?php if ( $opt( $key ) ) : ?>
						<li><a href="<?php echo esc_url( $opt( $key ) ); ?>" rel="noopener me" target="_blank"><?php echo esc_html( $label ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</footer>
