<?php
setup_postdata( get_post() );

$date      = get_post_datetime( $post );
$author_id = get_the_author_meta( 'ID' );

$the_tags = array();
$tags     = wp_get_object_terms( get_the_ID(), 'post_tag', array() );
if ( ! empty( $tags ) ) {
	foreach ( $tags as $the_tag ) {
		$the_tags[ $the_tag->slug ] = get_term_link( $the_tag );
	}
}

$context = array(
	'the_title'                => get_the_title(),
	'the_author'               => get_the_author_meta( 'display_name', $author_id ),
	'the_author_avatar_url'    => get_avatar_url(
		$author_id,
		array(
			'size' => 80,
		)
	),
	'the_publish_date'         => $date->format( 'g:i a \o\n F j, Y' ),
	'the_publish_machine_date' => $date->format( DATE_W3C ),
	'the_content'              => apply_filters( 'the_content', get_the_content() ),
	'the_tags'                 => $the_tags,
);
Sprig::out( 'single-post.twig', $context );
