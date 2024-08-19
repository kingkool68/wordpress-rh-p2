<?php
use PHPHtmlParser\Dom;
use PHPHtmlParser\Options;
/**
 * Suck down comments from a tasty morsel of markup
 */
class RH_P2_Comment_Importer {

	/**
	 * The nonce value to use
	 *
	 * @var string
	 */
	public static $nonce = 'rh-p2-comment-importer';

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
		add_action( 'add_meta_boxes', array( $this, 'action_add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ), 10, 2 );
	}

	/**
	 * Hook in to WordPress via filters
	 */
	public function setup_filters() {}

	/**
	 * Add meta box to paste comment markup in
	 */
	public function action_add_meta_boxes() {
		add_meta_box( 'rh-p2-comment-importer', 'P2 Comment Importer', array( $this, 'render_meta_box' ), 'post' );
	}

	/**
	 * Render the metabox for comment importing
	 *
	 * @param  WP_Post $post The WordPress post to render the metabox for
	 */
	public function render_meta_box( $post = null ) {
		$context = array(
			'the_nonce' => wp_create_nonce( static::$nonce ),
		);
		Sprig::out( 'p2-comment-importer-metabox.twig', $context );
	}

	/**
	 * Parse the comments from the provided markup
	 *
	 * @param  integer $post_id The WordPress post_id being saved
	 */
	public function action_save_post( $post_id = 0 ) {
		// If WordPress is doing an autosave then abort
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $post_id;
		}

		// If the nonce isn't set or can't be verified then abort
		if (
		empty( $_REQUEST['rh-p2-comment-importer-nonce'] ) ||
		! wp_verify_nonce( wp_unslash( $_REQUEST['rh-p2-comment-importer-nonce'] ), static::$nonce )
		) {
			return $post_id;
		}

		if ( empty( $_REQUEST['rh-p2-comment-importer'] ) ) {
			return $post_id;
		}
		$comment_markup   = wp_unslash( $_REQUEST['rh-p2-comment-importer'] );
		$comments         = static::parse_comments( $post_id, $comment_markup );
		$comments         = wp_list_sort( $comments, 'comment_date_gmt', 'ASC' );
		$old_id_to_new_id = array();
		foreach ( $comments as $comment ) {
			$old_id    = $comment->comment_id;
			$parent_id = $comment->comment_parent;
			if ( $parent_id > 0 ) {
				if ( ! empty( $old_id_to_new_id[ $parent_id ] ) ) {
					$comment->comment_parent = $old_id_to_new_id[ $parent_id ];
				}
			}
			$new_id                      = wp_insert_comment( (array) $comment );
			$old_id_to_new_id[ $old_id ] = $new_id;
		}
	}

	/**
	 * Parse comment data from given markup of the edit-comments.php?p=123 admin page
	 *
	 * @param  integer $post_id The post_id that should be associated with the parsed comments
	 * @param  string  $markup The markup that contains all of the comment info
	 */
	public static function parse_comments( $post_id = 0, $markup = '' ) {
		$post = get_post( $post_id );

		$comments = array();

		// We need to preserve line breaks to help with comment content formatting
		$dom_options = new Options();
		$dom_options->setPreserveLineBreaks( true );

		$dom = new Dom();
		$dom->setOptions( $dom_options );
		$dom->loadStr( $markup );
		$rows = $dom->find( 'tr.comment' );
		foreach ( $rows as $row ) {
			$comment_data = array(
				'comment_id'           => 0,
				'comment_post_ID'      => $post->ID,
				'comment_content'      => '',
				'comment_parent'       => 0,
				'comment_author'       => '',
				'comment_author_IP'    => '',
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_date'         => '',
				'comment_date_gmt'     => '',
			);

			$comment_id                 = $row->getAttribute( 'id' );
			$comment_id                 = str_replace( 'comment-', '', $comment_id );
			$comment_id                 = absint( $comment_id );
			$comment_data['comment_id'] = $comment_id;

			$author_markup = $row->find( 'td.author', 0 );
			$author_html   = $author_markup->innerHtml; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i', $author_html, $match );
			if ( ! empty( $match[0] ) ) {
				$comment_data['comment_author_IP'] = $match[0];
			}

			$comment_content = $row->find( 'textarea.comment', 0 )->innerHtml;
			$comment_content = html_entity_decode( $comment_content );
			// Strip HTML comments
			$comment_content                 = preg_replace( '/<!--(.|\s)*?-->/', '', $comment_content );
			$comment_content                 = trim( $comment_content );
			$comment_data['comment_content'] = $comment_content;

			$comment_data['comment_author_email'] = $row->find( 'div.author-email', 0 )->text;
			$comment_data['comment_author']       = $row->find( 'div.author', 0 )->text;
			$comment_data['comment_author_url']   = $row->find( 'div.author-url', 0 )->text;

			// Comment date
			$comment_date = $row->find( 'div.submitted-on a', 0 )->text;
			$the_timezone = new DateTimeZone( 'America/Los_Angeles' );
			$the_datetime = DateTime::createFromFormat( 'Y/m/d \a\t g:i a', $comment_date, $the_timezone );
			$the_datetime->setTimezone( new DateTimeZone( 'GMT' ) );
			$comment_data['comment_date_gmt'] = $the_datetime->format( 'Y-m-d H:i:s' );
			$comment_data['comment_date']     = get_date_from_gmt( $comment_data['comment_date_gmt'] );

			// Comment parent
			$comment_links = $row->find( 'td.column-primary > a' );
			if ( $comment_links ) {
				foreach ( $comment_links as $link ) {
					$reply_to_url = $link->getAttribute( 'href' );
					if ( ! empty( $reply_to_url ) ) {
						$pieces                         = explode( '#comment-', $reply_to_url );
						$parent_comment_id              = $pieces[1];
						$parent_comment_id              = absint( $parent_comment_id );
						$comment_data['comment_parent'] = $parent_comment_id;
					}
				}
			}
			$comments[] = (object) $comment_data;
		}
		return $comments;
	}
}
RH_P2_Comment_Importer::get_instance();
