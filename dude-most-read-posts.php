<?php
/**
 * Plugin Name: Most read posts
 * Plugin URI: https://github.com/digitoimistodude/dude-most-read-posts
 * Description: A developer-friendly plugin to count post reads and list most read content.
 * Version: 1.0.0
 * Author: Digitoimisto Dude Oy, Timi Wahalahti
 * Author URI: https://www.dude.fi
 * Requires at least: 4.6
 * Tested up to: 4.7.2
 *
 * Text Domain: dude-most-read-posts
 * Domain Path: /languages
 */

if( !defined( 'ABSPATH' )  )
	exit();

/**
 * Base for the plugin.
 */
class Dude_Most_Read_Posts {

	private $instance = null;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'dude-most-read-posts';
		$this->version = '1.0.0';

		$this->run();
	} // end __construct

	/**
	 * Start the magic from here.
	 *
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	public function run() {
		$this->set_hooks();
	} // end run

	/**
	 * Set hooks that makes thing rock.
	 *
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	private function set_hooks() {
		load_plugin_textdomain( 'dude-most-read-posts', false, dirname( dirname( plugin_basename( __FILE__ ) ) ).'/languages/' );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_dmrp_count', array( $this, 'update_read_count' ) );
		add_action( 'wp_ajax_nopriv_dmrp_count', array( $this, 'update_read_count' ) );
	} // end set_hooks

	/**
	 * Enqueue and localize javascript that calls the hit counter.
	 *
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	public function enqueue_scripts() {
		/**
		 * We don't need this plugin in admin page, except ajax call is made
		 */
		if( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) )
			return;

		/**
		 * Do not count hits made by logged in users. This behavior can be changed
		 * with filter returning false
		 */
		if( apply_filters( 'dmrp_dont_count_logged_in_users', is_user_logged_in() ) )
			return;

		/**
		 * Do not count hits made by users with a certain capability. This
		 * behavior can be changed with filter returning a capability.
		 *
		 * @see https://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table
		 */
		if ( current_user_can( apply_filters( 'dmrp_dont_count_for_capability', '__return_empty_string' ) ) )
			return;

		/**
		 * Do not count hits to these post types, by default count for all. This
		 * behavior can be cahnged with filter
		 */
		if( !is_singular( apply_filters( 'dmrp_count_for_post_types', array( 'post' ) ) ) )
			return;

		/**
		 * Enqueue and localize our javascript
		 */
		wp_enqueue_script( 'dmrp', plugin_dir_url( __FILE__ ).'public/js/script.min.js', array( 'jquery' ), $this->version, true );
		wp_localize_script( 'dmrp', 'dmrp', array(
			'id'							=> get_the_id(),
			'nonce'						=>  wp_create_nonce( 'dmrp'.get_the_id() ),
			'ajax_url'				=> admin_url( 'admin-ajax.php' ),
			'cookie_timeout'	=> apply_filters( 'dmrp_cookie_timeout', 3600000 ),
		) );
	} // end register_scripts

	/**
	 * Javascript calls this function with ajax to add hit for post. By default,
	 * hits are counted only for total of all time.
	 *
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	public function update_read_count() {
		$id = sanitize_text_field( $_POST['id'] );

		check_ajax_referer( 'dmrp'.$id, 'nonce' );

		if( !$this->post_exists( $id ) )
			wp_send_json_error();

		$this->update_count( $id, '_dmrp_count' );

		if( apply_filters( 'dmrp_count_week', false ) )
			$this->update_count( $id, '_dmrp_count_week_'.date( 'W-Y' ) );

		if( apply_filters( 'dmrp_count_month', false ) )
			$this->update_count( $id, '_dmrp_count_month_'.date( 'm-Y' ) );

		if( apply_filters( 'dmrp_count_year', false ) )
			$this->update_count( $id, '_dmrp_count_year_'.date( 'Y' ) );

		wp_send_json_success();
	} // end update_read_count

	/**
	 * Do the actual hit count increase.
	 *
	 * @param   integer   $id  post to update
	 * @param   string    $key meta key to update
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	private function update_count( $id, $key ) {
		$count = get_post_meta( $id, $key, true );

		if( false === $count )
			$count = 0;

		$count++;
		update_post_meta( $id, $key, $count );
	} // end update_count

	/**
	 * Helper function for checking if post with id really exists
	 *
	 * @param   integer   $id post to check
	 * @return  boolean				true if post exists, otherwise false
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	private function post_exists( $id ) {
	  return is_string( get_post_status( $id ) );
	} // end post_exists

	/**
	 * Get most popular posts
	 *
	 * @param   string    $period 		all time, year, month or weeks most read
	 * @param   array     $args   		arguments for period
	 * @param		array 		$query_args	arguments for wp_query
	 * @return  mixed     						boolean false if errors, otherwise wp_query
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	public function get_most_popular( $period = null, $args = array(), $query_args = array() ) {
		switch ( $period ) {
			case 'year':
				if( !apply_filters( 'dmrp_count_year', false ) )
					return false;

				$key = ( array_key_exists( 'year', $args ) ) ? $args['year'] : date( 'Y' );
				$key = '_dmrp_count_year_'.$key;
				break;

			case 'month':
				if( !apply_filters( 'dmrp_count_month', false ) )
					return false;

				$key = ( array_key_exists( 'month', $args ) ) ? $args['month'] : date( 'm' );
				$key .= ( array_key_exists( 'year', $args ) ) ? '-'.$args['year'] : date( '-Y' );
				$key = '_dmrp_count_month_'.$key;
				break;

			case 'week':
				if( !apply_filters( 'dmrp_count_week', false ) )
					return false;

				$key = ( array_key_exists( 'week', $args ) ) ? $args['week'] : date( 'W' );
				$key .= ( array_key_exists( 'year', $args ) ) ? '-'.$args['year'] : date( '-Y' );
				$key = '_dmrp_count_week_'.$key;
				break;

			default:
				$key = '_dmrp_count';
				break;
		}

		$args = wp_parse_args( $query_args, array(
			'post_type'								=> 'post',
			'posts_per_page'					=> 5,
			'post_status'							=> 'publish',
			'no_found_rows'						=> true,
			'update_post_term_cache'	=> false,
		) );

		$args['orderby'] = 'meta_value_num';
		$args['meta_key'] = $key;

		return new WP_Query( $args );
	} // end get_most_popular

	/**
	 * Get only ids of most popular posts, basically wrapper for function
	 * get_most_popular.
	 *
	 * @param   string    $period 		all time, year, month or weeks most read
	 * @param   array     $args   		arguments for period
	 * @param		array 		$query_args	arguments for wp_query
	 * @return  mixed     						boolean false if errors, otherwise wp_query
	 * @since		0.1.0
	 * @version	0.1.0
	 */
	public function get_most_popular_ids( $period = null, $args = array(), $query_args = array() ) {
		$query_args['fields'] = 'ids';

		$query = self::get_most_popular( $period, $args, $query_args );
		if( $query )
			return $query->posts;

		return false;
	} // end get_most_popular_ids
} // end class

// Init the class
new Dude_Most_Read_Posts;

if( !function_exists( 'get_most_popular_posts' ) ) {
	function get_most_popular_posts( $period = null, $args = array(), $query_args = array() ) {
		return Dude_Most_Read_Posts::get_most_popular( $period, $args, $query_args );
	} // end get_most_popular_posts
}

if( !function_exists( 'get_most_popular_posts_ids' ) ) {
	function get_most_popular_posts_ids( $period = null, $args = array(), $query_args = array() ) {
		return Dude_Most_Read_Posts::get_most_popular_ids( $period, $args, $query_args );
	} // end get_most_popular_posts_ids
}
