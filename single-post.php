<?php
setup_postdata( get_post() );

$date      = get_post_datetime( $post );
$author_id = get_the_author_meta( 'ID' );

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
);
Sprig::out( 'single-post.twig', $context );
