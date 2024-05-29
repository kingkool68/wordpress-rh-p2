<?php
$context = array(
	'the_post'         => RH_Posts::render_from_post(
		null,
		array(
			'the_title_url' => false,
		)
	),
);
Sprig::out( 'single-post.twig', $context );
