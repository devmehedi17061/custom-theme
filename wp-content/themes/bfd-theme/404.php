<?php get_header(); ?>

<div class="container section not-found">
	<h1 class="page-title">404</h1>
	<p><?php esc_html_e( 'That page could not be found.', 'bfd-theme' ); ?></p>
	<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'bfd-theme' ); ?></a>
</div>

<?php get_footer(); ?>
