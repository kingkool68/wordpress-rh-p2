<?php
setup_postdata( get_post() );

$date      = RH_Helpers::get_date_values( $post->post_date );
$author_id = get_the_author_meta( 'ID' );

$context = array(
	'the_title'             => get_the_title(),
	'the_author'            => get_the_author_meta( 'display_name', $author_id ),
	'the_author_avatar_url' => get_avatar_url(
		$author_id,
		array(
			'size' => 96,
		)
	),
	'display_date'          => $date->display_date,
	'machine_date'          => $date->machine_date,
	'the_content'           => apply_filters( 'the_content', get_the_content() ),
);
Sprig::out( 'single-post.twig', $context );
