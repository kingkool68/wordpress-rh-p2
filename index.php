<?php
$the_title          = '';
$the_queried_object = get_queried_object();
if ( ! empty( $the_queried_object->name ) ) {
	$the_title = apply_filters( 'the_title', $the_queried_object->name );
	$the_title = 'Tagged #' . $the_title;
}

$context = array(
	'the_title'  => $the_title,
	'the_posts'  => RH_Posts::render_from_wp_query(),
	'pagination' => RH_Pagination::render_from_wp_query(),
);
Sprig::out( 'index.twig', $context );
