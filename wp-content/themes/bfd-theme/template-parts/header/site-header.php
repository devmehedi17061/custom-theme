<header class="site-header">
	<div class="container site-header__inner">
		<div class="site-branding"><?php bfd_site_branding(); ?></div>

		<button class="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'bfd-theme' ); ?></span>
			<span class="nav-toggle__bar" aria-hidden="true"></span>
		</button>

		<nav id="primary-nav" class="primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'bfd-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => false,
					'depth'          => 2,
				)
			);
			?>
		</nav>

		<?php if ( function_exists( 'bfd_option' ) && bfd_option( 'header_cta_text' ) && bfd_option( 'header_cta_url' ) ) : ?>
			<a class="btn site-header__cta" href="<?php echo esc_url( bfd_option( 'header_cta_url' ) ); ?>"><?php echo esc_html( bfd_option( 'header_cta_text' ) ); ?></a>
		<?php endif; ?>
	</div>
</header>
