<?php
$context = array(
	'the_title'   => apply_filters( 'the_title', get_the_title() ),
	'the_content' => apply_filters( 'the_content', get_the_content() ),
);
Sprig::out( 'page.twig', $context );
