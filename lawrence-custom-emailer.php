<?php 
/*
Plugin Name: Lawrence Custom Emailer
Description: Plugin to create a custom email format for Lawrence articles that can sent to the Lawrenceville community via Gmail 
Version: 1.0
Author: Ricky Williams
*/
 
// prevent direct access to plugin files (cause why not) 
defined( 'ABSPATH' ) or die( 'Illegal access error!' ); 

// register the lawremail_add_admin_menu() function to create a new dashboard menu item
add_action( 'admin_menu', 'lawremail_add_admin_menu' );

/** 
 * Add an admin dashboard menu to access the email customizer and emailer 
 * 
 * Description?
 * 
 * @since 1.0.0
 * 
 * @see add_dashboard_page()
 */ 
function lawremail_add_admin_menu() {
    add_menu_page( 'Lawrence Custom Email', 'Custom Emailer', 'manage_options', 'lawrcustemail', 'lawremail_write_admin_menu' );
}

/** 
 * Write the basic html of the plugin's dashboard menu option
 * 
 * Description?
 * 
 * @since 1.0.0
 * 
 * @see?
 */ 
function lawremail_write_admin_menu() {
    // display possible posts to be attached in a drop-down menu 
    echo '<select>';
    
	$get_posts_args = array(
		'orderby' => 'date',
		'order' => 'DESC',
		'post_type' => 'post',
		'post_status' => 'publish'
	);

    $all_posts = get_posts( $get_posts_args );
    
    if ( $all_posts ) {
    	foreach ( $all_posts as $post ) {
    		setup_postdata( $post );
    		echo '<option value="' . the_ID() . '">' . the_title( '', '', false ) . '</option>';
    	}
    }
    
    echo '</select>';
}
 
?>
