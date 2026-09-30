<?php
/** Creates/removes scratch content for UI tests. Usage: php fixture.php setup|teardown */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 2 ) . '/wp-load.php';

if ( 'setup' === ( $argv[1] ?? '' ) ) {
	$sections = array(
		array( 'type' => 'hero', 'settings' => array(), 'fields' => array( 'heading' => 'UI Hero' ) ),
		array( 'type' => 'cta', 'settings' => array(), 'fields' => array( 'heading' => 'UI CTA' ) ),
	);
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Zz UI Test', 'post_name' => 'zz-ui-test', 'post_status' => 'publish' ) );
	update_post_meta( $id, '_bfd_sections', wp_slash( wp_json_encode( $sections ) ) );
	$custom = get_posts( array( 'post_type' => 'bfd_section', 'numberposts' => 1, 'fields' => 'ids' ) );
	echo wp_json_encode( array( 'page' => $id, 'custom' => $custom ? $custom[0] : 0 ) );
	exit;
}

// teardown: everything the UI tests created.
foreach ( get_posts( array( 'post_type' => array( 'page', 'bfd_form' ), 'post_status' => 'any', 'numberposts' => -1, 's' => 'UI ' ) ) as $p ) {
	if ( 0 === strpos( $p->post_title, 'Zz UI' ) || 0 === strpos( $p->post_title, 'UI Form' ) ) {
		wp_delete_post( $p->ID, true );
	}
}
foreach ( get_posts( array( 'post_type' => array( 'page', 'bfd_form' ), 'post_status' => 'auto-draft', 'numberposts' => -1 ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}
echo 'cleaned';
