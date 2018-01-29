<?php

/**
 * Main tabs area on ticket edit page
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


add_filter( 'wpas_admin_tabs_ticket_main', 'wpas_ticket_main_tabs' ); // Register tabs in main tabs area

/**
 * Register tabs 
 * 
 * @param array $tabs
 * 
 * @return array
 */
function wpas_ticket_main_tabs( $tabs ) {
	
	
	$options = maybe_unserialize( get_option( 'wpas_options', array() ) );
	
	$tabs['ticket']	= __( 'Ticket' , 'awesome-support' );
	
	if ( WPAS()->custom_fields->have_custom_fields() ) {
		$tabs['custom_fields'] = __( 'Custom Fields' , 'awesome-support' );
	}
	
	if (  ( isset( $options['multiple_agents_per_ticket'] ) && true === boolval( $options['multiple_agents_per_ticket'] ) ) or ( isset( $options['show_third_party_fields'] ) && true === boolval( $options['show_third_party_fields'] ) ) ) {
		$tabs['ai_parties'] = __( 'Additional Interested Parties', 'awesome-support' );
	}
	
	return $tabs;
}


add_filter( 'wpas_admin_tabs_ticket_main', 'wpas_ticket_main_tabs2', 16 ); //Register more tabs in main tabs area

/**
 * Register tabs
 * 
 * @param array $tabs
 * 
 * @return array
 */
function wpas_ticket_main_tabs2( $tabs ) {
	
	$tabs['statistics']	= __( 'Statistics' , 'awesome-support' );
	
	return $tabs;
}

add_filter( 'wpas_admin_tabs_ticket_main_ticket_content', 'wpas_ticket_main_tab_content' );

/**
 * Return content for ticket tab
 * 
 * @global object $post
 * 
 * @param string $content
 * 
 * @return string
 */
function wpas_ticket_main_tab_content( $content ) {
	global $post;
	
	ob_start();
	
	echo '<div class="wpas-post-body-content"></div><div class="clear clearfix"></div>';
	
	
	if( isset( $_GET['post'] ) ) {
		
		include WPAS_PATH . "includes/admin/metaboxes/message.php";
	}
	
	$content = ob_get_clean();
	return $content;
}


add_filter( 'wpas_admin_tabs_ticket_main_custom_fields_content', 'wpas_custom_fields_main_tab_content' );

/**
 * Return content for custom fields tab
 * 
 * @param string $content
 * 
 * @return string
 */
function wpas_custom_fields_main_tab_content( $content ) {
	ob_start();
	
	include WPAS_PATH . "includes/admin/metaboxes/tab-custom-fields.php";
	
	$content = ob_get_clean();
	return $content;
}

add_filter( 'wpas_admin_tabs_ticket_main_ai_parties_content', 'wpas_ai_parties_main_tab_content' );

/**
 * Return content for additional interested parties
 * 
 * @param string $content
 * 
 * @return string
 */
function wpas_ai_parties_main_tab_content( $content ) {
	ob_start();
	
	include WPAS_PATH . "includes/admin/metaboxes/ticket-additional-parties.php";
	
	$content = ob_get_clean();
	return $content;
}

add_filter( 'wpas_admin_tabs_ticket_main_statistics_content', 'wpas_statistics_main_tab_content' );

/**
 * Return content for statistics tab
 * 
 * @param string $content
 * 
 * @return string
 */
function wpas_statistics_main_tab_content( $content ) {
	ob_start();
	include WPAS_PATH . "includes/admin/metaboxes/ticket-statistics.php";
	
	$content = ob_get_clean();
	return $content;
}

/**
 * Print main tabs in ticket edit page
 */
echo wpas_admin_tabs( 'ticket_main' );