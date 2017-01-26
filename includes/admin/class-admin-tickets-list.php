<?php

	/**
	 * Admin Tickets List.
	 *
	 * @package   Admin/Tickets List
	 * @author    Julien Liabeuf <julien@liabeuf.fr>
	 * @license   GPL-2.0+
	 * @link      http://themeavenue.net
	 * @copyright 2014 ThemeAvenue
	 */
	class WPAS_Tickets_List {

		/**
		 * Instance of this class.
		 *
		 * @since    1.0.0
		 * @var      object
		 */
		protected static $instance = null;

		public function __construct() {

			/**
			 * Add custom columns
			 */
			add_action( 'manage_ticket_posts_columns', array( $this, 'add_custom_columns' ), 10, 1 );
			add_action( 'manage_ticket_posts_columns', array( $this, 'move_status_first' ), 15, 1 );
			add_action( 'manage_ticket_posts_custom_column', array( $this, 'core_custom_columns_content' ), 10, 2 );
			add_filter( 'manage_edit-ticket_sortable_columns', array( $this, 'custom_columns_sortable' ), 10, 1 );

			/**
			 * Add the taxonomies filters
			 */
			add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ), 8, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'custom_taxonomy_filter' ), 10, 2 );
			add_filter( 'parse_query', array( $this, 'custom_taxonomy_filter_convert_id_term' ), 10, 1 );
			add_filter( 'parse_query', array( $this, 'test' ), 11, 1 );

			add_action( 'admin_menu', array( $this, 'hide_closed_tickets' ), 10, 0 );
			add_filter( 'the_title', array( $this, 'add_ticket_id_title' ) );
			add_filter( 'the_excerpt', array( $this, 'remove_excerpt' ), 10, 1 );
			add_filter( 'post_row_actions', array( $this, 'remove_quick_edit' ), 10, 2 );
			add_filter( 'post_class', array( $this, 'ticket_row_class' ), 10, 3 );

			add_action( 'pre_get_posts', array( $this, 'set_ordering_query_var' ), 10, 1 );
			add_filter( 'posts_results', array( $this, 'apply_ordering_criteria' ), 10, 2 );
			add_filter( 'posts_clauses', array( $this, 'wpas_post_clauses' ), 10, 2 );
			add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
			//add_filter( 'posts_where', array( $this, 'custom_search_where' ), 999, 1 );
			add_action( 'parse_request', array( $this, 'parse_request' ), 10, 1 );

			add_filter( 'wpas_add_custom_fields', array( $this, 'add_custom_fields' ) );

			add_filter( 'manage_posts_extra_tablenav', array( $this, 'manage_posts_extra_tablenav' ), 10, 1 );

			//add_action( 'in_admin_header', array( $this, 'in_admin_header' ) );
			//add_action( 'admin_head', array( $this, 'register_my_option' ) );

		}


		public function test( $wp_query ) {

			global $pagenow;

			/* Check if we are in the correct post type */
			if ( ! is_admin()
				|| 'edit.php' !== $pagenow
				|| ! isset( $_GET[ 'post_type' ] )
				|| 'ticket' !== $_GET[ 'post_type' ]
				|| ! $wp_query->is_main_query()
			) {
			    return;
			}

			if ( isset( $_GET[ 'assignee' ] ) && !empty( $_GET[ 'assignee' ] ) ) {

				$staff_id = (int)$_GET[ 'assignee' ];
				$agent = new WPAS_Member_Agent( $staff_id );

				if ( $agent->is_agent() ) {

					$meta_query[] = array(
						'key'     => '_wpas_assignee',
						'value'   => $staff_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					);
				}

				if ( !isset( $meta_query[ 'relation' ] ) ) {
					$meta_query[ 'relation' ] = 'AND';
				}

//					$wp_query->set( 'meta_query', $meta_query );
			}


			if ( isset( $_GET[ 'id' ] ) && !empty( $_GET[ 'id' ] ) ) {
				$wp_query->query_vars[ 'id' ] = (int)$_GET[ 'id' ];
			}


			if ( isset( $_GET[ 'author' ] ) && !empty( $_GET[ 'author' ] ) ) {
				//$query->query_vars[ 'author' ] = (int)$_GET[ 'author' ];
				//$query->set( 'orderby',    'date' );
				//$query->set( 'order',      'DESC' );
			}


			if ( isset( $_GET[ 'status' ] ) && !empty( $_GET[ 'status' ] ) ) {

				//if ( 'any' !== $_GET[ 'status' ] ) {

				//$meta_query = $wp_query->get( 'meta_query' );

				//if ( !is_array( $meta_query ) ) {
				//	$meta_query = (array)$meta_query;
				//}

				if ( 'open' === $_GET[ 'status' ] || 'any' === $_GET[ 'status' ] ) {
					$meta_query[] = array(
						'key'     => '_wpas_status',
						'value'   => 'open',
						'compare' => '=',
						'type'    => 'CHAR',
					);

				}
				if ( 'closed' === $_GET[ 'status' ] || 'any' === $_GET[ 'status' ] ) {
					$meta_query[] = array(
						'key'     => '_wpas_status',
						'value'   => 'closed',
						'compare' => '=',
						'type'    => 'CHAR',
					);

				}

				if ( 'any' === $_GET[ 'status' ] ) {
					$meta_query[ 'relation' ] = 'OR';
				} elseif ( !isset( $meta_query[ 'relation' ] ) ) {
					$meta_query[ 'relation' ] = 'AND';
				}

				//$wp_query->set( 'meta_query', $meta_query );

			}
			//if ( isset( $_GET[ 'waiting_reply' ] ) && 'all' !== $_GET[ 'waiting_reply' ] ) {
			//	$post_id = $_GET[ 'waiting_reply' ];
			//$where .= " AND {$wpdb->posts}.ID = " . $post_id; //$wp_query->get( 'id' );
			//}


			$wp_query->set( 'meta_query', $meta_query );

		}


		public function posts_where( $where, $wp_query ) {

			if ( !is_admin() || 'ticket' !== $wp_query->query[ 'post_type' ] ) {
				return $where;
			}

			global $wpdb;

			$meta_query = $wp_query->get( 'meta_query' );

			if ( !is_array( $meta_query ) ) {
				$meta_query = (array)$meta_query;
			}


			if ( isset( $_GET[ 'id' ] ) && !empty( $_GET[ 'id' ] ) && intval( $_GET[ 'id' ] ) != 0 ) {
				$post_id = intval( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_STRING ) );
				$where .= " AND {$wpdb->posts}.ID = " . $post_id; //$wp_query->get( 'id' );
			}

return $where;


			if ( isset( $_GET[ 'assignee' ] ) && !empty( $_GET[ 'assignee' ] ) ) {

				$staff_id = (int)$_GET[ 'assignee' ];
				$agent = new WPAS_Member_Agent( $staff_id );

				if ( $agent->is_agent() ) {

					$meta_query[] = array(
						'key'     => '_wpas_assignee',
						'value'   => $staff_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					);
				}

				if ( !isset( $meta_query[ 'relation' ] ) ) {
					$meta_query[ 'relation' ] = 'AND';
				}

//					$wp_query->set( 'meta_query', $meta_query );
			}


			if ( isset( $_GET[ 'id' ] ) && !empty( $_GET[ 'id' ] ) ) {
				$wp_query->query_vars[ 'id' ] = (int)$_GET[ 'id' ];
			}


			if ( isset( $_GET[ 'author' ] ) && !empty( $_GET[ 'author' ] ) ) {
				//$query->query_vars[ 'author' ] = (int)$_GET[ 'author' ];
				//$query->set( 'orderby',    'date' );
				//$query->set( 'order',      'DESC' );
			}


			if ( isset( $_GET[ 'status' ] ) && !empty( $_GET[ 'status' ] ) ) {

				//if ( 'any' !== $_GET[ 'status' ] ) {

				//$meta_query = $wp_query->get( 'meta_query' );

				//if ( !is_array( $meta_query ) ) {
				//	$meta_query = (array)$meta_query;
				//}

				if ( 'open' === $_GET[ 'status' ] || '' === $_GET[ 'status' ] ) {
					$meta_query[] = array(
						'key'     => '_wpas_status',
						'value'   => 'open',
						'compare' => '=',
						'type'    => 'CHAR',
					);

				}
				if ( 'closed' === $_GET[ 'status' ] || '' === $_GET[ 'status' ] ) {
					$meta_query[] = array(
						'key'     => '_wpas_status',
						'value'   => 'closed',
						'compare' => '=',
						'type'    => 'CHAR',
					);

				}

				if ( '' === $_GET[ 'status' ] ) {
					$meta_query[ 'relation' ] = 'OR';
				} elseif ( !isset( $meta_query[ 'relation' ] ) ) {
					$meta_query[ 'relation' ] = 'AND';
				}

				//$wp_query->set( 'meta_query', $meta_query );

			}
			//if ( isset( $_GET[ 'waiting_reply' ] ) && 'all' !== $_GET[ 'waiting_reply' ] ) {
			//	$post_id = $_GET[ 'waiting_reply' ];
			//$where .= " AND {$wpdb->posts}.ID = " . $post_id; //$wp_query->get( 'id' );
			//}


			$wp_query->set( 'meta_query', $meta_query );


			return $where;
		}


		/**
		 * Return an instance of this class.
		 *
		 * @since     3.0.0
		 * @return    object    A single instance of this class.
		 */
		public static function get_instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 *  Called by the 'pre_get_posts' filter hook this method sets
		 *  the following to true when for the admin ticket list page:
		 *
		 *        $wp_query->query_var['wpas_order_by_urgency']
		 *
		 *  Setting this to true will trigger modifications to the query that
		 *  will be made in the apply_ordering_criteria() function called by
		 *  the 'posts_clauses' filter hook.
		 *
		 * @since    3.3
		 *
		 * @param WP_Query $query
		 *
		 * @return void
		 */
		public function set_ordering_query_var( $query ) {

			global $pagenow;

			if ( !isset( $_GET[ 'post_type' ] )
				|| 'ticket' !== $_GET[ 'post_type' ]
				|| 'edit.php' !== $pagenow
				|| $query->query[ 'post_type' ] !== 'ticket'
				//|| 'ticket' !== $query->get( 'post_type' )
				|| !$query->is_main_query()
			) {
				return;
			}


			if ( !isset( $_GET[ 'orderby' ] ) ) {

				// Skip urgency ordering on trash page
				if ( ! isset( $_GET[ 'post_status' ] )
					|| isset( $_GET[ 'post_status' ] ) && 'trash' !== $_GET[ 'post_status' ]
				) {

					// Manual column sorting disables order by urgency
					if ( wpas_has_smart_tickets_order() ) {
						/**
						 * Inspect the current context and if appropriate specify a query_var to allow
						 * WP_Query to modify itself based on arguments passed to WP_Query.
						 */
						$query->set( 'wpas_order_by_urgency', true );
					}

				}

			}

			return;

		}

		/**
		 *  Called by the 'posts_clauses' filter hook this method
		 *  modifies WP_Query SQL for ticket post types when:
		 *
		 *        $wp_query->get('wpas_order_by_urgency') === true
		 *
		 *  The query var 'wpas_order_by_urgency' will be set in the
		 *  set_ordering_query_var() function called by the 'pre_get_posts'
		 *  action hook.
		 *
		 * @since    3.3
		 *
		 * @param WP_Post[] $posts
		 * @param WP_Query $query
		 *
		 * @return WP_Post[]
		 */
		public function apply_ordering_criteria( $posts, $query ) {

			if ( $query->get( 'wpas_order_by_urgency' ) ) {

				/**
				 * Hooks in WP_Query should never modify SQL based on context.
				 * Instead they should modify based on a query_var so they can
				 * be tested and side-effects are minimized.
				 */
				//AND '_wpas_status'=wpas_postmeta.meta_key AND 'open'=CAST(wpas_postmeta.meta_value AS CHAR)
				/**
				 * @var wpdb $wpdb
				 *
				 */
				global $wpdb;

				$sql = <<<SQL
SELECT 
	wpas_ticket.ID AS ticket_id,
	wpas_ticket.post_title AS ticket_title,
	wpas_reply.ID AS reply_id,
	wpas_reply.post_title AS reply_title,
	wpas_replies.reply_count AS reply_count,
	wpas_replies.latest_reply,
	wpas_ticket.post_author=wpas_reply.post_author AS client_replied_last
FROM 
	{$wpdb->posts} AS wpas_ticket 
	INNER JOIN {$wpdb->postmeta} AS wpas_postmeta ON wpas_ticket.ID=wpas_postmeta.post_id
	LEFT OUTER JOIN {$wpdb->posts} AS wpas_reply ON wpas_ticket.ID=wpas_reply.post_parent
	LEFT OUTER JOIN (
		SELECT
			post_parent AS ticket_id,
			COUNT(*) AS reply_count,
			MAX(post_date) AS latest_reply
		FROM
			{$wpdb->posts}
		WHERE 1=1
			AND 'ticket_reply' = post_type
		GROUP BY
			post_parent
	) wpas_replies ON wpas_replies.ticket_id=wpas_reply.post_parent AND wpas_replies.latest_reply=wpas_reply.post_date 
WHERE 1=1
	AND wpas_replies.latest_reply IS NOT NULL
	AND 'ticket_reply'=wpas_reply.post_type
ORDER BY
	wpas_replies.latest_reply ASC
SQL;

				$no_replies = $client_replies = $agent_replies = array();

				foreach ( $posts as $post ) {

					$no_replies[ $post->ID ] = $post;

				}

				/**
				 * The post order will be modified using the following logic:
				 *
				 *        Order    -    Ticket State
				 *        -----    -------------------------------------------
				 *         1st    -    No reply - older since request made
				 *         2nd    -    No reply - newer since request made
				 *         3rd    -    Reply - older response since client replied
				 *         4th    -    Reply - newer response since client replied
				 *         5th    -    Reply - newer response since agent replied
				 *         6th    -    Reply - older response since agent replied
				 */

				foreach ( $wpdb->get_results( $sql ) as $reply_post ) {

					if ( isset( $no_replies[ $reply_post->ticket_id ] ) ) {

						if ( $reply_post->client_replied_last ) {
							$client_replies[ $reply_post->ticket_id ] = $no_replies[ $reply_post->ticket_id ];
						} else {
							$agent_replies[ $reply_post->ticket_id ] = $no_replies[ $reply_post->ticket_id ];
						}

						unset( $no_replies[ $reply_post->ticket_id ] );

					}

				}

				$posts = array_values( $no_replies + $client_replies + array_reverse( $agent_replies, true ) );

			}

			return $posts;

		}

		/**
		 * Remove Quick Edit action
		 *
		 * @since   3.1.6
		 * @global  object $post
		 *
		 * @param   array $actions An array of row action links.
		 *
		 * @return  array               Updated array of row action links
		 */
		public function remove_quick_edit( $actions ) {
			global $post;

			if ( $post->post_type === 'ticket' ) {
				unset( $actions[ 'inline hide-if-no-js' ] );
			}

			return $actions;
		}

		/**
		 * Add age custom column.
		 *
		 * Add this column after the date.
		 *
		 * @since  3.0.0
		 * @param  array $columns List of default columns
		 * @return array          Updated list of columns
		 */
		public function add_custom_columns( $columns ) {

			$new = array();
			$custom = array();
			$fields = $this->get_custom_fields();

			/**
			 * Prepare all custom fields that are supposed to show up
			 * in the admin columns.
			 */
			foreach ( $fields as $field ) {

				/* If CF is a regular taxonomy we don't handle it, WordPress does */
				if ( 'taxonomy' == $field[ 'args' ][ 'field_type' ] && true === $field[ 'args' ][ 'taxo_std' ] ) {
					continue;
				}

				if ( true === $field[ 'args' ][ 'show_column' ] ) {
					$id = $field[ 'name' ];
					$title = apply_filters( 'wpas_custom_column_title', wpas_get_field_title( $field ), $field );
					$custom[ $id ] = $title;
				}

			}

			/**
			 * Parse the old columns and add the new ones.
			 */
			foreach ( $columns as $col_id => $col_label ) {

				// We add all our columns where the date was and move the date column to the end
				if ( 'date' === $col_id ) {

					//continue;

					//} else if ( 'status' === $col_id ) {

					$new[ 'status' ] = $col_label;

					$new[ 'title' ] = esc_html__( 'Title', 'awesome-support' );

					if ( array_key_exists( 'ticket_priority', $custom ) ) {
						$new[ 'ticket_priority' ] = esc_html__( 'Priority', 'awesome-support' );
					}

					$new[ 'id' ] = esc_html__( 'ID', 'awesome-support' );

					if ( array_key_exists( 'product', $custom ) ) {
						$new[ 'product' ] = esc_html__( 'Product', 'awesome-support' );
					}

					if ( array_key_exists( 'department', $custom ) ) {
						$new[ 'department' ] = esc_html__( 'Department', 'awesome-support' );
					}

					if ( array_key_exists( 'ticket_channel', $custom ) ) {
						$new[ 'ticket_channel' ] = esc_html__( 'Channel', 'awesome-support' );
					}

					if ( array_key_exists( 'ticket-tag', $custom ) ) {
						$new[ 'ticket-tag' ] = esc_html__( 'Tag', 'awesome-support' );
					}

					// Add the client column
					$new[ 'author' ] = esc_html__( 'Created By', 'awesome-support' );

					// If agents can see all tickets do nothing
					if (
						current_user_can( 'administrator' )
						&& true === boolval( wpas_get_option( 'admin_see_all' ) )
						|| current_user_can( 'edit_ticket' )
						&& !current_user_can( 'administrator' )
						&& true === boolval( wpas_get_option( 'agent_see_all' ) )
					) {
						$new[ 'assignee' ] = esc_html__( 'Agent', 'awesome-support' );
					}

					// Add the date
					$new[ 'date' ] = $columns[ 'date' ];

					$new[ 'wpas-activity' ] = esc_html__( 'Activity', 'awesome-support' );


				} else {

					$new[ $col_id ] = $col_label;

				}

			}


			return array_merge( $new, $custom );

		}

		/**
		 * Reorder the admin columns.
		 *
		 * @since  3.0.0
		 *
		 * @param  array $columns List of admin columns
		 *
		 * @return array          Re-ordered list
		 */
		public function move_status_first( $columns ) {

			// Don't change columns order on mobiles as it breaks the layout. WordPress expects the title column to be the second one.
			// @link https://github.com/Awesome-Support/Awesome-Support/issues/306
			if ( wp_is_mobile() ) {
				return $columns;
			}

			if ( isset( $columns[ 'status' ] ) ) {
				$status_content = $columns[ 'status' ];
				unset( $columns[ 'status' ] );
			} else {
				return $columns;
			}

			$new = array();

			foreach ( $columns as $column => $content ) {

				if ( 'title' === $column ) {
					$new[ 'status' ] = $status_content;
				}

				$new[ $column ] = $content;

			}

			return $new;

		}

		/**
		 * Add ticket ID to the ticket title in admin list screen
		 *
		 * @since 3.3
		 *
		 * @param string $title Original title
		 *
		 * @return string
		 */
		public function add_ticket_id_title( $title ) {

			global $pagenow;

			if ( 'edit.php' !== $pagenow || !isset( $_GET[ 'post_type' ] ) || 'ticket' !== $_GET[ 'post_type' ] ) {
				return $title;
			}

			//$id = get_the_ID();

			//$title = "$title (#$id)";

			return $title;

		}

		/**
		 * Get all ticket replies
		 *
		 * Try to get the replies from cache and if not possible, run the query and cache the result.
		 *
		 * @since 3.3
		 *
		 * @param int $ticket_id ID of the ticket we want to get the replies for
		 *
		 * @return WP_Query
		 */
		public function get_replies_query( $ticket_id ) {

			$q = wp_cache_get( 'replies_query_' . $ticket_id, 'wpas' );

			if ( false === $q ) {

				$args = array(
					'post_parent'            => $ticket_id,
					'post_type'              => 'ticket_reply',
					'post_status'            => array( 'unread', 'read' ),
					'posts_per_page'         => -1,
					'orderby'                => 'date',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'cache_results'          => false,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
				);

				$q = new WP_Query( $args );

				// Cache the result
				wp_cache_add( 'replies_query_' . $ticket_id, $q, 'wpas', 600 );

			}

			return $q;

		}

		/**
		 * Hide closed tickets.
		 *
		 * If the plugin is set to hide closed tickets,
		 * we modify the "All Tickets" link in the post type menu
		 * and append the status filter with the "open" value.
		 *
		 * @since  3.0.0
		 * @return bool True if the closed tickets were hiddne, false otherwise
		 */
		public function hide_closed_tickets() {

			$hide = (bool)wpas_get_option( 'hide_closed' );

			if ( true !== $hide ) {
				return false;
			}

			global $submenu;

			if ( is_array( $submenu ) && array_key_exists( 'edit.php?post_type=ticket', $submenu ) && isset( $submenu[ 5 ] ) ) {
				$submenu[ "edit.php?post_type=ticket" ][ 5 ][ 2 ] = $submenu[ "edit.php?post_type=ticket" ][ 5 ][ 2 ] . '&amp;wpas_status=open';
			}

			return true;

		}

		/**
		 * Remove the ticket excerpt.
		 *
		 * We don't want ot display the ticket excerpt in the tickets list
		 * when the excerpt mode is selected.
		 *
		 * @param  string $content Ticket excerpt
		 * @return string          Excerpt if applicable or empty string otherwise
		 */
		public function remove_excerpt( $content ) {

			global $mode;

			if ( !is_admin() || !isset( $_GET[ 'post_type' ] ) || 'ticket' !== $_GET[ 'post_type' ] ) {
				return $content;
			}

			global $mode;

			if ( 'excerpt' === $mode ) {
				return '';
			}

			return $content;
		}

		public function get_custom_fields() {
			return WPAS()->custom_fields->get_custom_fields(); //$this->get_custom_fields();

		}

		/**
		 * Filter the list of CSS classes for the current post.
		 *
		 * @since 3.3
		 *
		 * @param array $classes An array of post classes.
		 * @param array $class An array of additional classes added to the post.
		 * @param int $post_id The post ID.
		 *
		 * @return array
		 */
		public function ticket_row_class( $classes, $class, $post_id ) {

			global $pagenow;

			if ( 'edit.php' !== $pagenow || !isset( $_GET[ 'post_type' ] ) || isset( $_GET[ 'post_type' ] ) && 'ticket' !== $_GET[ 'post_type' ] ) {
				return $classes;
			}

			if ( !is_admin() ) {
				return $classes;
			}

			if ( 'ticket' !== get_post_type( $post_id ) ) {
				return $classes;
			}

			$replies = $this->get_replies_query( $post_id );

			if ( true === wpas_is_reply_needed( $post_id, $replies ) ) {
				$classes[] = 'wpas-awaiting-support-reply';
			}

			if ( 'closed' === wpas_get_ticket_status( $post_id ) ) {
				$classes[] = 'wpas-ticket-list-row-closed';
			}

			return $classes;

		}


		/**
		 * Manage core column content.
		 *
		 * @since  3.0.0
		 * @param  array $column Column currently processed
		 * @param  integer $post_id ID of the post being processed
		 */
		public function core_custom_columns_content( $column, $post_id ) {

			$fields = $this->get_custom_fields();

			if ( isset( $fields[ $column ] ) ) {

				if ( true === $fields[ $column ][ 'args' ][ 'show_column' ] ) {

					switch ( $column ) {

						case 'author':

							$client = get_user_by( 'id', get_the_author_meta( 'ID' ) );
							$link = add_query_arg( array( 'post_type' => 'ticket', 'author' => $client->ID ), admin_url( 'edit.php' ) );

							echo "<a href='$link'>$client->display_name</a><br>$client->user_email";

							break;

						case 'id':

							$link = add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) );
							echo "<strong><a href='$link'>{$post_id}</a></strong>";

							break;

						case 'wpas-activity':

							$tags = array();
							$replies = $this->get_replies_query( $post_id );

							/**
							 * We check when was the last reply (if there was a reply).
							 * Then, we compute the ticket age and if it is considered as
							 * old, we display an informational tag.
							 */
							if ( 0 === $replies->post_count ) {
								echo _x( 'No reply yet.', 'No last reply', 'awesome-support' );
							} else {

								$last_reply = $replies->posts[ $replies->post_count - 1 ];
								$last_user_link = add_query_arg( array( 'user_id' => $last_reply->post_author ), admin_url( 'user-edit.php' ) );
								$last_user = get_user_by( 'id', $last_reply->post_author );
								$role = true === user_can( $last_reply->post_author, 'edit_ticket' ) ? _x( 'agent', 'User role', 'awesome-support' ) : _x( 'client', 'User role', 'awesome-support' );

								echo _x( sprintf( _n( '%s reply.', '%s replies.', $replies->post_count, 'awesome-support' ), $replies->post_count ), 'Number of replies to a ticket', 'awesome-support' );
								echo '<br>';
								printf( _x( '<a href="%s">Last replied</a> %s ago by %s (%s).', 'Last reply ago', 'awesome-support' ), add_query_arg( array(
										'post'   => $post_id,
										'action' => 'edit',
									), admin_url( 'post.php' ) ) . '#wpas-post-' . $last_reply->ID, human_time_diff( strtotime( $last_reply->post_date ), current_time( 'timestamp' ) ), '<a href="' . $last_user_link . '">' . $last_user->user_nicename . '</a>', $role );
							}

							// Maybe add the "Awaiting Support Response" tag
							if ( true === wpas_is_reply_needed( $post_id, $replies ) ) {
								$color = ( false !== ( $c = wpas_get_option( 'color_awaiting_reply', false ) ) ) ? $c : '#0074a2';
								array_push( $tags, "<span class='wpas-label' style='background-color:$color;'>" . __( 'Awaiting Support Reply', 'awesome-support' ) . "</span>" );
							}

							// Maybe add the "Old" tag
							if ( true === wpas_is_ticket_old( $post_id, $replies ) ) {
								$old_color = wpas_get_option( 'color_old' );
								array_push( $tags, "<span class='wpas-label' style='background-color:$old_color;'>" . __( 'Old', 'awesome-support' ) . "</span>" );
							}

							if ( !empty( $tags ) ) {
								echo '<br>' . implode( ' ', $tags );
							}

							break;

						default:

							/* In case a custom callback is specified we use it */
							if ( function_exists( $fields[ $column ][ 'args' ][ 'column_callback' ] ) ) {
								call_user_func( $fields[ $column ][ 'args' ][ 'column_callback' ], $fields[ $column ][ 'name' ], $post_id );
							} /* Otherwise we use the default rendering options */
							else {
								wpas_cf_value( $fields[ $column ][ 'name' ], $post_id );
							}

					}
				}
			}


		}


		/**
		 * Add filters for custom taxonomies
		 *
		 * @since  2.0.0
		 * @return void
		 */
		public function custom_taxonomy_filter() {

			global $typenow;

			if ( 'ticket' != $typenow ) {
				echo '';
			}

			$post_types = get_post_types( array( '_builtin' => false ) );

			if ( in_array( $typenow, $post_types ) ) {

				$filters = get_object_taxonomies( $typenow );

				/* Get all custom fields */
				$fields = $this->get_custom_fields();

				foreach ( $filters as $tax_slug ) {

					if ( !array_key_exists( $tax_slug, $fields ) ) {
						continue;
					}

					if ( true !== $fields[ $tax_slug ][ 'args' ][ 'filterable' ] ) {
						continue;
					}

					$tax_obj = get_taxonomy( $tax_slug );

					$args = array(
						'show_option_all' => __( 'All ' . $tax_obj->label ),
						'taxonomy'        => $tax_slug,
						'name'            => $tax_obj->name,
						'orderby'         => 'name',
						'hierarchical'    => $tax_obj->hierarchical,
						'show_count'      => true,
						'hide_empty'      => true,
						'hide_if_empty'   => true,
					);

					if ( isset( $_GET[ $tax_slug ] ) ) {
						$args[ 'selected' ] = filter_input( INPUT_GET, $tax_slug, FILTER_SANITIZE_STRING );
					}

					wp_dropdown_categories( $args );

				}
			}

		}

		public function restrict_manage_posts( $post_type, $which ) {

			if ( 'ticket' !== $post_type || 'top' !== $which ) {
				return;
			}

			/* STATE */

			$this_sort = isset( $_GET[ 'status' ] ) ? filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING ) : 'any';
			$all_selected = ( 'any' === $this_sort ) ? 'selected="selected"' : '';
			$open_selected = ( !isset( $_GET[ 'status' ] ) && true === (bool)wpas_get_option( 'hide_closed' ) || 'open' === $this_sort ) ? 'selected="selected"' : '';
			$closed_selected = ( 'closed' === $this_sort ) ? 'selected="selected"' : '';

			$dropdown = '<select id="status" name="status">';
			$dropdown .= "<option value='any' $all_selected>" . __( 'All States', 'awesome-support' ) . "</option>";
			$dropdown .= "<option value='open' $open_selected>" . __( 'Open', 'awesome-support' ) . "</option>";
			$dropdown .= "<option value='closed' $closed_selected>" . __( 'Closed', 'awesome-support' ) . "</option>";
			$dropdown .= '</select>';

			echo $dropdown;


			/* STATUS */

			if ( !isset( $_GET[ 'post_status' ] )
				|| isset( $_GET[ 'post_status' ] ) && 'trash' !== $_GET[ 'post_status' ]
			) {
				$this_sort = isset( $_GET[ 'post_status' ] ) ? filter_input( INPUT_GET, 'post_status', FILTER_SANITIZE_STRING ) : 'any';
				$all_selected = ( 'any' === $this_sort ) ? 'selected="selected"' : '';

				$dropdown = '<select id="post_status" name="post_status" >'; //disabled >';
				$dropdown .= "<option value='any' $all_selected>" . __( 'All Status', 'awesome-support' ) . "</option>";

				/**
				 * Get available statuses.
				 */
				$custom_statuses = wpas_get_post_status();

				foreach ( $custom_statuses as $_status_id => $_status_value ) {
					$custom_status_selected = ( isset( $_GET[ 'post_status' ] ) && $_status_id === $this_sort ) ? 'selected="selected"' : '';
					$dropdown .= "<option value='" . $_status_id . "' " . $custom_status_selected . " >" . __( $_status_value, 'awesome-support' ) . "</option>";
				}

				$dropdown .= '</select>';

				echo $dropdown;
			}


			$fields = $this->get_custom_fields();

			/* AGENT */

			if ( $fields[ 'assignee' ][ 'args' ][ 'filterable' ] ) {

				$selected = __( 'All Agents', 'awesome-support' );
				$selected_value = '';

				if ( isset( $_GET[ 'assignee' ] ) && !empty( $_GET[ 'assignee' ] ) ) {
					$staff_id = (int)$_GET[ 'assignee' ];
					$agent = new WPAS_Member_Agent( $staff_id );

					if ( $agent->is_agent() ) {
						$user = get_user_by( 'ID', $staff_id );
						$selected = $user->display_name;
						$selected_value = $staff_id;
					}
				}

				$atts = array(
					'name'      => 'assignee',
					'id'        => 'assignee',
					'disabled'  => !current_user_can( 'assign_ticket' ) ? true : false,
					//'please_select' => $selected,
					'select2'   => true,
					'data_attr' => array( 'capability' => 'edit_ticket' ),
				);

				echo wpas_dropdown( $atts, "<option value='' " . $selected_value . ">" . $selected . "</option>" );

			}

			/* CLIENT */

			$selected = __( 'All Clients', 'awesome-support' );
			$selected_value = '';

			if ( isset( $_GET[ 'author' ] ) && !empty( $_GET[ 'author' ] ) ) {

				$client_id = (int)$_GET[ 'author' ];

				$user = get_user_by( 'ID', $client_id );
				$selected = $user->display_name;
				$selected_value = $client_id;
			}

			$client_atts = array(
				'name'      => 'author',
				'id'        => 'author',
				//'please_select' => $selected,
				'disabled'  => !current_user_can( 'assign_ticket' ) ? true : false,
				'select2'   => true,
				'data_attr' => array( 'capability' => 'view_ticket' ),
			);

			echo wpas_dropdown( $client_atts, "<option value='' " . $selected_value . ">" . $selected . "</option>" );


			/* TICKET ID */

			$selected_value = '';

			if ( isset( $_GET[ 'id' ] ) && !empty( $_GET[ 'id' ] ) ) {
				$ticket_id = $_GET[ 'id' ];
				$selected_value = $ticket_id;
			}

			echo '<input type="text" placeholder="Ticket ID" name="id" id="id" value="' . $selected_value . '" />';

			echo '<div style="clear:both;"></div>';

			/* RESET FILTERS */

			echo '<span class="alignright" style="line-height: 28px; margin: 0 25px;">';
			echo $this->reset_link();
			echo '</span>';

		}

		public function reset_link() {

			$link = add_query_arg( array( 'post_type' => 'ticket' ), admin_url( 'edit.php' ) );

			return "<a href='$link'>Reset Filters</a>";

		}

		/**
		 * Display notice
		 *
		 * @param $which
		 *
		 */
		public function manage_posts_extra_tablenav( $which ) {

			if ( wp_is_mobile()
				|| !isset( $_GET[ 'post_type' ] )
				|| 'ticket' !== $_GET[ 'post_type' ]
			) {
				return;
			}

			if ( 'bottom' === $which ) {

				echo '<div class="alignright" style="clear: both; overflow: hidden; margin: 20px 10px;" class="noticekk notice-warningkk is-dismissiblekk"><p>'
					. __( 'NOTE: Please be aware that when you sort on a column, tickets that have never had a value entered into that column will not appear on your sorted list (null fields). This can reduce the number of tickets in your sorted list. This is by design. You should also be aware that deliberately entering a blank into a ticket field is considered data so those tickets will show up in your sort.', 'awesome-support' )
					. ' - '
					. $this->reset_link()
					. '</p></div>';
			}

		}


		public function parse_request() {

			global $wp;

			$fields = $this->get_custom_fields();

			// Map query vars to their keys, or get them if endpoints are not supported
			foreach ( $fields as $key => $var ) {
				if ( isset( $_GET[ $var[ 'name' ] ] ) ) {
					$wp->query_vars[ $key ] = $_GET[ $var[ 'name' ] ];
				} elseif ( isset( $wp->query_vars[ $var[ 'name' ] ] ) ) {
					$wp->query_vars[ $key ] = $wp->query_vars[ $var ];
				}
			}

		}

		public function add_custom_fields( $fields ) {

			global $pagenow, $typenow;

			if ( 'edit.php' !== $pagenow && 'ticket' !== $typenow ) {
				return;
			}

			wpas_add_custom_field( 'id', array(
				'show_column'     => true,
				'sortable_column' => true,
				'filterable'      => true,
				'title'           => __( 'ID', 'awesome-support' ),
			) );

			wpas_add_custom_field( 'author', array(
				'show_column'     => true,
				'sortable_column' => true,
				'filterable'      => true,
				'title'           => __( 'Created by', 'awesome-support' ),
			) );

			wpas_add_custom_field( 'wpas-activity', array(
				'show_column'     => true,
				'sortable_column' => true,
				'filterable'      => true,
				'title'           => __( 'Activity', 'awesome-support' ),
			) );

			return $this->get_custom_fields();

		}

		public function wpas_post_clauses( $clauses, $wp_query ) {

			if ( !isset( $wp_query->query[ 'post_type' ] )
				|| $wp_query->query[ 'post_type' ] !== 'ticket'
				|| !$wp_query->is_main_query()
				|| !isset( $wp_query->query[ 'orderby' ] )
			) {
				return $clauses;
			}

			global $wpdb;

			$fields = $this->get_custom_fields();
			$orderby = $wp_query->query[ 'orderby' ];

			if ( !empty( $orderby ) && array_key_exists( $orderby, $fields ) ) {

				if ( 'taxonomy' == $fields[ $orderby ][ 'args' ][ 'field_type' ] && !$fields[ $orderby ][ 'args' ][ 'taxo_std' ] ) {

					$clauses[ 'join' ] .= <<<SQL
LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;

					$clauses[ 'where' ] .= " AND (taxonomy = '" . $orderby . "' OR taxonomy IS NULL)";
					$clauses[ 'groupby' ] = "object_id";
					$clauses[ 'orderby' ] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) ";
					$clauses[ 'orderby' ] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

				} elseif ( 'status' === $orderby ) {

					$clauses[ 'orderby' ] = "{$wpdb->posts}.post_status ";
					$clauses[ 'orderby' ] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

				} elseif ( 'assignee' === $orderby ) {

					//Join user table onto the postmeta table
					$clauses[ 'join' ] .= " LEFT JOIN {$wpdb->users} ON {$wpdb->prefix}postmeta.meta_value={$wpdb->users}.ID";
					$clauses[ 'orderby' ] = "{$wpdb->users}.display_name ";
					$clauses[ 'orderby' ] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

				} elseif ( 'author' === $orderby ) {

					//Join user table onto the postmeta table
					$clauses[ 'join' ] .= " LEFT JOIN {$wpdb->users} ON {$wpdb->prefix}posts.post_author={$wpdb->users}.ID";
					$clauses[ 'orderby' ] = " {$wpdb->users}.display_name ";
					$clauses[ 'orderby' ] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

				} elseif ( 'id' === $orderby ) {

					$clauses[ 'orderby' ] = " {$wpdb->posts}.ID ";
					$clauses[ 'orderby' ] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

				} else {

					$wp_query->set( 'meta_key', '_wpas_' . $orderby );
					$wp_query->set( 'orderby', 'meta_value' );

                }

				apply_filters( 'wpas_custom_column_orderby', $wp_query );

			}

			return $clauses;
		}

		/**
		 * Make custom columns sortable
		 *
		 * @param  array $columns Already sortable columns
		 *
		 * @return array          New sortable columns
		 * @since  3.0.0
		 */
		public function custom_columns_sortable( $columns ) {

			$new = array();

			//$new[ 'author' ] = 'author';
			//$new[ 'id' ] = 'id';

			$fields = $this->get_custom_fields();

			foreach ( $fields as $field ) {

				/* If CF is a regular taxonomy we don't handle it, WordPress does */
				if ( 'taxonomy' == $field[ 'args' ][ 'field_type' ] && true === $field[ 'args' ][ 'taxo_std' ] ) {
					continue;
				}

				if ( true === $field[ 'args' ][ 'show_column' ] && true === $field[ 'args' ][ 'sortable_column' ] ) {
					$id = $field[ 'name' ];
					$new[ $id ] = $id;
				}

			}

			return apply_filters( 'wpas_custom_columns_sortable', array_merge( $columns, $new ) );

		}

		/**
		 * Convert taxonomy term ID into term slug.
		 *
		 * When filtering, WordPress uses the term ID by default in the query but
		 * that doesn't work. We need to convert it to the taxonomy term slug.
		 *
		 * @param  object $query WordPress current main query
		 *
		 * @return void
		 *
		 * @since  2.0.0
		 * @link   http://wordpress.stackexchange.com/questions/578/adding-a-taxonomy-filter-to-admin-list-for-a-custom-post-type
		 */
		public function custom_taxonomy_filter_convert_id_term( $query ) {

			global $pagenow;

			/* Check if we are in the correct post type */
			if ( is_admin()
				&& 'edit.php' == $pagenow
				&& isset( $_GET[ 'post_type' ] )
				&& 'ticket' === $_GET[ 'post_type' ]
				&& $query->is_main_query()
			) {

				/* Get all custom fields */
				$fields = $this->get_custom_fields();

				/* Filter custom fields that are taxonomies */
				foreach ( $query->query_vars as $arg => $value ) {

					if ( array_key_exists( $arg, $fields ) && 'taxonomy' === $fields[ $arg ][ 'args' ][ 'field_type' ] && true === $fields[ $arg ][ 'args' ][ 'filterable' ] ) {

						$term = get_term_by( 'id', $value, $arg );

						// Depending on where the filter was triggered (dropdown or click on a term) it uses either the term ID or slug. Let's see if this term slug exists
						if ( is_null( $term ) ) {
							$term = get_term_by( 'slug', $value, $arg );
						}

						if ( !empty( $term ) ) {
							$query->query_vars[ $arg ] = $term->slug;
						}

					}

				}

			}
		}

		public function custom_search_where( $where ) {

			global $pagenow, $typenow;

			if ( !is_search()
				|| 'edit.php' !== $pagenow
				|| 'ticket' !== $typenow

			) {
				return $where;
			}

			$where_original = $where;

			global $wpdb;

			// Overwrite the existing WHERE clause.
			$where = '';

			// Store all search terms into array.
			$search_terms = explode( ' ', get_search_query() );

			// Tables names.
			$type = $wpdb->prefix . "posts.post_type";
			$status = $wpdb->prefix . "posts.post_status";
			$title = $wpdb->prefix . "posts.post_title";
			$content = $wpdb->prefix . "posts.post_content";
			$meta_value = $wpdb->prefix . "postmeta.meta_value";

			foreach ( $search_terms as $term ) {
				$term = trim( $term );
				$where .= " AND ( ($title LIKE '%$term%') OR ($content LIKE '%$term%') OR ($meta_value LIKE '%$term%') ) ";
			}

			// As WHERE clause is overwritten, need to specify the post type, the status and/or anything else.
			// Post Types.
			//$where .= " AND ($type IN ('post', 'page', ... )) ";
			// Post status.
			//$where .= " AND ($status = 'publish') ";

			return $where_original;
		}


		public function register_my_option() {

			$screen = get_current_screen(); //Initiate the $screen variable.

			add_filter( 'screen_layout_columns', array( $this, 'display_my_option' ) ); //Add our custom HTML to the screen options panel.
			$screen->add_option( 'my_option', '' ); //Register our new screen options tab.

		}

		public function display_my_option() {

			?>
            <span>Hello World!</span>;

			<?php
		}

		public function in_admin_header() {
			global $wp_meta_boxes;
			unset( $wp_meta_boxes[ get_current_screen()->id ][ 'normal' ][ 'core' ][ 'trackbacksdiv' ] );
		}


	}