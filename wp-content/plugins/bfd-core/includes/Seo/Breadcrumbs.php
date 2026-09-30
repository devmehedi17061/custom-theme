<?php
namespace BFD\Core\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumb trail for the current request. Last item has an empty URL (current page).
 */
final class Breadcrumbs {

	/** @return array<int, array{name:string, url:string}> */
	public static function items(): array {
		if ( is_front_page() ) {
			return array();
		}

		$items   = array();
		$items[] = array( 'name' => __( 'Home', 'bfd-core' ), 'url' => home_url( '/' ) );

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( 'post' === $post->post_type ) {
				$posts_page = (int) get_option( 'page_for_posts' );
				if ( $posts_page ) {
					$items[] = array( 'name' => get_the_title( $posts_page ), 'url' => (string) get_permalink( $posts_page ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$items[] = array( 'name' => $cats[0]->name, 'url' => (string) get_category_link( $cats[0] ) );
				}
			} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					$items[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => (string) get_permalink( $ancestor_id ) );
				}
			} else {
				$obj = get_post_type_object( $post->post_type );
				if ( $obj && $obj->has_archive ) {
					$items[] = array( 'name' => $obj->labels->name, 'url' => (string) get_post_type_archive_link( $post->post_type ) );
				}
			}
			$items[] = array( 'name' => get_the_title( $post ), 'url' => '' );
		} elseif ( is_post_type_archive() ) {
			$items[] = array( 'name' => post_type_archive_title( '', false ), 'url' => '' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$items[] = array( 'name' => single_term_title( '', false ), 'url' => '' );
		} elseif ( is_search() ) {
			/* translators: %s: search query */
			$items[] = array( 'name' => sprintf( __( 'Search: %s', 'bfd-core' ), get_search_query() ), 'url' => '' );
		} elseif ( is_home() ) {
			$items[] = array( 'name' => single_post_title( '', false ) ?: __( 'Blog', 'bfd-core' ), 'url' => '' );
		} elseif ( is_404() ) {
			$items[] = array( 'name' => __( 'Not found', 'bfd-core' ), 'url' => '' );
		} elseif ( is_author() ) {
			$items[] = array( 'name' => get_the_author(), 'url' => '' );
		} elseif ( is_date() ) {
			$items[] = array( 'name' => get_the_archive_title(), 'url' => '' );
		}

		return (array) apply_filters( 'bfd_breadcrumb_items', $items );
	}

	public static function render(): void {
		$items = self::items();
		if ( count( $items ) < 2 ) {
			return;
		}
		echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'bfd-core' ) . '"><ol>';
		foreach ( $items as $item ) {
			if ( $item['url'] ) {
				printf( '<li><a href="%s">%s</a></li>', esc_url( $item['url'] ), esc_html( $item['name'] ) );
			} else {
				printf( '<li aria-current="page">%s</li>', esc_html( $item['name'] ) );
			}
		}
		echo '</ol></nav>';
	}
}
