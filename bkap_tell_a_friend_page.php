<?php
/**
 * Tell a Friend Page.
 *
 * @package Send a Friend Addon.
 */

if ( ! class_exists( 'bkap_tell_a_friend_page' ) ) {

	/**
	 * Tell a Friend Class.
	 */
	class bkap_tell_a_friend_page {
		/**
		 * Slug.
		 *
		 * @var $slug - Page Slug.
		 */
		private $slug = null;
		/**
		 * Title.
		 *
		 * @var $title - Page Title.
		 */
		private $title = null;
		/**
		 * Content.
		 *
		 * @var $content - Page Content.
		 */
		private $content = null;
		/**
		 * Author.
		 *
		 * @var $author - Page Author.
		 */
		private $author = null;
		/**
		 * Date.
		 *
		 * @var $date.
		 */
		private $date = null;
		/**
		 * Type.
		 *
		 * @var $type.
		 */
		private $type = null;

		/**
		 * Construct.
		 *
		 * @param array $args - Arguments.
		 * @throws Exception - Throws an error if no slug found.
		 */
		public function __construct( $args ) {
			if ( ! isset( $args['slug'] ) ) {
				throw new Exception( 'No slug given for page' );
			}

			$this->slug    = $args['slug'];
			$this->title   = isset( $args['title'] ) ? $args['title'] : '';
			$this->content = isset( $args['content'] ) ? $args['content'] : '';
			$this->author  = isset( $args['author'] ) ? $args['author'] : 1;
			$this->date    = isset( $args['date'] ) ? $args['date'] : current_time( 'mysql' );
			$this->dategmt = isset( $args['date'] ) ? $args['date'] : current_time( 'mysql', 1 );
			$this->type    = isset( $args['type'] ) ? $args['type'] : 'page';

			add_filter( 'the_posts', array( &$this, 'create_virtual_page' ) );
		}

		/**
		 * Filter to create virtual page content for Tell a Friend page.
		 *
		 * @param object $posts - Posts.
		 */
		public function create_virtual_page( $posts ) {
			global $wp, $wp_query;

			$url                  = '';
			$tell_friend_page_url = get_option( 'bkap_friend_tell_friend_page_url', '' );
			if ( ( isset( $tell_friend_page_url ) && '' === $tell_friend_page_url ) || ! isset( $tell_friend_page_url ) ) {
				$tell_friend_page_url = 'send-booking-to-friend';
			}

			$page_url_setting = '/' . trim( $tell_friend_page_url ) . '/';
			if ( preg_match( $page_url_setting, $wp->request ) ) {
				$url = 'send-booking-to-friend';
			}
			if ( '' !== $url && ( 0 == strcasecmp( $url, $this->slug ) || $wp->query_vars['page_id'] == $this->slug ) ) { //phpcs:ignore

				// Create a fake post intance.
				$post = new stdClass();
				// Fill properties of $post with everything a page in the database would have.
				$post->ID                    = -1; // use an illegal value for page ID.
				$post->post_author           = $this->author; // post author id.
				$post->post_date             = $this->date; // date of post.
				$post->post_date_gmt         = $this->dategmt;
				$post->post_content          = $this->content;
				$post->post_title            = $this->title;
				$post->post_excerpt          = '';
				$post->post_status           = 'publish';
				$post->comment_status        = 'closed'; // mark as closed for comments, since page doesn't exist.
				$post->ping_status           = 'closed'; // mark as closed for pings, since page doesn't exist.
				$post->post_password         = ''; // no password.
				$post->post_name             = $this->slug;
				$post->to_ping               = '';
				$post->pinged                = '';
				$post->modified              = $post->post_date;
				$post->modified_gmt          = $post->post_date_gmt;
				$post->post_parent           = 0;
				$post->guid                  = get_home_url( '/' . $this->slug );
				$post->menu_order            = 0;
				$post->post_tyle             = $this->type;
				$post->post_mime_type        = '';
				$post->comment_count         = 0;
				$post->post_content_filtered = '';

				// Set filter results.
				$posts = array( $post );

				// Reset wp_query properties to simulate a found page.
				$wp_query->is_page     = true;
				$wp_query->is_singular = true;
				$wp_query->is_home     = false;
				$wp_query->is_archive  = false;
				$wp_query->is_category = false;

				unset( $wp_query->query['error'] );
				$wp_query->query_vars['error'] = '';
				$wp_query->is_404              = false;
			}

			return ( $posts );
		}
	}
}
