<?php
$main_nav_args = array(
	'theme_location' => 'main',
	'container'      => false,
	'items_wrap'     => '%3$s', // Prevents wrapping in any markup
	'echo'           => false,
);
	$main_nav  = wp_nav_menu( $main_nav_args );


$context = array(
	'site_url'  => get_site_url(),
	'site_name' => get_bloginfo( 'name' ),
	'main_nav'  => $main_nav,
);
Sprig::out( 'header.twig', $context );
