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
	 * Render an archive item using data passed to the method
	 *
	 * @param array $args Data to use to render the component
	 * @return string A rendered archive item component
	 */
	public static function render_archive_item( $args = array() ) {
		$defaults = array(
			'the_title'        => '',
			'the_date'         => '',
			'display_date'     => '',
			'display_time'     => '',
			'display_datetime' => '',
			'machine_date'     => '',
			'the_categories'   => '',
			'the_excerpt'      => '',
			'the_url'          => '',
			'the_image'        => '',
		);

		$context                = wp_parse_args( $args, $defaults );
		$context['the_title']   = apply_filters( 'the_title', $context['the_title'] );
		$context['the_excerpt'] = apply_filters( 'the_content', $context['the_excerpt'] );

		if ( ! empty( $context['the_date'] ) ) {
			$date                        = RH_Helpers::get_date_values( $context['the_date'] );
			$context['display_date']     = $date->display_date;
			$context['display_time']     = $date->display_time;
			$context['display_datetime'] = $date->display_datetime;
			$context['machine_date']     = $date->machine_date;
		}

		return Sprig::render( 'post-archive-item.twig', $context );
	}

	/**
	 * Render an archive item using data from a WP_Post object
	 *
	 * @param integer|WP_Post $post WordPress post to fetch data for
	 * @param array           $args Arguments to override the post data
	 * @return string A rendered archive item
	 */
	public static function render_archive_item_from_post( $post, $args = array() ) {
		$post  = get_post( $post );
		$image = RH_Media::render_image_from_post( $post->ID );
		if ( empty( $image ) && defined( 'RH_DEFAULT_BLOG_FEATURED_IMAGE_ID' ) ) {
			$image = RH_Media::render_image_from_post( RH_DEFAULT_BLOG_FEATURED_IMAGE_ID );
		}
		$defaults = array(
			'the_title'   => get_the_title( $post ),
			'the_date'    => $post->post_date,
			'the_excerpt' => get_the_excerpt( $post ),
			'the_url'     => get_permalink( $post ),
			'the_image'   => $image,
		);
		$args     = wp_parse_args( $args, $defaults );

		return static::render_archive_item( $args );
	}

	/**
	 * Render archive items from a WP_Query object
	 *
	 * @param boolean $the_query WP_Query object to loop over and render archive items
	 * @param array   $args Arguments to pass to the render_archive_item_from_post() method
	 *
	 * @throws Exception $the_query is not a WP_Query object
	 *
	 * @return string Rendered archive items
	 */
	public static function render_archive_items_from_wp_query( $the_query = false, $args = array() ) {
		global $wp_query;
		if ( ! $the_query ) {
			$the_query = $wp_query;
		}
		if ( ! $the_query instanceof WP_Query ) {
			throw new Exception( '$the_query is not a WP_Query object!' );
		}

		if ( empty( $the_query->posts ) ) {
			return '';
		}

		$output = array();
		while ( $the_query->have_posts() ) :
			$post     = $the_query->the_post();
			$output[] = static::render_archive_item_from_post( $post, $args );
		endwhile;
		wp_reset_postdata();
		return implode( "\n", $output );
	}
}
RH_Posts::get_instance();
