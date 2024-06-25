<?php
use PHPHtmlParser\Dom;
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

	public function action_add_meta_boxes() {
		add_meta_box( 'rh-p2-comment-importer', 'P2 Comment Importer', array( $this, 'render_meta_box' ), 'post' );
	}

	public function render_meta_box( $post = null ) {
		$context = array(
			'the_nonce' => wp_create_nonce( static::$nonce ),
		);
		Sprig::out( 'p2-comment-importer-metabox.twig', $context );
	}

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
		! wp_verify_nonce( $_REQUEST['rh-p2-comment-importer-nonce'], static::$nonce )
		) {
			return $post_id;
		}

		if ( empty( $_REQUEST['rh-p2-comment-importer'] ) ) {
			return $post_id;
		}
		$comment_markup = wp_unslash( $_REQUEST['rh-p2-comment-importer'] );
		static::parse_and_save_comment( $post_id, $comment_markup );
	}

	public static function parse_and_save_comment( $post_id = 0, $markup = '' ) {
		$post = get_post( $post_id );
		$dom  = new Dom();
		$dom->loadStr( $markup );
		$rows = $dom->find( 'tr.comment' );
		foreach ( $rows as $row ) {
			$comment_data = array(
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

			$comment_id = $row->getAttribute('id');
			$comment_id = str_replace( 'comment-', '', $comment_id );
			$comment_id = absint( $comment_id );
			var_dump( $comment_id );

			$author_markup = $row->find( 'td.author', 0 );
			$author_html   = $author_markup->innerHtml;
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
			var_dump( $comment_data );
		}
	}

	// if ( comments_open( $postId ) ) {
	// $data = array(
	// 'comment_post_ID'      => $postId,
	// 'comment_content'      => $field['comment'],
	// 'comment_parent'       => $field['comment_parent'],
	// 'user_id'              => $current_user->ID,
	// 'comment_author'       => $current_user->user_login,
	// 'comment_author_email' => $current_user->user_email,
	// 'comment_author_url'   => $current_user->user_url,
	// 'comment_meta'         => array(
	// 'property_district' => $field['property_district'],
	// 'property_estate'   => $field['property_estate'],
	// 'custom_tag'        => $field['custom_tag'],
	// ),
	// );

	// $comment_id = wp_insert_comment( $data );
	// if ( ! is_wp_error( $comment_id ) ) {
	// return $comment_id;
	// }
	// }
}
RH_P2_Comment_Importer::get_instance();
