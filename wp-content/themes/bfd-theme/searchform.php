<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="s"><?php esc_html_e( 'Search', 'bfd-theme' ); ?></label>
	<input type="search" id="s" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'bfd-theme' ); ?>">
	<button type="submit" class="btn"><?php esc_html_e( 'Search', 'bfd-theme' ); ?></button>
</form>
