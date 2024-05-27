<?php
$context = array(
	'site_url'   => get_site_url(),
	'site_name'  => get_bloginfo( 'name' ),
	'close_icon' => RH_SVG::get_icon( 'close' ),
	'menu_icon'  => RH_SVG::get_icon( 'menu' ),
);
Sprig::out( 'header.twig', $context );
