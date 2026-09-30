<?php
namespace BFD\Core\Seo;

use BFD\Core\Sections\Section_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the JSON-LD @graph: Organization, WebSite, WebPage/Article, BreadcrumbList, FAQPage.
 */
final class Schema {

	public static function graph( ?\WP_Post $post, string $title, string $desc, string $url, string $image ): array {
		$home   = home_url( '/' );
		$org_id = $home . '#organization';
		$site   = $home . '#website';

		$graph = array();

		$org     = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => bfd_option( 'org_name', get_bloginfo( 'name' ) ),
			'url'   => $home,
		);
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo ) {
				$org['logo'] = $logo;
			}
		}
		$same = array_values( array_filter( array_map( 'bfd_option', array( 'facebook', 'x', 'instagram', 'linkedin', 'youtube' ) ) ) );
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		if ( bfd_option( 'phone' ) ) {
			$org['telephone'] = bfd_option( 'phone' );
		}
		if ( bfd_option( 'email' ) ) {
			$org['email'] = bfd_option( 'email' );
		}
		$graph[] = $org;

		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => $site,
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'publisher'       => array( '@id' => $org_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);

		if ( $post ) {
			$is_article = 'post' === $post->post_type;
			$page       = array(
				'@type'         => $is_article ? 'Article' : 'WebPage',
				'@id'           => $url . '#webpage',
				'url'           => $url,
				'name'          => $title,
				'headline'      => wp_html_excerpt( get_the_title( $post ), 110 ),
				'description'   => $desc,
				'inLanguage'    => get_bloginfo( 'language' ),
				'isPartOf'      => array( '@id' => $site ),
				'datePublished' => get_the_date( DATE_W3C, $post ),
				'dateModified'  => get_the_modified_date( DATE_W3C, $post ),
			);
			if ( $image ) {
				$page['image'] = $image;
			}
			if ( $is_article ) {
				$page['author']    = array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
				);
				$page['publisher'] = array( '@id' => $org_id );
			}
			$graph[] = $page;

			$faq = self::faq_entities( $post );
			if ( $faq ) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => $url . '#faq',
					'mainEntity' => $faq,
				);
			}
		}

		$crumbs = Breadcrumbs::items();
		if ( count( $crumbs ) > 1 ) {
			$list = array();
			foreach ( $crumbs as $i => $c ) {
				$item = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $c['name'],
				);
				if ( $c['url'] ) {
					$item['item'] = $c['url'];
				}
				$list[] = $item;
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $list,
			);
		}

		return (array) apply_filters( 'bfd_schema_graph', $graph, $post );
	}

	/** FAQPage entities from any FAQ sections on the post. */
	private static function faq_entities( \WP_Post $post ): array {
		$out = array();
		foreach ( Section_Registry::get_sections( $post->ID ) as $s ) {
			if ( 'faq' !== ( $s['type'] ?? '' ) ) {
				continue;
			}
			foreach ( $s['fields']['items'] ?? array() as $item ) {
				if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
					continue;
				}
				$out[] = array(
					'@type'          => 'Question',
					'name'           => $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['answer'],
					),
				);
			}
		}
		return $out;
	}
}
