<?php 
/*
Plugin Name: Lawrence Custom Emailer
Description: Plugin to create a custom email format for Lawrence articles that can sent to the Lawrenceville community via Gmail 
Version: 1.0
Author: Ricky Williams
*/
 
// prevent direct access to plugin files (cause why not) 
defined( 'ABSPATH' ) or die( 'Illegal access error!' ); 

// register the lawrcustemail_setup_scripts() function to get the CSS working
add_action( 'wp_enqueue_scripts', 'lawrcustemail_setup_scripts' );

/**
 * Add all stylesheets/scripts to the plugin 
 *
 * @since 1.0.0
 *
 */
function lawrcustemail_setup_scripts()
{
	wp_enqueue_style( 'lawrcustemail_stylesheet', plugins_url('lawrence-custom-emailer.css', __FILE__) );
}

/**
 * Display posts to select in a drop-down menu 
 * 
 * 
 * @since 1.0.0
 * 
 * @see get_posts()
 */
function lawrcustemail_create_post_select() {
    echo '<select>';
    
    // get an array of all WP_Post objects and sort them (most recent articles at the top of the menu, etc.)
	$get_posts_args = array(
		'posts_per_page' => -1,
		'orderby' => 'date',
		'order' => 'DESC',
	);
	
    $all_posts = get_posts( $get_posts_args );
    
    // show the post titles via a select tag; if no posts could be found, then something is really wrong
    if ( $all_posts ) {
    	foreach ( $all_posts as $post ) {
    		echo '<option value="' . $post->ID . '">' . $post->post_title . '</option>';
    	}
    } else {
    	die( 'No posts could be found!' );
    }
    
    echo '</select>';
}

// register the lawremail_add_admin_menu() function to create a new dashboard menu item
add_action( 'admin_menu', 'lawrcustemail_add_admin_menu' );

/** 
 * Add an admin dashboard menu to access the email customizer and emailer 
 * 
 * @since 1.0.0
 * 
 * @see add_dashboard_page()
 */ 
function lawrcustemail_add_admin_menu() {
    add_menu_page( 'Lawrence Custom Email', 'Custom Emailer', 'manage_options', 'lawrcustemail', 'lawremail_write_admin_menu' );
}

/** 
 * Write the basic html of the plugin's dashboard menu option
 * 
 * @since 1.0.0
 * 
 * @see?
 */ 
function lawrcustemail_write_admin_menu() {
	lawrcustemail_create_post_select();
}
 
?>
