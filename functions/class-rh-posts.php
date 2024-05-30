<?php
use voku\helper\HtmlDomParser;
/**
 * Handles blog posts
 */
class RH_Posts {

	/**
	 * The post type
	 *
	 * @var string
	 */
	public static $post_type = 'post';

	/**
	 * Get an instance of this class
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new static();
			$instance->setup_actions();
			$instance->setup_filters();
		}
		return $instance;
	}

	/**
	 * Hook in to WordPress via actions
	 */
	public function setup_actions() {
		add_action( 'acf/init', array( $this, 'action_acf_init' ) );
	}

	/**
	 * Hook in to WordPress via filters
	 */
	public function setup_filters() {
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_rank_math_frontend_title' ) );
	}

	/**
	 * Register ACF custom fields
	 */
	public function action_acf_init() {
		$args = array(
			'key'        => 'processing_option_fields',
			'title'      => 'Processing Options',
			'position'   => 'side',
			'menu_order' => 10,
			'fields'     => array(
				array(
					'key'     => 'field_processing_options_markdown',
					'name'    => 'processing_options_markdown',
					'label'   => ' ',
					'type'    => 'true_false',
					'message' => 'Convert Markdown to HTML on save',
				),
			),
			'location'   => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => static::$post_type,
					),
				),
			),
		);
		acf_add_local_field_group( $args );
	}

	/**
	 * Remove or add items to the body class
	 *
	 * @param array $class The body classes to modify
	 * @return array Modified body class items
	 */
	public function filter_body_class( $class = array() ) {
		$values_to_remove = array( 'single-post' );
		$class            = array_diff( $class, $values_to_remove );
		return $class;
	}

	/**
	 * Create unique page titles for blog searches
	 *
	 * @param string $title The page title to be modified
	 */
	public function filter_rank_math_frontend_title( $title = '' ) {
		if ( is_home() && ! empty( get_search_query() ) ) {
			$the_search_query = wp_trim_words( get_search_query(), $num_words = 5, $more = '...' );
			$title            = str_replace( 'Blog', 'Blog search for ' . $the_search_query, $title );
		}
		return $title;
	}

	/**
	 * Render a post
	 *
	 * @param  array $args Arguments to modify what is rendered
	 */
	public static function render( $args = array() ) {
		$defaults               = array(
			'the_title'                => '',
			'the_title_url'            => '',
			'the_author'               => '',
			'the_author_avatar_url'    => '',
			'the_publish_date'         => '',
			'the_publish_machine_date' => '',
			'the_content'              => '',
			'the_tags'                 => array(),
			'the_comments'             => '',
			'the_comment_form'         => '',
		);
		$context                = wp_parse_args( $args, $defaults );
		$context['the_title']   = apply_filters( 'the_title', $context['the_title'] );
		$context['the_content'] = apply_filters( 'the_content', $context['the_content'] );
		return Sprig::render( 'the-post.twig', $context );
	}

	/**
	 * Render a post from a WP_Post object
	 *
	 * @param  WP_Post|integer $post The WP_Post to use as data for rendering
	 * @param  array           $args Arguments to modify what is rendered
	 */
	public static function render_from_post( $post = 0, $args = array() ) {
		$post      = get_post( $post );
		$date      = get_post_datetime( $post );
		$author_id = $post->post_author;

		$the_tags = array();
		$tags     = wp_get_object_terms( $post->ID, 'post_tag', array() );
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $the_tag ) {
				$the_tags[ $the_tag->slug ] = get_term_link( $the_tag );
			}
		}

		$defaults = array(
			'the_title'                => get_the_title( $post ),
			'the_title_url'            => get_permalink( $post ),
			'the_author'               => get_the_author_meta( 'display_name', $author_id ),
			'the_author_avatar_url'    => get_avatar_url(
				$author_id,
				array(
					'size' => 80,
				)
			),
			'the_publish_date'         => $date->format( 'g:i a \o\n F j, Y' ),
			'the_publish_machine_date' => $date->format( DATE_W3C ),
			'the_content'              => get_the_content( $more_link_text = null, $strip_teaser = false, $post ),
			'the_tags'                 => $the_tags,
			'the_comments_count'        => get_comments_number( $post->ID ),
			'the_comments'             => RH_Comments::render_comments(),
			'the_comment_form'         => RH_Comments::render_comment_form(),
		);
		$args = wp_parse_args( $args, $defaults );
		return static::render( $args );
	}

	/**
	 * Render a series of posts from a WP_Query
	 *
	 * @param  WP_Query $the_query The WP_Query to loop over and get posts
	 * @param  array    $args Arguments to modify what is rendered
	 */
	public static function render_from_wp_query( $the_query = false, $args = array() ) {
		return RH_Helpers::render_from_wp_query( $the_query, $args, array( static::class, 'render_from_post' ) );
	}
}
RH_Posts::get_instance();
