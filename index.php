<?php
$context = array(
	'the_posts'  => RH_Posts::render_from_wp_query(),
	'pagination' => RH_Pagination::render_from_wp_query(),
);
Sprig::out( 'index.twig', $context );
