<?php
$f   = $args['fields'] ?? array();
$url = $f['url'] ?? '';

// Only YouTube, Vimeo and direct video files are embedded; anything else is ignored.
$embed = '';
$file  = '';
if ( preg_match( '#^https?://(?:www\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', $url, $m ) ) {
	$embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
} elseif ( preg_match( '#^https?://(?:www\.)?vimeo\.com/(\d{5,12})#', $url, $m ) ) {
	$embed = 'https://player.vimeo.com/video/' . $m[1];
} elseif ( preg_match( '#^https?://[^\s]+\.(?:mp4|webm)(?:\?.*)?$#i', $url ) ) {
	$file = $url;
}
?>
<?php if ( ! empty( $f['heading'] ) ) : ?>
	<h2 class="section__title"><?php echo esc_html( $f['heading'] ); ?></h2>
<?php endif; ?>
<?php if ( $embed || $file ) : ?>
	<figure class="video">
		<div class="video__frame">
			<?php if ( $embed ) : ?>
				<iframe src="<?php echo esc_url( $embed ); ?>" title="<?php echo esc_attr( $f['caption'] ?? $f['heading'] ?? 'Video' ); ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
			<?php else : ?>
				<video src="<?php echo esc_url( $file ); ?>" controls preload="metadata"></video>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $f['caption'] ) ) : ?><figcaption><?php echo esc_html( $f['caption'] ); ?></figcaption><?php endif; ?>
	</figure>
<?php elseif ( current_user_can( 'edit_pages' ) ) : ?>
	<p class="section__intro"><em><?php esc_html_e( 'Enter a YouTube, Vimeo or .mp4 URL.', 'bfd-theme' ); ?></em></p>
<?php endif; ?>
