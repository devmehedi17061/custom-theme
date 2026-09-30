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
	// Multi-step form with a conditional field, for the front-end test.
	$fields = \BFD\Core\Forms\Form_Definition::sanitize_fields(
		array(
			array( 'label' => 'Name', 'type' => 'text', 'required' => true ),
			array( 'label' => 'Topic', 'type' => 'select', 'options' => "a|Sales\nb|Support" ),
			array( 'label' => 'Order number', 'type' => 'text', 'required' => true, 'show_if' => array( 'field' => 'topic', 'op' => 'equals', 'value' => 'b' ) ),
			array( 'label' => 'Contact details', 'type' => 'step' ),
			array( 'label' => 'Email', 'type' => 'email', 'required' => true ),
		)
	);
	$form_id = wp_insert_post( array( 'post_type' => 'bfd_form', 'post_title' => 'UI Form Steps', 'post_status' => 'publish' ) );
	update_post_meta( $form_id, '_bfd_form_fields', wp_slash( wp_json_encode( $fields ) ) );
	update_post_meta( $form_id, '_bfd_form_settings', wp_slash( wp_json_encode( \BFD\Core\Forms\Form_Definition::sanitize_settings( array( 'store' => '0', 'recipients' => 'nobody@example.com' ) ) ) ) );
	$form_page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Zz UI Form Page', 'post_name' => 'zz-ui-form-page', 'post_status' => 'publish' ) );
	update_post_meta( $form_page, '_bfd_sections', wp_slash( wp_json_encode( array( array( 'type' => 'form', 'settings' => array(), 'fields' => array( 'heading' => 'Contact', 'form_id' => (string) $form_id ) ) ) ) ) );

	$custom = get_posts( array( 'post_type' => 'bfd_section', 'numberposts' => 1, 'fields' => 'ids' ) );
	echo wp_json_encode( array( 'page' => $id, 'custom' => $custom ? $custom[0] : 0, 'formpage' => $form_page ) );
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
