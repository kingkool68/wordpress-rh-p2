<?php
/**
 * Comments
 */
class RH_Comments {

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
	 * Hook into WordPress via actions
	 */
	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init' ) );
	}

	/**
	 * Hook in to WordPress via filters
	 */
	public function setup_filters() {}

	/**
	 * Register styles and scripts
	 */
	public function action_init() {
		wp_register_style(
			'rh-comment-form',
			get_template_directory_uri() . '/assets/css/rhp2-comment-form.min.css',
			$deps  = array( 'rh' ),
			$ver   = null,
			$media = 'all'
		);
	}

	/**
	 * Render comments and comment form
	 *
	 * @param  array   $args  Arguments to override what is rendered
	 * @param  WP_Post $post The WordPress post to render comments for
	 */
	public static function render( $args = array(), $post = null ) {
		if ( ! comments_open() && ! get_comments_number() ) {
			return;
		}
		$post     = get_post( $post );
		$defaults = array(
			'the_comments_count'     => get_comments_number( $post->ID ),
			'the_comments'           => static::render_comments( $post->ID ),
			'the_comment_form'       => static::render_comment_form(),
			'the_comments_permalink' => '',
		);
		$context  = wp_parse_args( $args, $defaults );
		if ( ! is_singular() ) {
			$context['the_comment_form']       = '';
			$context['the_comments_permalink'] = get_permalink( $post );
		}
		return Sprig::render( 'the-comments.twig', $context );
	}

	/**
	 * Render the comments for a post
	 *
	 * @param  WP_Post $post The WordPress post to render comments for
	 */
	public static function render_comments( $post = null ) {
		$post         = get_post( $post );
		$comment_args = array(
			'post_id' => $post->ID,
			'orderby' => 'comment_date_gmt',
			'order'   => 'ASC',
			'status'  => 'approve',
		);

		if ( is_user_logged_in() ) {
			$comment_args['include_unapproved'] = array( get_current_user_id() );
		} else {
			$unapproved_email = wp_get_unapproved_comment_author_email();
			if ( $unapproved_email ) {
				$comment_args['include_unapproved'] = array( $unapproved_email );
			}
		}
		$comments     = get_comments( $comment_args );
		$the_comments = wp_list_comments(
			array(
				'avatar_size'  => 60,
				'style'        => 'ol',
				'short_ping'   => false,
				'echo'         => false,
				'type'         => 'comment',
				'format'       => 'html5',
				'callback'     => array( static::class, 'render_comment' ),
				'end-callback' => function () {
					return '</li>';
				},
			),
			$comments
		);
		return $the_comments;
	}

	/**
	 * Render an individual comment
	 *
	 * @param  WP_Comment $the_comment The WordPress comment object with data to render
	 * @param  array      $args Arguments to override what is rendered
	 * @param  integer    $the_depth The depth of rendering comments
	 */
	public static function render_comment( $the_comment, $args = array(), $the_depth = 1 ) {
		$comment_date = get_comment_datetime( $the_comment );
		$comment_text = get_comment_text( $the_comment );
		$comment_text = make_clickable( $comment_text );
		$comment_text = static::link_cross_posts( $comment_text );
		$context      = array(
			'the_id'           => $the_comment->comment_ID,
			'the_name'         => $the_comment->comment_author,
			'the_avatar_url'   => get_avatar_url(
				$the_comment->comment_author_email,
				array(
					'size' => absint( $args['avatar_size'] ),
				)
			),
			'the_date'         => $comment_date->format( 'g:i a \o\n F j, Y' ),
			'the_machine_date' => $comment_date->format( DATE_W3C ),
			'the_comment'      => apply_filters( 'the_content', $comment_text ),
			'the_reply_link'   => get_comment_reply_link(
				array(
					'depth'     => $the_depth,
					'max_depth' => absint( $args['max_depth'] ),
				)
			),
			'the_depth'        => $the_depth,
		);
		Sprig::out( 'comment.twig', $context );
	}

	/**
	 * Render a comment form
	 *
	 * @link https://developer.wordpress.org/reference/functions/comment_form/
	 *
	 * @param  array   $args Arguments to override what is rendered
	 * @param  WP_Post $post The WordPress post to render the comment form for
	 */
	public static function render_comment_form( $args = array(), $post = null ) {
		$defaults = array(
			'logged_in_as'         => static::render_logged_in_as(),
			'title_reply'          => '',
			'title_reply_to'       => '<span class="reply-to">to %s</span>',
			'title_reply_before'   => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'    => '</h2>',
			'cancel_reply_link'    => 'Cancel',
			'comment_notes_before' => '',
			'comment_notes_after'  => '',
			'label_submit'         => 'Reply',
			'format'               => 'html5',
		);
		$args     = wp_parse_args( $args, $defaults );
		wp_enqueue_style( 'rh-comment-form' );
		wp_enqueue_script( 'comment-reply' );
		ob_start();
		comment_form( $args, $post );
		return ob_get_clean();
	}

	/**
	 * Render markup for the comment form when a user is loffed in
	 *
	 * @param  array   $args Arguments to override what is rendered
	 * @param  WP_Post $post The WordPress post to render for
	 */
	public static function render_logged_in_as( $args = array(), $post = null ) {
		$post           = get_post( $post );
		$the_permalink  = apply_filters( 'the_permalink', get_permalink( $post->ID ), $post->ID );
		$user           = wp_get_current_user();
		$logged_in_name = '';
		if ( ! empty( $user->display_name ) ) {
			$logged_in_name = $user->display_name;
		}
		$defaults = array(
			'the_name'       => $logged_in_name,
			'the_avatar_url' => get_avatar_url(
				$user->ID,
				array(
					'size' => 48,
				)
			),
			'the_logout_url' => wp_logout_url( $the_permalink ),
		);
		$context  = wp_parse_args( $args, $defaults );
		return Sprig::render( 'comment-form--logged-in-as.twig', $context );
	}

	/**
	 * Link occurances of cross-posted blogs to their respective domains
	 *
	 * @example +example --> https://example.wordpress.com
	 *
	 * @param  string $text The text to find x-posts in
	 */
	public static function link_cross_posts( $text = '' ) {
		preg_match_all( '/\+((\w)+)/i', $text, $matches );
		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $index => $link_text ) {
				if ( ! empty( $matches[1][ $index ] ) ) {
					$link_url = 'https://' . $matches[1][ $index ] . '.wordpress.com/';
					$link     = '<a href="' . esc_url( $link_url ) . '" class="x-post-link">' . $link_text . '</a>';
					$text     = str_replace( $link_text, $link, $text );
				}
			}
		}
		return $text;
	}
}
RH_Comments::get_instance();
