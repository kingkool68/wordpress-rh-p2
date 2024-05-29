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

	public static function render() {
		if ( comments_open() || get_comments_number() ) {
			// return comments_template();
		}
		return '';
	}

	public static function render_comments() {
		$comment_args = array(
			'post_id' => get_the_ID(),
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
				'avatar_size' => 60,
				'style'       => 'ol',
				'short_ping'  => false,
				'echo'        => false,
				'type'        => 'comment',
				// 'callback'    => array( static::class, 'render_comment' ),
			),
			$comments
		);
		return $the_comments;
	}

	public static function render_comment( $comment, $args, $depth ) {
		var_dump( $comment );
		echo '<p>Comment!</p>';
	}

	public static function render_comment_form( $args = array(), $post = null ) {
		$defaults = array(
			'logged_in_as'       => static::render_logged_in_as(),
			'title_reply'        => '',
			'title_reply_to'     => 'Reply to %s',
			'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h2>',
			'label_submit'       => 'Reply',
			'format'             => 'html5',
		);
		$args     = wp_parse_args( $args, $defaults );
		wp_enqueue_style( 'rh-comment-form' );
		ob_start();
		comment_form( $args, $post );
		return ob_get_clean();
	}

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
}
RH_Comments::get_instance();
