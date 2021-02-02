<?php
/**
 * Plugin Name: Most read posts
 * Plugin URI: https://github.com/digitoimistodude/dude-most-read-posts
 * Description: A developer-friendly plugin to count post reads and list most read content.
 * Version: 2.2.2
 * Author: Digitoimisto Dude Oy, Timi Wahalahti
 * Author URI: https://www.dude.fi
 * Requires at least: 4.6
 * Tested up to: 5.6
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
	protected $db_version;

	public function __construct() {
		$this->plugin_name = 'dude-most-read-posts';
		$this->version = '2.2.2';
		$this->db_version = 1;

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
		register_activation_hook( __FILE__, array( $this, 'maybe_do_db' ) );

		load_plugin_textdomain( 'dude-most-read-posts', false, dirname( dirname( plugin_basename( __FILE__ ) ) ).'/languages/' );
		add_action( 'plugins_loaded',  array( $this, 'maybe_do_db' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_dmrp_count', array( $this, 'update_read_count' ) );
		add_action( 'wp_ajax_nopriv_dmrp_count', array( $this, 'update_read_count' ) );
	} // end set_hooks

	public function maybe_do_db() {
		$installed_db_version = get_option( 'dmrp_db_version' );

		if ( empty( $installed_db_version ) ) {
			self::install_database();
		} elseif ( $installed_db_version < $this->db_version ) {
			self::install_database();
		}
	}

	public function install_database() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dude_most_read_posts';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) DEFAULT '0' NOT NULL,
			time date DEFAULT '0000-00-00' NOT NULL,
			count bigint(20) DEFAULT '0' NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		add_option( 'dmrp_db_version', $this->db_version );

		add_action( 'init', array( $this, 'migrate_old_counts_to_table' ) );
	} // end function install_database

	public function migrate_old_counts_to_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dude_most_read_posts';

		// get all posts which have old count
		$query = new WP_Query( array(
			'post_type'		=> apply_filters( 'dmrp_count_for_post_types', array( 'post' ) ),
			'post_status'		=> 'publish',
			'posts_per_page'	=> -1,
			'meta_query'		=> array(
				array(
					'key'	=> '_dmrp_count',
				),
				array(
					'key'		=> '_dmrp_migrated',
					'compare'	=> 'NOT EXISTS',
				),
			),
			'no_found_rows'			=> true,
			'cache_results'			=> false,
			'update_post_term_cache'	=> false,
			'update_post_meta_cache'	=> true,
		) );

		// loop posts
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$old_count = get_post_meta( get_the_id(), '_dmrp_count', true );

				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE post_id = %s AND time = %s", get_the_id(), date( 'Y-m-d' ) ) );

				if ( is_null( $row ) ) {
					$wpdb->insert( $table_name, array(
						'post_id'	=> get_the_id(),
						'time'		=> date( 'Y-m-d' ),
						'count'		=> $old_count,
					) );
				} else {
					$count = intval( $row->count );
					$wpdb->update( $table_name, array(
						'count'		=> $count + $old_count,
					), array(
						'post_id'	=> get_the_id(),
						'time'		=> date( 'Y-m-d' ),
					) );
				}

				update_post_meta( get_the_id(), '_dmrp_migrated', 'yes' );
			} // end while
		} // end if

		wp_reset_postdata();
	} // end migrate_old_counts_to_table

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
		if( ! is_singular( apply_filters( 'dmrp_count_for_post_types', array( 'post' ) ) ) )
			return;

		/**
		 * Enqueue and localize our javascript
		 */
		wp_enqueue_script( 'dmrp', plugin_dir_url( __FILE__ ) . 'public/js/script.min.js', array( 'jquery' ), $this->version, true );
		wp_localize_script( 'dmrp', 'dmrp', array(
			'id'			=> get_the_id(),
			'nonce'			=>  wp_create_nonce( 'dmrp' . get_the_id() ),
			'ajax_url'		=> admin_url( 'admin-ajax.php' ),
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
		global $wpdb;

		$id = sanitize_text_field( $_POST['id'] );

		check_ajax_referer( 'dmrp' . $id, 'nonce' );

		if( ! $this->post_exists( $id ) )
			wp_send_json_error();

		$table_name = $wpdb->prefix . 'dude_most_read_posts';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE post_id = %s AND time = %s", $id, date( 'Y-m-d' ) ) );

		if ( is_null( $row ) ) {
			$wpdb->insert( $table_name, array(
				'post_id'	=> $id,
				'time'		=> date( 'Y-m-d' ),
				'count'		=> 1,
			) );
		} else {
			$count = intval( $row->count );
			$wpdb->update( $table_name, array(
				'count'		=> ++$count,
			), array(
				'post_id'	=> $id,
				'time'		=> date( 'Y-m-d' ),
			) );
		}

		wp_send_json_success();
	} // end update_read_count

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
	 * @param   string    $period 			all time, year, month, weeks or custom period most read
	 * @param		array 		$query_args		arguments for wp_query
	 * @param   string 		$custom_start start timestamp (Y-m-d) for custom period
	 * @param   string 		$custom_end 	end timestamp (Y-m-d) for custom period
	 * @return  mixed     							boolean false if errors, otherwise wp_query
	 * @since   0.1.0
	 * @version 0.1.0
	 */
	public static function get_most_popular( $period = null, $query_args = array(), $custom_start = null, $custom_end = null ) {
		$popular_posts = self::get_most_popular_ids( $period, true, $custom_start, $custom_end );

		if ( ! $popular_posts ) {
			return false;
		}

		$args = wp_parse_args( $query_args, array(
			'post_type'			=> apply_filters( 'dmrp_count_for_post_types', array( 'post' ) ),
			'posts_per_page'		=> 5,
			'post_status'			=> 'publish',
			'ignore_sticky_posts'		=> true,
			'no_found_rows'			=> true,
			'update_post_term_cache'	=> false,
		) );

		$args['post__in'] = $popular_posts;
		$args['orderby'] = 'post__in';

		return new WP_Query( $args );
	} // end get_most_popular

	/**
	 * Get only ids of most popular posts, basically wrapper for function
	 * get_most_popular.
	 *
	 * @param   string    $period 			all time, year, month or weeks most read
	 * @param   boolen 		$only_ids 		should we return only post ids, not read counts also
	 * @param   string 		$custom_start start timestamp (Y-m-d) for custom period
	 * @param   string 		$custom_end 	end timestamp (Y-m-d) for custom period
	 * @return  mixed     							boolean false if errors, otherwise wp_query
	 * @since		0.1.0
	 * @version	0.1.0
	 */
	public static function get_most_popular_ids( $period = null, $only_ids = true, $custom_start = null, $custom_end = null ) {
		global $wpdb;

		switch ( $period ) {
			case 'custom':
				$start_date = $custom_start;
				$end_date = $custom_end;
				break;

			case 'year':
				$start_date = date( 'Y-m-d', strtotime( '-1 year' ) );
				$end_date = date( 'Y-m-d' );
				break;

			case 'month':
				$start_date = date( 'Y-m-d', strtotime( '-1 month' ) );
				$end_date = date( 'Y-m-d' );
				break;

			case 'week':
				$start_date = date( 'Y-m-d', strtotime( '-1 week' ) );
				$end_date = date( 'Y-m-d' );
				break;

			default:
				$start_date = date( 'Y-m-d', strtotime( '-100 year' ) );
				$end_date = date( 'Y-m-d' );
				break;
		}

		$table_name = $wpdb->prefix . 'dude_most_read_posts';
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, SUM(count) AS count FROM {$table_name} WHERE time between %s and %s GROUP BY post_id ORDER BY count DESC", $start_date, $end_date ), ARRAY_A );

		if ( is_null( $result ) ) {
			return false;
		}

		$return = array();
		foreach ( $result as $row ) {
			if ( $only_ids ) {
				$return[] = $row['post_id'];
			} else {
				$return[ $row['post_id'] ] = $row['count'];
			}
		}

		return $return;
	} // end get_most_popular_ids

	/**
	 * Get read count for spesific post.
	 *
	 * @param   integer   $post_id			for which post to get count
	 * @param   string    $period 			all time, year, month or weeks most read
	 * @param   string 		$custom_start start timestamp (Y-m-d) for custom period
	 * @param   string 		$custom_end 	end timestamp (Y-m-d) for custom period
	 * @return  mixed     							boolean false if errors, otherwise read count
	 * @since		2.1.0
	 * @version	0.1.0
	 */
	public static function get_read_count_for_id( $post_id = 0, $period = null, $custom_start = null, $custom_end = null ) {
		global $wpdb;

		if ( empty( $post_id ) ) {
			return false;
		}

		switch ( $period ) {
			case 'custom':
				$start_date = $custom_start;
				$end_date = $custom_end;
				break;

			case 'year':
				$start_date = date( 'Y-m-d', strtotime( '-1 year' ) );
				$end_date = date( 'Y-m-d' );
				break;

			case 'month':
				$start_date = date( 'Y-m-d', strtotime( '-1 month' ) );
				$end_date = date( 'Y-m-d' );
				break;

			case 'week':
				$start_date = date( 'Y-m-d', strtotime( '-1 week' ) );
				$end_date = date( 'Y-m-d' );
				break;

			default:
				$start_date = date( 'Y-m-d', strtotime( '-100 year' ) );
				$end_date = date( 'Y-m-d' );
				break;
		}

		$table_name = $wpdb->prefix . 'dude_most_read_posts';
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, SUM(count) AS count FROM {$table_name} WHERE time between %s and %s AND post_id = %s GROUP BY post_id", $start_date, $end_date, $post_id ), ARRAY_A );

		if ( is_null( $result ) ) {
			return false;
		}

		if ( ! isset( $result[0] ) ) {
			return false;
		}

		if ( ! isset( $result[0]['count'] ) ) {
			return false;
		}

		return (int) $result[0]['count'];
	} // end get_read_count_for_id
} // end class

// Init the class
new Dude_Most_Read_Posts;

if( !function_exists( 'get_most_popular_posts' ) ) {
	function get_most_popular_posts( $period = null, $query_args = array(), $custom_start = null, $custom_end = null ) {
		return Dude_Most_Read_Posts::get_most_popular( $period, $query_args, $custom_start, $custom_end );
	} // end get_most_popular_posts
}

if( !function_exists( 'get_most_popular_posts_ids' ) ) {
	function get_most_popular_posts_ids( $period = null, $only_ids = true, $custom_start = null, $custom_end = null ) {
		return Dude_Most_Read_Posts::get_most_popular_ids( $period, $only_ids, $custom_start, $custom_end );
	} // end get_most_popular_posts_ids
}

if( !function_exists( 'get_post_read_count' ) ) {
	function get_post_read_count( $post_id = 0, $period = null, $custom_start = null, $custom_end = null ) {
		return Dude_Most_Read_Posts::get_read_count_for_id( $post_id, $period, $custom_start, $custom_end );
	}
}
